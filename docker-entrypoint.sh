#!/bin/sh
set -e

# Sisma CLI
chmod +x /var/www/html/SismaFramework/Console/sisma 2>/dev/null || true

# Imposta i permessi sulle cartelle scrivibili
chown -R www-data:www-data /var/www/html/Cache /var/www/html/Logs /var/www/html/filesystemMedia 2>/dev/null || true
chmod -R 775 /var/www/html/Cache /var/www/html/Logs /var/www/html/filesystemMedia 2>/dev/null || true

# Limiti PHP (upload/memoria/tempo) da .env: upload_max_filesize, post_max_size
# e max_input_time sono PHP_INI_PERDIR, quindi vanno scritte in un file ini
# prima dell'avvio (non impostabili a runtime dall'applicativo). Default di
# fallback se la variabile non è definita in .env.
export UPLOAD_MAX_FILESIZE="${UPLOAD_MAX_FILESIZE:-10M}"
export POST_MAX_SIZE="${POST_MAX_SIZE:-12M}"
export MEMORY_LIMIT="${MEMORY_LIMIT:-256M}"
export MAX_EXECUTION_TIME="${MAX_EXECUTION_TIME:-60}"
export MAX_INPUT_TIME="${MAX_INPUT_TIME:-60}"
envsubst '${UPLOAD_MAX_FILESIZE} ${POST_MAX_SIZE} ${MEMORY_LIMIT} ${MAX_EXECUTION_TIME} ${MAX_INPUT_TIME}' \
    < /usr/local/etc/php/conf.d/limits.ini.template \
    > /usr/local/etc/php/conf.d/limits.ini

exec "$@"
