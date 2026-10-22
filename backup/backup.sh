#!/bin/sh
set -eu

TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
DEST="/backups/${DATABASE_NAME}_${TIMESTAMP}.sql.gz"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-7}"

mariadb-dump -h "${DATABASE_HOST}" -u root -p"${DB_ROOT_PASSWORD}" --single-transaction "${DATABASE_NAME}" | gzip > "${DEST}"

find /backups -name "${DATABASE_NAME}_*.sql.gz" -mtime "+${RETENTION_DAYS}" -delete

echo "Backup completato: ${DEST}"
