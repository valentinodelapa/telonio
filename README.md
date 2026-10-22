# CommSuite

[![PHP Version Support](https://img.shields.io/badge/php-%3E%3D8.5-blue)](https://www.php.net/)
[![license](https://img.shields.io/badge/license-MIT-yellowgreen)](LICENSE)

Suite gestionale open source multi-studio per studi commerciali e tributari, sviluppata in PHP 8.5 su [SismaFramework](https://github.com/valentinodelapa/SismaFramework).

## Moduli della suite

Ogni applicativo è un modulo `SismaFramework` all'interno di `www/` che condivide l'autenticazione, l'isolamento multi-studio e l'anagrafica unica dei soggetti:

| Modulo | Stato | Descrizione | Documentazione |
|---|---|---|---|
| **`CommSuite`** | Disponibile | Modulo base: gestione studi, utenti, autenticazione, layout e validatori fiscali (Codice Fiscale, Partita IVA, IBAN) | `www/CommSuite/` |
| **`Anagrafica`** | Disponibile | Archivio unico storicizzato dei soggetti dello studio (persone fisiche, ditte individuali, società, enti), dati fiscali, indirizzi, legami/soci, conti correnti, immobili con storico catastale e quote di possesso, tabelle ANPR e ATECO | [`www/Anagrafica/Docs/README.md`](www/Anagrafica/Docs/README.md) |
| **`Deleghe`** | Disponibile | Deleghe di pagamento F24 (Ordinario, Semplificato, ELIDE, Accise): archivio versamenti e crediti in compensazione, controlli formali sulle tabelle dell'Agenzia delle Entrate, ripartizione su più modelli, ravvedimento operoso, sanzioni civili INPS/INAIL, stampa su modello ufficiale SVG e generazione del file telematico Entratel | [`www/Deleghe/Docs/README.md`](www/Deleghe/Docs/README.md) |
| **`Imu`** | Disponibile | Calcolo dell'IMU sugli immobili in anagrafica: importazione dei prospetti comunali delle aliquote dal portale del Dipartimento delle Finanze, gestione caratteristiche e agevolazioni storicizzate, prospetto di calcolo e invio automatico dei versamenti di acconto e saldo all'archivio `Deleghe`, dichiarazione IMU/IMPi e IMU ENC con file telematico | [`www/Imu/Docs/README.md`](www/Imu/Docs/README.md) |
| **`Successioni`** | Disponibile | Dichiarazione di successione e domanda di volture catastali: beneficiari e immobili dall'anagrafica (anche del catasto tavolare), aziende, titoli, navi e aeromobili, quote di legge proposte, liquidazione del quadro EF con ravvedimento e autoliquidazione dell'imposta di successione, F24 dei beneficiari (anche a rate) nell'archivio `Deleghe`, allegati PDF/A, file telematico XML SUC13 validato sullo schema ufficiale, dichiarazione sostitutiva, volture riportate nell'anagrafica | [`www/Successioni/Docs/README.md`](www/Successioni/Docs/README.md) |
| **`Contabilita`** | In arrivo | Contabilità ordinaria, semplificata e forfetaria, registri IVA e cespiti | — |
| **`Dichiarativi`** | In arrivo | Redditi, IVA annuale, 770 e invii telematici | — |
| **`Bilancio`** | In arrivo | Bilancio CEE, nota integrativa e analisi di bilancio | — |
| **`Parcellazione`** | In arrivo | Parcelle, fatture elettroniche e incassi dello studio | — |
| **`GestioneStudio`** | In arrivo | Scadenzario, pratiche, attività e controllo di gestione | — |

## Stack

*   **PHP 8.5-FPM** (`php:8.5-fpm`) + **Nginx** (`nginx:alpine`)
*   **MariaDB** (`mariadb:latest`)
*   **phpMyAdmin** per la gestione del database (solo sviluppo)
*   **Mailpit** come mock SMTP per intercettare la posta in uscita (solo sviluppo)
*   **Traefik** come reverse proxy per lo sviluppo locale

## Prerequisiti

*   Docker e Docker Compose
*   Per lo sviluppo: Traefik in esecuzione sulla rete esterna `web_network` (non richiesto in produzione, vedi "Esposizione in produzione")

## Avvio e installazione (sviluppo)

1.  Clona il repository comprensivo del submodule `SismaFramework`:
    ```bash
    git clone --recurse-submodules <url-repository>
    cd commercial-suite
    ```
2.  Crea il file `.env` a partire da `.env.example` e avvia i container:
    ```bash
    cp .env.example .env
    make start-dev
    ```
3.  Crea le tabelle dei moduli e importa i dati di riferimento (comuni, stati esteri, codici ATECO, tabelle F24 e tassi, coefficienti e comuni esenti IMU, uffici di registrazione):
    ```bash
    docker exec -i comm_suite_db mariadb -uroot -pchange_me_root_password comm_suite < www/CommSuite/Database/schema.sql
    docker exec -i comm_suite_db mariadb -uroot -pchange_me_root_password comm_suite < www/Anagrafica/Database/schema.sql
    docker exec -i comm_suite_db mariadb -uroot -pchange_me_root_password comm_suite < www/Anagrafica/Database/dati-riferimento.sql
    docker exec -i comm_suite_db mariadb -uroot -pchange_me_root_password comm_suite < www/Deleghe/Database/schema.sql
    docker exec -i comm_suite_db mariadb -uroot -pchange_me_root_password comm_suite < www/Deleghe/Database/dati-riferimento.sql
    docker exec -i comm_suite_db mariadb -uroot -pchange_me_root_password comm_suite < www/Imu/Database/schema.sql
    docker exec -i comm_suite_db mariadb -uroot -pchange_me_root_password comm_suite < www/Imu/Database/dati-riferimento.sql
    docker exec -i comm_suite_db mariadb -uroot -pchange_me_root_password comm_suite < www/Successioni/Database/schema.sql
    docker exec -i comm_suite_db mariadb -uroot -pchange_me_root_password comm_suite < www/Successioni/Database/dati-riferimento.sql
    ```
4.  *(Opzionale, solo sviluppo)* Carica i dati demo (studio, utente `admin` / `admin`, soggetti con storico, immobili e deleghe di esempio):
    ```bash
    docker exec comm_suite_php sh -c 'cd /var/www/html && sisma fixtures'
    ```

L'applicazione sarà disponibile su `http://comm-suite.localhost`, phpMyAdmin su `http://db.comm-suite.localhost` e Mailpit su `http://mail.comm-suite.localhost`.

## Comandi CLI (`sisma`)

Il comando `sisma` è disponibile nel container `comm_suite_php` e include i comandi dei moduli per l'aggiornamento delle tabelle di riferimento e dei modelli ufficiali:

```bash
# Carica i dati demo di sviluppo (su tabelle vuote)
docker exec comm_suite_php sh -c 'cd /var/www/html && sisma fixtures'

# Aggiorna comuni e stati esteri (ANPR) e classificazioni ATECO (ISTAT)
docker exec comm_suite_php sh -c 'cd /var/www/html && sisma tabelle-riferimento'

# Aggiorna codici tributo, causali, enti F24 (Agenzia delle Entrate) e tassi BCE
docker exec comm_suite_php sh -c 'cd /var/www/html && sisma tabelle-f24'

# Aggiorna gli sfondi SVG dei modelli F24 ufficiali dai PDF dell'Agenzia delle Entrate
docker exec comm_suite_php sh -c 'cd /var/www/html && sisma modelli-f24'

# Importa i prospetti comunali delle aliquote IMU dal Dipartimento delle Finanze
docker exec comm_suite_php sh -c 'cd /var/www/html && sisma aliquote-imu --anno=2026'

# Aggiorna gli XSD della dichiarazione di successione e gli uffici di registrazione (Agenzia delle Entrate)
docker exec comm_suite_php sh -c 'cd /var/www/html && sisma specifiche-successioni'
```

## Ambienti: sviluppo e produzione

La configurazione Docker è divisa in quattro file:

| File | Contenuto |
|---|---|
| `docker-compose.yml` | Servizi core (`nginx`, `php`, `db`), comuni a ogni ambiente. `db` è dietro il profilo `internal-db` (vedi "Database interno o esterno") |
| `docker-compose.dev.yml` | Aggiunte per lo sviluppo: esposizione di `nginx` via Traefik (`web_network`, dominio `comm-suite.localhost`), `phpmyadmin`, `mailpit` (mock SMTP) |
| `docker-compose.prod.yml` | Aggiunte per la produzione: `restart: unless-stopped` sui servizi core |
| `docker-compose.backup.yml` | Layer opzionale: backup schedulato del database |

Target disponibili nel `Makefile`:

```bash
make start-dev              # docker compose -f docker-compose.yml -f docker-compose.dev.yml --profile internal-db up -d
make stop-dev               # docker compose -f docker-compose.yml -f docker-compose.dev.yml --profile internal-db down
make start-prod             # docker compose -f docker-compose.yml -f docker-compose.prod.yml --profile internal-db up -d
make stop-prod              # docker compose -f docker-compose.yml -f docker-compose.prod.yml --profile internal-db down
make start-prod-external-db # docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
make stop-prod-external-db  # docker compose -f docker-compose.yml -f docker-compose.prod.yml down
make start-backup           # docker compose -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.backup.yml --profile internal-db up -d
make stop-backup            # docker compose -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.backup.yml --profile internal-db down
```

## Credenziali database

Le credenziali (e la passphrase di cifratura) sono lette dal file `.env` (non versionato, copiato da `.env.example`), che `docker-compose.yml` rende disponibile ai container tramite `env_file`.

| Parametro | Valore di default (`.env.example`) |
|---|---|
| Host | `db` |
| Database | `comm_suite` |
| Utente | `comm_suite_user` |
| Password | `change_me_db_password` |
| Password root | `change_me_root_password` |

L'utente applicativo (`comm_suite_user`) ha soli permessi DML (`SELECT`, `INSERT`, `UPDATE`, `DELETE`) sullo schema del progetto (configurati in `comm_suite.sql`); le operazioni di struttura (DDL) vanno eseguite come `root`.

## Database interno o esterno

Il servizio `db` (container MariaDB) è dietro il profilo Compose `internal-db`:

*   **Database interno** (container MariaDB gestito da questo stack): usare i target Make standard (`start-dev`, `start-prod`, `start-backup`), che attivano il profilo con `--profile internal-db`.
*   **Database esterno** (es. RDS, MariaDB/MySQL managed, server dedicato): usare `make start-prod-external-db` / `make stop-prod-external-db`, impostando `DATABASE_HOST`/`DATABASE_PORT` in `.env` con l'endpoint reale.

## Esposizione in produzione

`docker-compose.prod.yml` non pubblica porte sull'host né impone un reverse proxy specifico. A seconda dell'infrastruttura di deploy è possibile:

1.  **Pubblicare la porta direttamente** (`ports: ["80:80"]` su `nginx`), gestendo la terminazione TLS su un proxy/load balancer esterno.
2.  **Agganciarsi al proprio reverse proxy/Traefik di produzione** aggiungendo `networks` e `labels` con il dominio reale in `docker-compose.prod.yml`.

## Configurazione SMTP e limiti PHP

*   **SMTP (`MAIL_*` in `.env`)**: in sviluppo puntano a `mailpit:1025` senza autenticazione; in produzione basta sostituirli con i parametri del provider SMTP reale.
*   **Limiti PHP (`UPLOAD_MAX_FILESIZE`, `POST_MAX_SIZE`, `MEMORY_LIMIT`, `MAX_EXECUTION_TIME`, `MAX_INPUT_TIME`)**: renderizzati all'avvio del container `php` in `/usr/local/etc/php/conf.d/limits.ini` tramite `docker-entrypoint.sh`. `POST_MAX_SIZE` viene allineato automaticamente anche a `client_max_body_size` del container `nginx`.
*   **Backup (`BACKUP_SCHEDULE`, `BACKUP_RETENTION_DAYS`)**: il servizio opzionale `docker-compose.backup.yml` esegue dump periodici compressi di MariaDB in `./backups` (esclusa da Git) con pulizia automatica oltre la retention configurata.

## Licenza e attribuzioni

Rilasciato sotto licenza **MIT** — vedi il file [LICENSE](LICENSE).

Per l'elenco completo delle librerie open source di terze parti e delle fonti istituzionali dei dati pubblici e dei modelli fiscali utilizzati (ANPR, ISTAT, Agenzia delle Entrate, MEF, BCE, Debian `iso-codes`) consulta il file [NOTICE.md](NOTICE.md).
