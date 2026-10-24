FROM alpine:latest

# rclone: copia cifrata dei dump su OneDrive, Google Drive o altri servizi
# (BACKUP_REMOTES in .env, configurazione in ./rclone/rclone.conf)
RUN apk add --no-cache mariadb-client rclone

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
COPY backup.sh /usr/local/bin/backup.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/backup.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
