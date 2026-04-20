#!/bin/bash
set -e

TITLE="--- Configuration -----------------------------------------------------------"
APPDIR="$(cd "$(dirname "$0")" && pwd)"
PHPBIN="${PHPBIN:-php}"
COMPOSERBIN="${COMPOSERBIN:-composer}"
GITBRANCH="${GITBRANCH:-master}"
BACKUPDIR="$APPDIR/var/backups"
DBPATH="$APPDIR/var/data/bougerolle.db"
WEBUSER="${WEBUSER:-www-data}"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'
log(){ echo -e "${GREEN}[DEPLOY]${NC} $1"; }
warn(){ echo -e "${YELLOW}[WARN]${NC} $1"; }
err(){ echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

command -v "$PHPBIN" >/dev/null 2>&1 || err "PHP non trouvé. Installez PHP 8.4 ou définissez PHPBIN."
command -v "$COMPOSERBIN" >/dev/null 2>&1 || err "Composer non trouvé. Installez-le ou définissez COMPOSERBIN."
command -v git >/dev/null 2>&1 || err "Git non trouvé."
cd "$APPDIR"

echo "$TITLE"

if ! "$PHPBIN" -r 'exit(version_compare(PHP_VERSION, "8.4.0", ">=") ? 0 : 1);'; then
  err "PHP 8.4+ requis. Version détectée: $($PHPBIN -r 'echo PHP_VERSION;')"
fi

if [ ! -d vendor ]; then
  MODE="install"
  log "PREMIER DÉPLOIEMENT"
else
  MODE="update"
  log "MISE À JOUR"
fi

echo "--- Sauvegarde avant mise à jour --------------------------------------------"
if [ "$MODE" = "update" ] && [ -f "$DBPATH" ]; then
  mkdir -p "$BACKUPDIR"
  BACKUPFILE="$BACKUPDIR/bougerolle-$(date +%Y%m%d-%H%M%S).db"
  cp "$DBPATH" "$BACKUPFILE"
  log "Base sauvegardée : $BACKUPFILE"
  ls -t "$BACKUPDIR"/bougerolle-*.db 2>/dev/null | tail -n +11 | xargs -r rm -f
fi

echo "--- Git pull ------------------------------------------------------------------"
if [ -d .git ]; then
  log "Récupération des dernières modifications (branche: $GITBRANCH)…"
  git fetch origin
  git reset --hard "origin/$GITBRANCH"
  log "Code à jour ($(git log -1 --format='%h — %s'))"
else
  warn "Pas de dépôt Git détecté. Le code doit être mis à jour manuellement."
fi

echo "--- Composer ------------------------------------------------------------------"
"$COMPOSERBIN" config allow-plugins.symfony/runtime true --no-interaction >/dev/null 2>&1 || true
log "Installation des dépendances Composer…"
"$COMPOSERBIN" install --no-dev --optimize-autoloader --no-interaction --prefer-dist

mkdir -p "$(dirname "$DBPATH")"

echo "--- Base de données -----------------------------------------------------------"
NEED_CREATE=0
if [ ! -f "$DBPATH" ] || [ ! -s "$DBPATH" ]; then
  NEED_CREATE=1
elif command -v sqlite3 >/dev/null 2>&1; then
  TABLE_COUNT=$(sqlite3 "$DBPATH" "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';" 2>/dev/null || echo 0)
  if [ "${TABLE_COUNT:-0}" -eq 0 ] 2>/dev/null; then
    NEED_CREATE=1
  fi
fi

if [ "$NEED_CREATE" -eq 1 ]; then
  log "Création du schéma de la base de données…"
  "$PHPBIN" bin/console doctrine:schema:create --no-interaction
  log "Seed initial utilisateur admin…"
  "$PHPBIN" bin/console app:seed --no-interaction 2>/dev/null || true
else
  log "Base existante détectée : mise à jour du schéma si nécessaire…"
  "$PHPBIN" bin/console doctrine:schema:update --force --no-interaction 2>/dev/null \
    || warn "doctrine:schema:update a échoué ; la base est peut-être déjà à jour."
fi

echo "--- Cache ---------------------------------------------------------------------"
log "Vidage du cache…"
"$PHPBIN" bin/console cache:clear --env=prod --no-interaction 2>/dev/null || true
rm -rf var/cache
log "Cache vidé manuellement."
"$PHPBIN" bin/console cache:warmup --env=prod --no-interaction 2>/dev/null || true

echo "--- Permissions ---------------------------------------------------------------"
log "Réglage des permissions…"
mkdir -p var/cache var/log var/data
if id "$WEBUSER" >/dev/null 2>&1; then
  chown -R "$WEBUSER":"$WEBUSER" var 2>/dev/null || warn "Impossible de changer le propriétaire de var. Lancez avec sudo si nécessaire."
fi
chmod -R 775 var

echo
if [ "$MODE" = "install" ]; then
  log "INSTALLATION TERMINÉE"
  log "Base : $DBPATH"
else
  log "MISE À JOUR TERMINÉE"
  log "Commit : $(git log -1 --format='%h — %s' 2>/dev/null || echo 'n/a')"
  [ -n "${BACKUPFILE:-}" ] && log "Backup : $BACKUPFILE"
fi