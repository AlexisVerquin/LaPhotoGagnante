#!/usr/bin/env bash
#
# Sauvegarde la base de données et tous les fichiers envoyés (photos publiées, photos en attente, avatars).
# Lancé chaque nuit par le cron, et avant chaque déploiement par deploy.sh.
#
# Restauration : voir deploy/README.md, section « Restaurer une sauvegarde ».
#
set -Eeuo pipefail

PROJECT="${LAPHOTOGAGNANTE_PROJECT:-laphotogagnante-prod}"
BACKUP_DIR="${LAPHOTOGAGNANTE_BACKUP_DIR:-/srv/laphotogagnante/backups}"
# Les photos pèsent bien plus lourd que la base : on en garde moins d'exemplaires
DB_TO_KEEP="${LAPHOTOGAGNANTE_BACKUPS_TO_KEEP:-14}"
FILES_TO_KEEP="${LAPHOTOGAGNANTE_FILE_BACKUPS_TO_KEEP:-7}"

fail() { printf '[ERREUR] %s\n' "$*" >&2; exit 1; }

# Les conteneurs sont retrouvés par leurs étiquettes Docker Compose : pas besoin du dépôt pour lancer ce script
container() {
    docker ps -q --filter "label=com.docker.compose.project=$PROJECT" --filter "label=com.docker.compose.service=$1" | head -n1
}

database="$(container database)"
php="$(container php)"
[[ -n "$database" && -n "$php" ]] || fail "Les conteneurs du projet « $PROJECT » ne tournent pas."

mkdir -p "$BACKUP_DIR"
stamp="$(date +%Y%m%d-%H%M%S)"
db_file="$BACKUP_DIR/laphotogagnante-$stamp.sql.gz"
files_file="$BACKUP_DIR/fichiers-$stamp.tar"

# Écriture dans un fichier temporaire, renommé seulement si tout s'est bien passé
docker exec "$database" sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines "$MYSQL_DATABASE"' \
    | gzip > "$db_file.tmp"
mv "$db_file.tmp" "$db_file"

# Les photos sont déjà compressées (JPEG, WebP) : un simple tar suffit
docker exec "$php" tar cf - -C /app public/uploads storage > "$files_file.tmp"
mv "$files_file.tmp" "$files_file"

prune() {
    ls -1t "$BACKUP_DIR"/$1 2>/dev/null | tail -n +"$(($2 + 1))" | xargs -r rm --
}
prune 'laphotogagnante-*.sql.gz' "$DB_TO_KEEP"
prune 'fichiers-*.tar' "$FILES_TO_KEEP"

echo "Sauvegarde : $db_file ($(du -h "$db_file" | cut -f1)), $files_file ($(du -h "$files_file" | cut -f1))"
