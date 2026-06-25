#!/bin/bash

# server_setup.sh
# Prépare un VPS Ubuntu vierge (ou vérifie/complète un serveur existant) pour héberger cette
# application : nginx, PHP-FPM, PostgreSQL, utilisateur applicatif, crontab. Idempotent — peut
# être relancé sans risque (utile en cas de migration vers un nouveau serveur).
#
# Ne gère PAS : le SSL (à configurer séparément, ex. certbot), le clonage/déploiement du code
# applicatif (voir deployment/deploy.sh), ni la création du fichier app/.env.local.
#
# Usage : sudo DOMAIN=your-domain.example bash deployment/server_setup.sh [-y|--yes]
#
#   -y, --yes   Ne pas demander de confirmation avant d'appliquer les changements système
#               (utile pour un lancement scripté/non-interactif).
#
# Variables surchargeables :
#   APP_USER     (défaut: www-data)
#   APP_DIR      (défaut: /var/www/app)
#   DOMAIN       (défaut: example.com — à surcharger, pas de valeur sensée par défaut)
#   PHP_VERSION  (défaut: 8.4)
#   DB_NAME      (défaut: app)
#   DB_USER      (défaut: app)
#   DB_PASSWORD  (défaut: généré aléatoirement si le rôle n'existe pas encore)

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
    echo "Ce script doit être exécuté en root (sudo)." >&2
    exit 1
fi

ASSUME_YES=0
for arg in "$@"; do
    case "$arg" in
        -y|--yes) ASSUME_YES=1 ;;
    esac
done

APP_USER="${APP_USER:-www-data}"
APP_DIR="${APP_DIR:-/var/www/app}"
DOMAIN="${DOMAIN:-example.com}"
PHP_VERSION="${PHP_VERSION:-8.4}"
DB_NAME="${DB_NAME:-app}"
DB_USER="${DB_USER:-app}"
DB_PASSWORD="${DB_PASSWORD:-}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

PHP_EXTENSION_SUFFIXES=(cli fpm pgsql intl curl zip bcmath gmp mbstring xml apcu)

build_php_extensions() {
    PHP_EXTENSIONS=()
    for suffix in "${PHP_EXTENSION_SUFFIXES[@]}"; do
        PHP_EXTENSIONS+=("php${PHP_VERSION}-${suffix}")
    done
}

build_php_extensions

echo "🔧 Setup serveur"
echo "============================="
echo "  Domaine     : $DOMAIN"
echo "  APP_DIR     : $APP_DIR"
echo "  APP_USER    : $APP_USER"
echo "  PHP         : $PHP_VERSION"
echo ""

if [ "$ASSUME_YES" -ne 1 ]; then
    read -r -p "Appliquer ces changements système (paquets, nginx, PHP-FPM, PostgreSQL, crontab) ? [y/N] " confirm
    case "$confirm" in
        [yY]|[yY][eE][sS]) ;;
        *) echo "Annulé."; exit 1 ;;
    esac
fi

echo "📦 Mise à jour des paquets..."
apt-get update -y

# --- nginx ---------------------------------------------------------------

if command -v nginx &>/dev/null; then
    echo "✅ nginx déjà installé ($(nginx -v 2>&1))"
else
    echo "📦 Installation de nginx..."
    apt-get install -y nginx
fi

# --- PHP -------------------------------------------------------------------

if ! apt-cache show "php${PHP_VERSION}-fpm" &>/dev/null; then
    # PHP_VERSION absent des dépôts par défaut : soit le système est trop ancien (pas de paquet
    # PHP moderne du tout, le PPA ondrej/php comble l'écart), soit trop récent (le PPA n'a pas
    # encore de build pour cette release Ubuntu, mais les dépôts par défaut fournissent déjà une
    # version plus récente que celle demandée).
    NATIVE_PHP_VERSION="$(apt-cache search -n '^php[0-9]+\.[0-9]+-fpm$' | grep -oP 'php\K[0-9]+\.[0-9]+(?=-fpm)' | sort -V | tail -1)"

    if [ -n "$NATIVE_PHP_VERSION" ]; then
        echo "📦 PHP ${PHP_VERSION} absent des dépôts, mais PHP ${NATIVE_PHP_VERSION} disponible nativement — utilisation de cette version."
        PHP_VERSION="$NATIVE_PHP_VERSION"
        build_php_extensions
    else
        echo "📦 Aucun paquet PHP moderne dans les dépôts par défaut, ajout du PPA ondrej/php..."
        apt-get install -y software-properties-common
        add-apt-repository -y ppa:ondrej/php
        apt-get update -y
    fi
fi

MISSING_PHP_PACKAGES=()
for package in "${PHP_EXTENSIONS[@]}"; do
    if ! dpkg -s "$package" &>/dev/null; then
        MISSING_PHP_PACKAGES+=("$package")
    fi
done

if [ "${#MISSING_PHP_PACKAGES[@]}" -eq 0 ]; then
    echo "✅ PHP ${PHP_VERSION} + extensions déjà installés"
else
    echo "📦 Installation des paquets PHP manquants : ${MISSING_PHP_PACKAGES[*]}"
    apt-get install -y "${MISSING_PHP_PACKAGES[@]}"
fi

if command -v composer &>/dev/null; then
    echo "✅ Composer déjà installé ($(composer --version 2>&1 | head -1))"
else
    echo "📦 Installation de Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# --- PostgreSQL -------------------------------------------------------------

if command -v psql &>/dev/null; then
    echo "✅ PostgreSQL déjà installé ($(psql --version))"
else
    echo "📦 Installation de PostgreSQL..."
    apt-get install -y postgresql postgresql-contrib
fi

systemctl enable --now postgresql

ROLE_EXISTS="$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='${DB_USER}'")"
if [ "$ROLE_EXISTS" = "1" ]; then
    echo "✅ Rôle PostgreSQL '${DB_USER}' déjà présent — mot de passe inchangé"
else
    if [ -z "$DB_PASSWORD" ]; then
        DB_PASSWORD="$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 32)"
    fi
    echo "📦 Création du rôle PostgreSQL '${DB_USER}'..."
    sudo -u postgres psql -c "CREATE ROLE ${DB_USER} WITH LOGIN PASSWORD '${DB_PASSWORD}';"
fi

DB_EXISTS="$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'")"
if [ "$DB_EXISTS" = "1" ]; then
    echo "✅ Base '${DB_NAME}' déjà présente"
else
    echo "📦 Création de la base '${DB_NAME}'..."
    sudo -u postgres psql -c "CREATE DATABASE ${DB_NAME} OWNER ${DB_USER};"
fi

# Authentification par mot de passe (scram-sha-256) pour le rôle applicatif, plutôt que le
# "peer" par défaut de Debian/Ubuntu qui ne fonctionne que pour l'utilisateur système "postgres".
PG_HBA="$(sudo -u postgres psql -tAc "SHOW hba_file" | xargs)"
if [ -f "$PG_HBA" ] && ! grep -q "^host\s*${DB_NAME}\s*${DB_USER}" "$PG_HBA"; then
    echo "📝 Ajout d'une règle d'authentification scram-sha-256 dans pg_hba.conf..."
    echo "host    ${DB_NAME}    ${DB_USER}    127.0.0.1/32    scram-sha-256" >> "$PG_HBA"
    systemctl reload postgresql
fi

# Tuning mémoire (adapté à un petit VPS) — déposé en conf.d, ne touche pas postgresql.conf
# lui-même. Ajuste les valeurs dans postgresql/app-tuning.conf.dist si le serveur a plus de RAM.
PG_CONFIG_DIR="$(dirname "$PG_HBA")"
PG_TUNING_DEST="${PG_CONFIG_DIR}/conf.d/99-app-tuning.conf"
if [ -d "${PG_CONFIG_DIR}/conf.d" ]; then
    echo "📝 Tuning PostgreSQL ($PG_TUNING_DEST)..."
    cp "$SCRIPT_DIR/postgresql/app-tuning.conf.dist" "$PG_TUNING_DEST"
    systemctl restart postgresql
else
    echo "⚠️  ${PG_CONFIG_DIR}/conf.d introuvable — tuning PostgreSQL ignoré (à appliquer manuellement)."
fi

# --- Utilisateur & répertoires applicatifs ----------------------------------

mkdir -p "$APP_DIR"
chown "$APP_USER":"$APP_USER" "$APP_DIR"

mkdir -p /var/log/cron
chown "$APP_USER":"$APP_USER" /var/log/cron

# --- PHP-FPM pool dédié ------------------------------------------------------

FPM_POOL_DEST="/etc/php/${PHP_VERSION}/fpm/pool.d/app.conf"
echo "📝 Configuration du pool PHP-FPM dédié ($FPM_POOL_DEST)..."
sed \
    -e "s/__APP_USER__/${APP_USER}/g" \
    -e "s/__PHP_VERSION__/${PHP_VERSION}/g" \
    "$SCRIPT_DIR/php-fpm/app-pool.conf.dist" > "$FPM_POOL_DEST"

systemctl enable --now "php${PHP_VERSION}-fpm"
systemctl restart "php${PHP_VERSION}-fpm"

# --- nginx (vhost + certificat) ---------------------------------------------

NGINX_READY=1
if ! APP_DIR="$APP_DIR" DOMAIN="$DOMAIN" PHP_VERSION="$PHP_VERSION" bash "$SCRIPT_DIR/nginx/setup.sh"; then
    NGINX_READY=0
fi

# --- Crontab -------------------------------------------------------------------

echo "📝 Installation du crontab de '${APP_USER}'..."
CRONTAB_TMP="$(mktemp)"
sed \
    -e "s/__APP_USER__/${APP_USER}/g" \
    -e "s#__APP_DIR__#${APP_DIR}#g" \
    "$SCRIPT_DIR/cron/crontab.dist" > "$CRONTAB_TMP"
crontab -u "$APP_USER" "$CRONTAB_TMP"
rm -f "$CRONTAB_TMP"

# --- Résumé ----------------------------------------------------------------

echo ""
echo "============================="
echo "✅ Setup serveur terminé."
echo ""

if [ "$NGINX_READY" -eq 0 ]; then
    echo "⚠️  nginx N'EST PAS actif pour ${DOMAIN} — certificat manquant dans /etc/ssl/app."
    echo "    Dépose ton certificat (cert.pem + key.pem) puis :"
    echo "      sudo bash deployment/nginx/setup.sh"
    echo ""
fi

echo "Étapes manuelles restantes :"
echo "  1. Déployer le code applicatif dans ${APP_DIR} (voir deployment/deploy.sh)."
echo "  2. Créer ${APP_DIR}/app/.env.local avec :"
echo "       APP_ENV=prod"
echo "       APP_SECRET=<à générer>"
echo "       DATABASE_HOST=127.0.0.1"
echo "       DATABASE_PORT=5432"
echo "       DATABASE_NAME=${DB_NAME}"
echo "       DATABASE_USERNAME=${DB_USER}"
if [ -n "$DB_PASSWORD" ]; then
    echo "       DATABASE_PASSWORD=${DB_PASSWORD}   (mot de passe généré, à noter précieusement)"
else
    echo "       DATABASE_PASSWORD=<mot de passe existant du rôle '${DB_USER}'>"
fi
echo "  3. Configurer le SSL pour ${DOMAIN} — non géré par ce script."
