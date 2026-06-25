#!/bin/bash

# setup.sh (nginx)
# Génère/rafraîchit le vhost nginx de l'application à partir de app.conf.dist. Idempotent,
# appelable seul pour ne pas avoir à relancer tout server_setup.sh après un simple changement de
# config nginx.
#
# Ne génère AUCUN certificat : le vhost référence /etc/ssl/app/{cert,key}.pem, à déposer
# manuellement (Let's Encrypt, certificat d'origine d'un CDN, etc.). Si ces fichiers sont
# absents, `nginx -t` échoue et ce script s'arrête avec un avertissement — la config est tout de
# même écrite sur disque, prête à être activée dès que le certificat est en place.
#
# Usage : sudo bash deployment/nginx/setup.sh
#
# Variables surchargeables : APP_DIR, DOMAIN, PHP_VERSION (mêmes défauts que server_setup.sh)

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
    echo "Ce script doit être exécuté en root (sudo)." >&2
    exit 1
fi

APP_DIR="${APP_DIR:-/var/www/app}"
DOMAIN="${DOMAIN:-example.com}"
PHP_VERSION="${PHP_VERSION:-8.4}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SSL_DIR="/etc/ssl/app"

mkdir -p "$SSL_DIR"

# --- vhost ---------------------------------------------------------------------

NGINX_DEST="/etc/nginx/sites-available/app.conf"
echo "📝 Configuration du vhost nginx (${NGINX_DEST})..."
sed \
    -e "s/__DOMAIN__/${DOMAIN}/g" \
    -e "s#__APP_DIR__#${APP_DIR}#g" \
    -e "s/__PHP_VERSION__/${PHP_VERSION}/g" \
    "$SCRIPT_DIR/app.conf.dist" > "$NGINX_DEST"

ln -sf "$NGINX_DEST" /etc/nginx/sites-enabled/app.conf

if [ ! -f "$SSL_DIR/cert.pem" ] || [ ! -f "$SSL_DIR/key.pem" ]; then
    echo "⚠️  Certificat absent (${SSL_DIR}/cert.pem + key.pem) — vhost écrit mais PAS activé."
    echo "    Dépose ton certificat puis relance ce script."
    exit 1
fi

nginx -t
systemctl reload nginx

echo "✅ Vhost nginx à jour et actif."
