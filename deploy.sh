#!/bin/bash
# =============================================================================
# Gestion Commandes — Script de déploiement
# Usage :
#   ./deploy.sh
# =============================================================================

set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
GIT_BRANCH="${GIT_BRANCH:-master}"
BACKUP_DIR="${APP_DIR}/var/backups"
DB_PATH="${APP_DIR}/var/data/gestion_commandes.db"
WEB_USER="${WEB_USER:-www-data}"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log()  { echo -e "${GREEN}[DEPLOY]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
err()  { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

command -v "$PHP_BIN" >/dev/null 2>&1 || err "PHP non trouvé."
command -v "$COMPOSER_BIN" >/dev/null 2>&1 || err "Composer non trouvé."
command -v git >/dev/null 2>&1 || err "Git non trouvé."

cd "$APP_DIR"

if [ ! -d vendor ]; then
  MODE="install"
  log "=== PREMIER DÉPLOIEMENT ==="
else
  MODE="update"
  log "=== MISE À JOUR ==="
fi

mkdir -p "$BACKUP_DIR" "$(dirname "$DB_PATH")" var/cache var/log

if [ "$MODE" = "update" ] && [ -f "$DB_PATH" ]; then
  BACKUP_FILE="${BACKUP_DIR}/gestion_commandes_$(date +%Y%m%d_%H%M%S).db"
  cp "$DB_PATH" "$BACKUP_FILE"
  log "Base sauvegardée → $BACKUP_FILE"
  ls -t "$BACKUP_DIR"/gestion_commandes_*.db 2>/dev/null | tail -n +11 | xargs -r rm
fi

if [ -d .git ]; then
  log "Récupération des dernières modifications (branche: $GIT_BRANCH)…"
  git fetch origin
  git reset --hard "origin/$GIT_BRANCH"
  log "Code à jour ($(git log -1 --format='%h — %s'))"
else
  warn "Pas de dépôt Git détecté."
fi

log "Installation / mise à jour des dépendances Composer…"
"$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction

if [ ! -f .env.local ]; then
  warn ".env.local absent. Pense à le créer avant de continuer."
fi

if [ ! -f "$DB_PATH" ]; then
  log "Création du schéma de la base de données…"
  "$PHP_BIN" bin/console doctrine:schema:create --no-interaction
else
  log "Mise à jour du schéma (si nécessaire)…"
  "$PHP_BIN" bin/console doctrine:schema:update --force --no-interaction || \
    warn "doctrine:schema:update a échoué ou aucune mise à jour n'était nécessaire."
fi

log "Vidage du cache…"
"$PHP_BIN" bin/console cache:clear --env=prod --no-interaction || {
  rm -rf var/cache/*
  warn "Cache vidé manuellement."
}

log "Warmup du cache…"
"$PHP_BIN" bin/console cache:warmup --env=prod --no-interaction || true

log "Réglage des permissions…"
chown -R "$WEB_USER:$WEB_USER" var || warn "Impossible de changer le propriétaire de var/."
chmod -R 775 var

echo ""
log "============================================"
if [ "$MODE" = "install" ]; then
  log " INSTALLATION TERMINÉE"
else
  log " MISE À JOUR TERMINÉE"
fi
log " Base   : $DB_PATH"
log " Commit : $(git log -1 --format='%h — %s' 2>/dev/null || echo 'n/a')"
log "============================================"
echo ""