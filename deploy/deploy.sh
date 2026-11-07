#!/usr/bin/env bash
#
# Met en ligne la nouvelle version, lancé par le runner GitHub ou à la main sur le serveur.
# Sauvegarde, construction, migrations, redémarrage, puis retour à l'ancienne version si le site ne répond plus.
#
set -Eeuo pipefail

ENV_FILE="${PHOTOPRIX_ENV_FILE:-/srv/photoprix/.env.prod}"
BACKUP_DIR="${PHOTOPRIX_BACKUP_DIR:-/srv/photoprix/backups}"
BACKUPS_TO_KEEP="${PHOTOPRIX_BACKUPS_TO_KEEP:-14}"
IMAGE="photoprix-app"

cd "$(dirname "$0")/.."

log() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
fail() { printf '\n\033[1;31m[ERREUR] %s\033[0m\n' "$*" >&2; exit 1; }

[[ -f "$ENV_FILE" ]] || fail "Fichier de configuration introuvable : $ENV_FILE (voir deploy/README.md)"
grep -Eq '^[^#].*change-me' "$ENV_FILE" && fail "$ENV_FILE contient encore des valeurs « change-me »"

export PHOTOPRIX_ENV_FILE="$ENV_FILE"
compose() { docker compose -f compose.prod.yaml --env-file "$ENV_FILE" "$@"; }

# Le contrôle final doit se présenter avec le vrai domaine, sinon Symfony le refuse.
APP_HOST="$(sed -nE 's#^DEFAULT_URI="?https?://([^/"]+).*#\1#p' "$ENV_FILE" | head -n1)"
APP_HOST="${APP_HOST:-localhost}"

# --- 1. Sauvegarde de la base (si elle tourne déjà) ------------------------------------------
if compose ps --status running --services 2>/dev/null | grep -qx database; then
    log "Sauvegarde de la base de données"
    mkdir -p "$BACKUP_DIR"
    backup="$BACKUP_DIR/photoprix-$(date +%Y%m%d-%H%M%S).sql.gz"
    compose exec -T database sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines "$MYSQL_DATABASE"' \
        | gzip > "$backup"
    echo "Sauvegarde : $backup"
    # On ne garde que les dernières sauvegardes.
    ls -1t "$BACKUP_DIR"/photoprix-*.sql.gz | tail -n +"$((BACKUPS_TO_KEEP + 1))" | xargs -r rm --
fi

# --- 2. Construction de la nouvelle image -----------------------------------------------------
log "Construction de l'image"
if docker image inspect "$IMAGE:latest" >/dev/null 2>&1; then
    docker tag "$IMAGE:latest" "$IMAGE:previous"
fi
compose build --pull php

rollback() {
    if docker image inspect "$IMAGE:previous" >/dev/null 2>&1; then
        log "Retour à la version précédente"
        docker tag "$IMAGE:previous" "$IMAGE:latest"
        compose up -d --no-build php messenger
    fi
    fail "$1 — les migrations éventuelles ne sont PAS annulées (sauvegarde : ${backup:-aucune})"
}

# --- 3. Services de base puis migrations ------------------------------------------------------
log "Démarrage de la base et de RabbitMQ"
compose up -d --wait database rabbitmq

log "Migrations de la base"
compose run --rm --no-deps php php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration \
    || rollback "Échec des migrations"

# --- 4. Mise en service --------------------------------------------------------------------------
log "Redémarrage de l'application"
compose up -d --no-build --remove-orphans

# Au cas où une photo en attente traînerait encore dans le dossier public.
compose exec -T php php bin/console app:photos:secure-pending --no-interaction >/dev/null

# --- 5. Vérification ------------------------------------------------------------------------------
log "Vérification que le site répond (Host: $APP_HOST)"
for attempt in $(seq 1 30); do
    if compose exec -T php curl -fsS -o /dev/null -H "Host: $APP_HOST" -H "X-Forwarded-Proto: https" http://localhost:8080/; then
        log "Déploiement terminé ✔"
        docker image prune -f >/dev/null
        exit 0
    fi
    sleep 2
done

compose logs --tail=50 php || true
rollback "Le site ne répond pas après le déploiement"
