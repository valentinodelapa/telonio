#!/bin/sh
set -eu

# I dump contengono dati personali e fiscali: leggibili solo dal proprietario
umask 077

TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
DEST="/backups/${DATABASE_NAME}_${TIMESTAMP}.sql.gz"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
REMOTE_RETENTION_DAYS="${BACKUP_REMOTE_RETENTION_DAYS:-90}"

mariadb-dump -h "${DATABASE_HOST}" -u root -p"${DB_ROOT_PASSWORD}" --single-transaction "${DATABASE_NAME}" | gzip > "${DEST}"

find /backups -name "${DATABASE_NAME}_*.sql.gz" -mtime "+${RETENTION_DAYS}" -delete

echo "Backup completato: ${DEST}"

# Copia fuori dall'host: ogni remote rclone elencato in BACKUP_REMOTES (es.
# "onedrive-cifrato: gdrive-cifrato:", configurati in ./rclone/rclone.conf)
# riceve i dump con "rclone copy" (non "sync": i file spariti in locale non
# vengono cancellati in remoto) e conserva quelli degli ultimi
# REMOTE_RETENTION_DAYS giorni. Un remote non raggiungibile non blocca gli
# altri: il dump resta in locale e lo script termina con errore.
FAILED=0
for REMOTE in ${BACKUP_REMOTES:-}; do
    if rclone copy /backups "${REMOTE}" --include "${DATABASE_NAME}_*.sql.gz" \
        && rclone delete "${REMOTE}" --include "${DATABASE_NAME}_*.sql.gz" --min-age "${REMOTE_RETENTION_DAYS}d"; then
        echo "Copia remota completata: ${REMOTE}"
    else
        echo "ERRORE: copia remota non riuscita: ${REMOTE}" >&2
        FAILED=1
    fi
done

exit "${FAILED}"
