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

# Détecter la meilleure version PHP disponible
PHP_V=""
for v in 8.4 8.3 8.2 8.1; do
    if apt-cache show php${v}-cli &>/dev/null 2>&1; then
        PHP_V=$v
        break
    fi
done
if [ -z "$PHP_V" ]; then
    echo "[ERROR] Aucune version PHP 8.x trouvée dans les dépôts."
    exit 1
fi
log "PHP $PHP_V détecté."

apt install -y \
    php${PHP_V}-cli php${PHP_V}-fpm php${PHP_V}-sqlite3 php${PHP_V}-xml php${PHP_V}-intl php${PHP_V}-mbstring php${PHP_V}-curl \
    nginx git unzip curl

# --- Docker + Gotenberg (génération PDF) -------------------------------------
if ! command -v docker &>/dev/null; then
    log "Installation de Docker…"
    curl -fsSL https://get.docker.com | sh
    systemctl enable docker
    systemctl start docker
fi

if ! docker ps --format '{{.Names}}' | grep -q gotenberg; then
    log "Lancement du conteneur Gotenberg…"
    docker run -d --name gotenberg --restart unless-stopped -p 3000:3000 gotenberg/gotenberg:8
    log "Gotenberg démarré sur le port 3000."
else
    log "Gotenberg déjà en cours d'exécution."
fi

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
GOTENBERG_DSN=http://localhost:3000
WEBHOOK_SECRET=$(openssl rand -hex 20)
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
        fastcgi_pass unix:/run/php/php${PHP_V}-fpm.sock;
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
