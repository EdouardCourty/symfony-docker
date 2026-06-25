#!/bin/bash

# deploy.sh
# Déploie une nouvelle version de l'application sur un serveur déjà préparé par
# server_setup.sh.
#
# Usage : sudo bash deployment/deploy.sh
#
# Prérequis (premier déploiement uniquement, à faire à la main) :
#   - git clone <repo> $APP_DIR
#   - $APP_DIR/app/.env.local créé avec les identifiants DB (affichés par server_setup.sh)
#   - certificat SSL en place (non géré par ces scripts)
#
# Variables surchargeables : APP_USER, APP_DIR, PHP_VERSION (mêmes défauts que server_setup.sh)

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
    echo "Ce script doit être exécuté en root (sudo)." >&2
    exit 1
fi

APP_USER="${APP_USER:-www-data}"
APP_DIR="${APP_DIR:-/var/www/app}"
PHP_VERSION="${PHP_VERSION:-8.4}"

PHP_DIR="$APP_DIR/app"
PHP_BIN="/usr/bin/php"
COMPOSER_BIN="/usr/local/bin/composer"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Shorthand : exécute une commande en tant qu'utilisateur applicatif (pas root) — pour tout ce
# qui touche au runtime de l'app (cache, console), afin que les fichiers générés appartiennent
# bien à l'utilisateur qui fera ensuite tourner PHP-FPM/le crontab.
run_as_app() {
    sudo -u "$APP_USER" "$@"
}

console() {
    run_as_app "$PHP_BIN" "$PHP_DIR/bin/console" "$@" --env=prod
}

echo "🚀 Déploiement"
echo "==========================="

if [ ! -d "$APP_DIR/.git" ]; then
    echo "❌ ${APP_DIR} n'est pas un dépôt git — clone-le une première fois à la main." >&2
    exit 1
fi

if [ ! -f "$PHP_DIR/.env.local" ]; then
    echo "❌ ${PHP_DIR}/.env.local manquant — voir deployment/documentation.md (premier déploiement)." >&2
    exit 1
fi

echo "📥 Git pull..."
cd "$APP_DIR"
git pull origin main

echo "📁 Préparation de ${PHP_DIR} (ownership ${APP_USER})..."
mkdir -p "$PHP_DIR/var/cache" "$PHP_DIR/var/log"
chown -R "$APP_USER":"$APP_USER" "$PHP_DIR"

echo "📦 Installation des dépendances (composer, en tant que ${APP_USER})..."
# Pas de --no-scripts : composer.json déclenche assets:install automatiquement.
cd "$PHP_DIR"
run_as_app "$PHP_BIN" "$COMPOSER_BIN" install --no-dev --optimize-autoloader

echo "🗄️  Migrations..."
console doctrine:migrations:migrate --no-interaction

echo "🧹 Cache..."
console cache:clear
console cache:warmup

echo "⏰ Synchronisation du crontab..."
CRONTAB_TMP="$(mktemp)"
sed \
    -e "s/__APP_USER__/${APP_USER}/g" \
    -e "s#__APP_DIR__#${APP_DIR}#g" \
    "$SCRIPT_DIR/cron/crontab.dist" > "$CRONTAB_TMP"
crontab -u "$APP_USER" "$CRONTAB_TMP"
rm -f "$CRONTAB_TMP"

echo "🔄 Reload PHP-FPM..."
systemctl reload "php${PHP_VERSION}-fpm"

echo ""
echo "✅ Déploiement terminé à $(date +'%Y-%m-%d %H:%M:%S')"
