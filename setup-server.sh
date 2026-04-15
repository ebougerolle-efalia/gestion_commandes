#!/bin/bash
# =============================================================================
# Bougerolle — Configuration initiale du serveur (Debian/Ubuntu)
# À lancer une seule fois sur un serveur vierge.
# Usage : sudo ./setup-server.sh
# =============================================================================

set -e

DOMAIN="${1:-gestion-commandes.bougerolle.ovh}"
APP_DIR="/var/www/gestion_commandes"
REPO_URL="${2:-https://github.com/ebougerolle-efalia/gestion_commandes.git}"

GREEN='\033[0;32m'
NC='\033[0m'
log() { echo -e "${GREEN}[SETUP]${NC} $1"; }

if [ "$EUID" -ne 0 ]; then
    echo "Ce script doit être lancé avec sudo."
    exit 1
fi

# --- 1. Paquets système ------------------------------------------------------
log "Installation des paquets…"
apt update
apt install -y \
    php8.3-cli php8.3-fpm php8.3-sqlite3 php8.3-xml php8.3-intl php8.3-mbstring php8.3-curl \
    nginx git unzip curl

# Installer Composer
if ! command -v composer &>/dev/null; then
    log "Installation de Composer…"
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# --- 2. Cloner le projet -----------------------------------------------------
if [ ! -d "$APP_DIR" ]; then
    log "Clonage du dépôt → $APP_DIR"
    git clone "$REPO_URL" "$APP_DIR"
else
    log "Le dossier $APP_DIR existe déjà."
fi

cd "$APP_DIR"

# --- 3. Environnement --------------------------------------------------------
if [ ! -f ".env.local" ]; then
    log "Création du .env.local…"
    SECRET=$(openssl rand -hex 16)
    cat > .env.local <<EOF
APP_ENV=prod
APP_SECRET=$SECRET
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data/bougerolle.db"
EOF
    log "APP_SECRET généré automatiquement."
fi

# --- 4. Déploiement initial ---------------------------------------------------
log "Lancement du déploiement initial…"
chmod +x deploy.sh
./deploy.sh

# --- 5. Permissions -----------------------------------------------------------
chown -R www-data:www-data var/
chmod -R 775 var/

# --- 6. Configuration Nginx ---------------------------------------------------
log "Configuration Nginx pour $DOMAIN…"
cat > /etc/nginx/sites-available/gestion_commandes <<NGINX
server {
    listen 80;
    server_name $DOMAIN;
    root $APP_DIR/public;
    index index.php;

    # Logs
    access_log /var/log/nginx/gestion_commandes_access.log;
    error_log  /var/log/nginx/gestion_commandes_error.log;

    # Taille max upload (pour les backups JSON)
    client_max_body_size 20M;

    location / {
        try_files \$uri /index.php\$is_args\$args;
    }

    location ~ ^/index\\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        internal;
    }

    # Bloquer l'accès aux fichiers sensibles
    location ~ /\\. { deny all; }
    location ~ /(var|config|src|vendor|bin|templates)/ { deny all; }
}
NGINX

ln -sf /etc/nginx/sites-available/gestion_commandes /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

nginx -t && systemctl reload nginx
log "Nginx configuré pour $DOMAIN."

# --- 7. HTTPS (obligatoire) ---------------------------------------------------
log "Installation de Certbot et activation HTTPS…"
apt install -y certbot python3-certbot-nginx

certbot --nginx -d $DOMAIN --non-interactive --agree-tos --email admin@bougerolle.ovh --redirect || {
    warn "Certbot a échoué. Vérifiez que le DNS de $DOMAIN pointe vers ce serveur."
    warn "Relancez manuellement : certbot --nginx -d $DOMAIN"
}

# Renouvellement auto (cron déjà installé par certbot)
log "HTTPS configuré. Renouvellement automatique activé."

# --- 8. Webhook sudoers -------------------------------------------------------
log "Configuration sudoers pour le webhook…"
echo "www-data ALL=(ALL) NOPASSWD: $APP_DIR/deploy.sh" > /etc/sudoers.d/gestion_commandes
chmod 440 /etc/sudoers.d/gestion_commandes

echo ""
log "============================================"
log " SERVEUR CONFIGURÉ"
log " URL : https://$DOMAIN"
log " App : $APP_DIR"
log " Repo : $REPO_URL (branche master)"
log ""
log " Webhook GitHub :"
log "   Payload URL : https://$DOMAIN/webhook.php"
log "   Secret : celui de WEBHOOK_SECRET dans .env.local"
log "============================================"
echo ""
