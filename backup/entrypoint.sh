#!/bin/sh
set -eu

SCHEDULE="${BACKUP_SCHEDULE:-0 3 * * *}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-7}"

mkdir -p /backups
echo "${SCHEDULE} /usr/local/bin/backup.sh >> /proc/1/fd/1 2>&1" > /etc/crontabs/root

echo "Backup pianificato: '${SCHEDULE}' (retention: ${RETENTION_DAYS} giorni)"
exec crond -f -l 2
