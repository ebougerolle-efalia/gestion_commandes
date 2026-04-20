#!/bin/bash
# =============================================================================
# Bougerolle — Script de déploiement
# Usage :
#   Premier déploiement : ./deploy.sh
#   Mise à jour :         ./deploy.sh
#   Le script détecte automatiquement s'il s'agit d'une installation ou d'un update.
# =============================================================================

set -e

# --- Configuration -----------------------------------------------------------
APP_DIR="$(cd "$(dirname "$0")" && pwd)"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
GIT_BRANCH="${GIT_BRANCH:-master}"
BACKUP_DIR="${APP_DIR}/var/backups"
DB_PATH="${APP_DIR}/var/data/bougerolle.db"
WEB_USER="${WEB_USER:-www-data}"

# Couleurs
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log()  { echo -e "${GREEN}[DEPLOY]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC}  $1"; }
err()  { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

# --- Vérifications -----------------------------------------------------------
command -v $PHP_BIN >/dev/null 2>&1    || err "PHP non trouvé. Installez PHP 8.1+ ou définissez PHP_BIN."
command -v $COMPOSER_BIN >/dev/null 2>&1 || err "Composer non trouvé. Installez-le ou définissez COMPOSER_BIN."
command -v git >/dev/null 2>&1          || err "Git non trouvé."

cd "$APP_DIR"

# --- Détection premier déploiement vs mise à jour ----------------------------
if [ ! -d "vendor" ]; then
    MODE="install"
    log "=== PREMIER DÉPLOIEMENT ==="
else
    MODE="update"
    log "=== MISE À JOUR ==="
fi

# --- Sauvegarde avant mise à jour --------------------------------------------
if [ "$MODE" = "update" ] && [ -f "$DB_PATH" ]; then
    mkdir -p "$BACKUP_DIR"
    BACKUP_FILE="${BACKUP_DIR}/bougerolle_$(date +%Y%m%d_%H%M%S).db"
    cp "$DB_PATH" "$BACKUP_FILE"
    log "Base sauvegardée → $BACKUP_FILE"

    # Garder les 10 dernières sauvegardes
    ls -t "$BACKUP_DIR"/bougerolle_*.db 2>/dev/null | tail -n +11 | xargs -r rm
fi

# --- Git pull ----------------------------------------------------------------
if [ -d ".git" ]; then
    log "Récupération des dernières modifications (branche: $GIT_BRANCH)…"
    git fetch origin
    git reset --hard "origin/$GIT_BRANCH"
    log "Code à jour ($(git log -1 --format='%h — %s'))"
else
    warn "Pas de dépôt Git détecté. Le code doit être mis à jour manuellement."
fi

# --- Composer ----------------------------------------------------------------
$COMPOSER_BIN config allow-plugins.symfony/runtime true --no-interaction 2>/dev/null
log "Installation / mise à jour des dépendances Composer…"
$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || {
    warn "Lock file obsolète — lancement de composer update…"
    $COMPOSER_BIN update --no-dev --optimize-autoloader --no-interaction
}

# --- Base de données ---------------------------------------------------------
mkdir -p "$(dirname "$DB_PATH")"

if [ ! -f "$DB_PATH" ]; then
    log "Création du schéma de la base de données…"
    $PHP_BIN bin/console doctrine:schema:create --no-interaction
    log "Seed initial (utilisateur admin)…"
    $PHP_BIN bin/console app:seed --no-interaction 2>/dev/null || true
else
    log "Mise à jour du schéma (si nécessaire)…"
    $PHP_BIN bin/console doctrine:schema:update --force --no-interaction 2>/dev/null || {
        warn "doctrine:schema:update a échoué — la base est peut-être déjà à jour."
    }
fi

# --- Cache -------------------------------------------------------------------
log "Vidage du cache…"
$PHP_BIN bin/console cache:clear --env=prod --no-interaction 2>/dev/null || {
    rm -rf var/cache/*
    log "Cache vidé manuellement."
}

# Warmup
$PHP_BIN bin/console cache:warmup --env=prod --no-interaction 2>/dev/null || true

# --- Permissions -------------------------------------------------------------
log "Réglage des permissions…"
mkdir -p var/cache var/log var/data

# Détecter si www-data existe
if id "$WEB_USER" &>/dev/null; then
    chown -R "$WEB_USER:$WEB_USER" var/ 2>/dev/null || {
        warn "Impossible de changer le propriétaire de var/. Lancez avec sudo si nécessaire."
    }
fi

chmod -R 775 var/

# --- Résumé ------------------------------------------------------------------
echo ""
log "============================================"
if [ "$MODE" = "install" ]; then
    log " INSTALLATION TERMINÉE"
    log " Base : $DB_PATH"
    log ""
    log " Prochaines étapes :"
    log "   1. Configurer le serveur web (Nginx/Apache)"
    log "   2. Créer le .env.local avec APP_SECRET"
    log "   3. Accéder à l'application et importer un backup"
else
    log " MISE À JOUR TERMINÉE"
    log " Commit : $(git log -1 --format='%h — %s' 2>/dev/null || echo 'n/a')"
    log " Backup : $BACKUP_FILE"
fi
log "============================================"
echo ""
