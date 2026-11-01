#!/bin/sh
set -eu

SCHEDULE="${BACKUP_SCHEDULE:-0 3 * * *}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"

mkdir -p /backups

# BACKUP_SCHEDULE=off (o none/false, senza distinzione di maiuscole) lascia il
# crontab vuoto: il backup si esegue solo su richiesta (docker exec ... backup.sh)
case "$(printf '%s' "${SCHEDULE}" | tr '[:upper:]' '[:lower:]')" in
    off|none|false)
        : > /etc/crontabs/root
        echo "Backup pianificato: disattivato (solo su richiesta con 'backup.sh'; retention: ${RETENTION_DAYS} giorni; copie remote: ${BACKUP_REMOTES:-nessuna})"
        ;;
    *)
        echo "${SCHEDULE} /usr/local/bin/backup.sh >> /proc/1/fd/1 2>&1" > /etc/crontabs/root
        echo "Backup pianificato: '${SCHEDULE}' (retention: ${RETENTION_DAYS} giorni; copie remote: ${BACKUP_REMOTES:-nessuna})"
        ;;
esac

exec crond -f -l 2
