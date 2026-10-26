#!/bin/sh
# Inizializzazione del database, eseguita dall'immagine MariaDB solo alla
# creazione del volume (docker-entrypoint-initdb.d). Schema e utente vengono da
# .env (DATABASE_NAME, DATABASE_USERNAME, passati come MARIADB_DATABASE e
# MARIADB_USER), così lo script vale per qualsiasi nome.
#
# L'entrypoint dell'immagine assegna all'utente GRANT ALL sullo schema prima di
# eseguire questo file, registrato col nome dello schema escapato (`_` → `\_`):
# una REVOKE ... ON <schema>.* non lo troverebbe. Si revoca quindi tutto a ogni
# livello e si riassegnano le sole operazioni DML; le operazioni di struttura
# (DDL) restano a root.
set -e

mariadb --protocol=socket -uroot -p"$MARIADB_ROOT_PASSWORD" <<SQL
ALTER DATABASE \`$MARIADB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
REVOKE ALL PRIVILEGES, GRANT OPTION FROM '$MARIADB_USER'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON \`$MARIADB_DATABASE\`.* TO '$MARIADB_USER'@'%';
FLUSH PRIVILEGES;
SQL
