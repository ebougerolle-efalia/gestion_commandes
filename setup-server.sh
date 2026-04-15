#!/bin/bash
# =============================================================================
# Gestion Commandes — Configuration initiale du serveur Debian 13
# Usage :
#   ./setup-server.sh [domaine] [repo_git]
#
# Exemple :
#   ./setup-server.sh gestion-commandes.bougerolle.ovh https://github.com/ebougerolle-efalia/gestion_commandes.git
# =============================================================================

set -euo pipefail

DOMAIN="${1:-gestion-commandes.bougerolle.ovh}"
APP_DIR="/var/www/gestion_commandes"
REPO_URL="${2:-https://github.com/ebougerolle-efalia/gestion_commandes.git}"
NGINX_SITE="gestion_commandes"
WEB_USER="www-data"
PHP_FPM_SOCK=""
DB_PATH="${APP_DIR}/var/data/gestion_commandes.db"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log()  { echo -e "${GREEN}[SETUP]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
err()  { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

if [ "${EUID}" -ne 0 ]; then
  err "Ce script doit être lancé en root."
fi

if [ ! -f /etc/debian_version ]; then
  err "Ce script est prévu pour Debian."
fi

log "Mise à jour des paquets..."
apt update

log "Installation des dépendances système..."
apt install -y \
  nginx \
  git \
  unzip \
  curl \
  ca-certificates \
  gnupg \
  lsb-release \
  acl \
  certbot \
  python3-certbot-nginx \
  composer \
  sqlite3 \
  openssl \
  php-cli \
  php-fpm \
  php-sqlite3 \
  php-xml \
  php-intl \
  php-mbstring \
  php-curl \
  php-zip

log "Détection de PHP-FPM..."
if [ -S /run/php/php-fpm.sock ]; then
  PHP_FPM_SOCK="/run/php/php-fpm.sock"
else
  PHP_FPM_SOCK="$(find /run/php -maxdepth 1 -type s -name 'php*.sock' | head -n 1 || true)"
fi

if [ -z "${PHP_FPM_SOCK}" ]; then
  err "Impossible de trouver la socket PHP-FPM dans /run/php."
fi

log "Socket PHP-FPM détectée : ${PHP_FPM_SOCK}"

log "Activation et démarrage des services..."
systemctl enable nginx
systemctl restart nginx
systemctl enable php*-fpm 2>/dev/null || true
systemctl restart php*-fpm 2>/dev/null || true

if [ ! -d "${APP_DIR}" ]; then
  log "Clonage du dépôt dans ${APP_DIR}..."
  git clone "${REPO_URL}" "${APP_DIR}"
else
  log "Le dossier ${APP_DIR} existe déjà, clonage ignoré."
fi

cd "${APP_DIR}"

if [ -f "deploy.sh" ]; then
  chmod +x deploy.sh
elif [ -f "deploy-2.sh" ]; then
  log "Le dépôt contient deploy-2.sh, copie vers deploy.sh..."
  cp deploy-2.sh deploy.sh
  chmod +x deploy.sh
else
  warn "Aucun deploy.sh trouvé dans le projet."
fi

if [ ! -f ".env.local" ]; then
  log "Création du fichier .env.local..."
  APP_SECRET="$(openssl rand -hex 16)"
  WEBHOOK_SECRET="$(openssl rand -hex 24)"

  cat > .env.local <<EOF
APP_ENV=prod
APP_SECRET=${APP_SECRET}

###> doctrine ###
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data/gestion_commandes.db"
###< doctrine ###

WEBHOOK_SECRET=${WEBHOOK_SECRET}
EOF

  chmod 640 .env.local
  chown root:${WEB_USER} .env.local || true
else
  log ".env.local déjà présent, création ignorée."
fi

log "Création des dossiers applicatifs..."
mkdir -p var/cache var/log var/data var/backups
touch "${DB_PATH}" 2>/dev/null || true
chown -R ${WEB_USER}:${WEB_USER} var || true
chmod -R 775 var

log "Configuration Nginx..."
cat > /etc/nginx/sites-available/${NGINX_SITE} <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};

    root ${APP_DIR}/public;
    index index.php;
    client_max_body_size 20M;

    location /.well-known/acme-challenge/ {
        root /var/www/html;
    }

    location / {
        try_files \$uri /index.php\$is_args\$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:${PHP_FPM_SOCK};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        include fastcgi_params;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    location ~ /\. {
        deny all;
    }

    location ~ /(var|config|src|vendor|bin|templates)/ {
        deny all;
    }
}
EOF

ln -sf /etc/nginx/sites-available/${NGINX_SITE} /etc/nginx/sites-enabled/${NGINX_SITE}
rm -f /etc/nginx/sites-enabled/default

nginx -t
systemctl reload nginx

if [ -f "./deploy.sh" ]; then
  log "Lancement du déploiement applicatif..."
  ./deploy.sh || warn "Le déploiement a échoué. Vérifie le contenu de deploy.sh."
else
  warn "deploy.sh absent, déploiement non lancé."
fi

log "Configuration sudoers pour le webhook PHP/Nginx..."
cat > /etc/sudoers.d/gestion_commandes <<EOF
www-data ALL=(root) NOPASSWD: ${APP_DIR}/deploy.sh
EOF
chmod 440 /etc/sudoers.d/gestion_commandes

log "Génération du certificat Let's Encrypt..."
if certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos --register-unsafely-without-email --redirect; then
  log "Certificat SSL installé avec succès."
else
  warn "Échec de Certbot. Vérifie que le domaine pointe bien sur ce serveur."
fi

echo ""
log "============================================"
log " SERVEUR CONFIGURÉ"
log " Domaine       : https://${DOMAIN}"
log " Application   : ${APP_DIR}"
log " Dépôt         : ${REPO_URL}"
log " Base SQLite   : ${DB_PATH}"
log " Socket PHP    : ${PHP_FPM_SOCK}"
log ""
log " Webhook GitHub :"
log " Payload URL   : https://${DOMAIN}/webhook.php"
log " Secret        : voir WEBHOOK_SECRET dans ${APP_DIR}/.env.local"
log "============================================"
echo ""