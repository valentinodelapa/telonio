# Telonio

[![PHP Version Support](https://img.shields.io/badge/php-%3E%3D8.5-blue)](https://www.php.net/)
[![license](https://img.shields.io/badge/license-MIT-yellowgreen)](LICENSE)

Suite gestionale open source multi-studio per studi commerciali e tributari, sviluppata in PHP 8.5 su [SismaFramework](https://github.com/valentinodelapa/SismaFramework).

## Moduli della suite

Ogni applicativo è un modulo `SismaFramework` all'interno di `www/` che condivide l'autenticazione, l'isolamento multi-studio e l'anagrafica unica dei soggetti:

| Modulo | Stato | Descrizione | Documentazione |
|---|---|---|---|
| **`Telonio`** | Disponibile | Modulo base: gestione studi (CLI e web), utenti e ruoli (`amministratore`, `operatore`), profilo e password, autenticazione, layout e validatori fiscali (Codice Fiscale, Partita IVA, IBAN) | [`www/Telonio/Docs/README.md`](www/Telonio/Docs/README.md) |
| **`Anagrafica`** | Disponibile | Archivio unico storicizzato dei soggetti dello studio (persone fisiche, ditte individuali, società, enti), dati fiscali e regime fiscale (ordinario, semplificato, forfetario, periodicità IVA, ISA, sostituto d'imposta), indirizzi, legami/soci, conti correnti, immobili con storico catastale, identificativi graffati e quote di possesso, tabelle ANPR e ATECO | [`www/Anagrafica/Docs/README.md`](www/Anagrafica/Docs/README.md) |
| **`Deleghe`** | Disponibile | Deleghe di pagamento F24 (Ordinario, Semplificato, ELIDE, Accise): archivio versamenti e crediti in compensazione, controlli formali sulle tabelle dell'Agenzia delle Entrate, ripartizione su più modelli, ravvedimento operoso, sanzioni civili INPS/INAIL, stampa su modello ufficiale SVG e generazione del file telematico Entratel | [`www/Deleghe/Docs/README.md`](www/Deleghe/Docs/README.md) |
| **`Imu`** | Disponibile | Calcolo dell'IMU sugli immobili in anagrafica: importazione dei prospetti comunali delle aliquote dal portale del Dipartimento delle Finanze, gestione caratteristiche e agevolazioni storicizzate, prospetto di calcolo e invio automatico dei versamenti di acconto e saldo all'archivio `Deleghe`, dichiarazione IMU/IMPi e IMU ENC con file telematico | [`www/Imu/Docs/README.md`](www/Imu/Docs/README.md) |
| **`Successioni`** | Disponibile | Dichiarazione di successione e domanda di volture catastali: beneficiari (anche trust testamentario ed eredità giacente) e immobili dall'anagrafica (anche del catasto tavolare, graffati o all'estero), aziende, titoli, navi e aeromobili, quote di legge proposte, liquidazione del quadro EF con ravvedimento e autoliquidazione dell'imposta di successione, F24 dei beneficiari (anche a rate) nell'archivio `Deleghe`, allegati PDF/A, file telematico XML SUC13 validato sullo schema ufficiale, dichiarazione sostitutiva, volture riportate nell'anagrafica | [`www/Successioni/Docs/README.md`](www/Successioni/Docs/README.md) |
| **`GestioneStudio`** | Disponibile | Scadenzario dello studio con le scadenze di tutti gli applicativi (adempimenti dei clienti, versamenti F24, dichiarazioni IMU e di successione) per periodo, stato e operatore; calendario fiscale degli adempimenti con termini, proroghe (anche riservate ai soggetti ISA), differimento di agosto e slittamento al primo giorno lavorativo; adempimenti dei clienti storicizzati con responsabile, proposti in base all'anagrafica e al regime fiscale; stato di lavorazione e note di ogni scadenza | [`www/GestioneStudio/Docs/README.md`](www/GestioneStudio/Docs/README.md) |
| **`Contabilita`** | In arrivo | Contabilità ordinaria, semplificata e forfetaria, registri IVA e cespiti | — |
| **`Dichiarativi`** | In arrivo | Redditi, IVA annuale, 770 e invii telematici | — |
| **`Bilancio`** | In arrivo | Bilancio CEE, nota integrativa e analisi di bilancio | — |
| **`Parcellazione`** | In arrivo | Parcelle, fatture elettroniche e incassi dello studio | — |

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
    cd telonio
    ```
2.  Crea il file `.env` a partire da `.env.example` e avvia i container:
    ```bash
    cp .env.example .env
    make start-dev
    ```
3.  Crea le tabelle dei moduli e importa i dati di riferimento (comuni, stati esteri, codici ATECO, tabelle F24 e tassi, coefficienti e comuni esenti IMU, uffici di registrazione, calendario fiscale degli adempimenti):
    ```bash
    docker exec -i telonio_db mariadb -uroot -pchange_me_root_password telonio < www/Telonio/Database/schema.sql
    docker exec -i telonio_db mariadb -uroot -pchange_me_root_password telonio < www/Anagrafica/Database/schema.sql
    docker exec -i telonio_db mariadb -uroot -pchange_me_root_password telonio < www/Anagrafica/Database/dati-riferimento.sql
    docker exec -i telonio_db mariadb -uroot -pchange_me_root_password telonio < www/Deleghe/Database/schema.sql
    docker exec -i telonio_db mariadb -uroot -pchange_me_root_password telonio < www/Deleghe/Database/dati-riferimento.sql
    docker exec -i telonio_db mariadb -uroot -pchange_me_root_password telonio < www/Imu/Database/schema.sql
    docker exec -i telonio_db mariadb -uroot -pchange_me_root_password telonio < www/Imu/Database/dati-riferimento.sql
    docker exec -i telonio_db mariadb -uroot -pchange_me_root_password telonio < www/Successioni/Database/schema.sql
    docker exec -i telonio_db mariadb -uroot -pchange_me_root_password telonio < www/Successioni/Database/dati-riferimento.sql
    docker exec -i telonio_db mariadb -uroot -pchange_me_root_password telonio < www/GestioneStudio/Database/schema.sql
    docker exec -i telonio_db mariadb -uroot -pchange_me_root_password telonio < www/GestioneStudio/Database/dati-riferimento.sql
    ```
4.  Crea uno studio e il primo utente amministratore (oppure, in sviluppo, carica i dati demo con utente `admin` / `admin`, soggetti con storico, immobili, deleghe, calcoli IMU e successione di esempio):
    ```bash
    # Creazione di uno studio (interattiva o con opzioni, vedi www/Telonio/Docs/README.md)
    docker exec -it telonio_php sh -c 'cd /var/www/html && sisma studio crea'

    # Oppure, solo sviluppo su tabelle vuote: carica i dati demo
    docker exec telonio_php sh -c 'cd /var/www/html && sisma fixtures'
    ```

L'applicazione sarà disponibile su `http://telonio.localhost`, phpMyAdmin su `http://db.telonio.localhost` e Mailpit su `http://mail.telonio.localhost`.

## Comandi CLI (`sisma`)

Il comando `sisma` è disponibile nel container `telonio_php` e include i comandi dei moduli per la gestione degli studi e l'aggiornamento delle tabelle di riferimento e dei modelli ufficiali:

```bash
# Elenca gli studi e i relativi utenti, oppure crea/gestisce uno studio e i suoi amministratori
docker exec telonio_php sh -c 'cd /var/www/html && sisma studio'
docker exec -it telonio_php sh -c 'cd /var/www/html && sisma studio crea'

# Carica i dati demo di sviluppo (su tabelle vuote)
docker exec telonio_php sh -c 'cd /var/www/html && sisma fixtures'

# Aggiorna comuni e stati esteri (ANPR) e classificazioni ATECO (ISTAT)
docker exec telonio_php sh -c 'cd /var/www/html && sisma tabelle-riferimento'

# Aggiorna codici tributo, causali, enti F24 (Agenzia delle Entrate) e tassi BCE
docker exec telonio_php sh -c 'cd /var/www/html && sisma tabelle-f24'

# Aggiorna gli sfondi SVG dei modelli F24 ufficiali dai PDF dell'Agenzia delle Entrate
docker exec telonio_php sh -c 'cd /var/www/html && sisma modelli-f24'

# Importa i prospetti comunali delle aliquote IMU dal Dipartimento delle Finanze
docker exec telonio_php sh -c 'cd /var/www/html && sisma aliquote-imu --anno=2026'

# Aggiorna gli XSD della dichiarazione di successione e gli uffici di registrazione (Agenzia delle Entrate)
docker exec telonio_php sh -c 'cd /var/www/html && sisma specifiche-successioni'

# Aggiorna il calendario fiscale degli adempimenti (termini e proroghe di legge) dalle sorgenti del modulo
docker exec telonio_php sh -c 'cd /var/www/html && sisma calendario-fiscale'
```

## Ambienti: sviluppo e produzione

La configurazione Docker è divisa in quattro file:

| File | Contenuto |
|---|---|
| `docker-compose.yml` | Servizi core (`nginx`, `php`, `db`), comuni a ogni ambiente. `db` è dietro il profilo `internal-db` (vedi "Database interno o esterno") |
| `docker-compose.dev.yml` | Aggiunte per lo sviluppo: immagine `php` con Xdebug e pcov (stage `dev` del `Dockerfile`), esposizione di `nginx` via Traefik (`web_network`, dominio `telonio.localhost`), `phpmyadmin`, `mailpit` (mock SMTP) |
| `docker-compose.prod.yml` | Aggiunte per la produzione: `restart: unless-stopped` sui servizi core, `nginx` pubblicato su `HTTP_BIND:HTTP_PORT` (default `127.0.0.1:8080`) |
| `docker-compose.backup.yml` | Layer opzionale: backup schedulato del database |

Il `Dockerfile` è multi-stage: `base` contiene l'applicativo, `dev` aggiunge Xdebug e pcov, `prod` (ultimo stage, costruito se non si indica un target) è la sola base. Compose non ricostruisce l'immagine quando cambia lo stage: passando da sviluppo a produzione sulla stessa macchina, o dopo modifiche al `Dockerfile`, avviare una volta con `--build` (es. `docker compose -f docker-compose.yml -f docker-compose.dev.yml --profile internal-db up -d --build`).

La modalità dell'applicativo si imposta in `.env`: `DEVELOPMENT_ENVIRONMENT=true` in sviluppo (errori dettagliati, log estesi), `false` in produzione; `HTTPS_IS_FORCED=true` in produzione dietro un reverse proxy HTTPS (vedi "Self-hosting"). Senza le variabili valgono entrambe `false`.

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
| Database | `telonio` |
| Utente | `telonio_user` |
| Password | `change_me_db_password` |
| Password root | `change_me_root_password` |

L'utente applicativo (`telonio_user`) ha soli permessi DML (`SELECT`, `INSERT`, `UPDATE`, `DELETE`) sullo schema del progetto (configurati in `db-init.sh`, eseguito alla creazione del database); le operazioni di struttura (DDL) vanno eseguite come `root`.

## Database interno o esterno

Il servizio `db` (container MariaDB) è dietro il profilo Compose `internal-db`:

*   **Database interno** (container MariaDB gestito da questo stack): usare i target Make standard (`start-dev`, `start-prod`, `start-backup`), che attivano il profilo con `--profile internal-db`.
*   **Database esterno** (es. RDS, MariaDB/MySQL managed, server dedicato): usare `make start-prod-external-db` / `make stop-prod-external-db`, impostando `DATABASE_HOST`/`DATABASE_PORT` in `.env` con l'endpoint reale.

## Self-hosting

La configurazione di produzione è pensata per un'installazione privata, raggiungibile solo dai propri dispositivi: l'applicativo tratta dati personali e fiscali e non va esposto su internet. `nginx` è pubblicato solo su `127.0.0.1:8080` dell'host; un reverse proxy sulla stessa macchina termina il TLS e assegna l'indirizzo web. L'indirizzo non dipende dai nomi dei container: il framework costruisce gli URL dall'host della richiesta e `nginx` accetta qualsiasi nome.

### Host e installazione

Su una macchina sempre accesa (mini PC, NAS, server o VPS) con Docker, **fuori da cartelle sincronizzate** (OneDrive, Dropbox):

1.  Scarica il codice: clone con `git clone --recurse-submodules` (poi `composer install --working-dir=www --no-dev`) oppure l'archivio di una release, che contiene già framework e dipendenze.
2.  Crea il `.env` da `.env.example` con password e passphrase nuove (es. `openssl rand -base64 32`), `DEVELOPMENT_ENVIRONMENT=false`, `HTTPS_IS_FORCED=true` e i parametri SMTP reali.
3.  Avvia lo stack con il backup: `make start-backup`.
4.  Crea le tabelle eseguendo come `root` gli script `schema.sql` e `dati-riferimento.sql` dei moduli, come al passo 3 di "Avvio e installazione" (con `docker exec -i telonio_db ...`).
5.  Crea lo studio e il primo amministratore: `docker exec -it telonio_php sh -c 'cd /var/www/html && sisma studio crea'`. **Non** caricare le fixture (`sisma fixtures`), che creano l'utente demo `admin` / `admin`.

Per una seconda istanza sulla stessa macchina dello sviluppo usare una cartella separata con `CONTAINER_PREFIX` diverso in `.env` (es. `telonio_prod`): nome di progetto Compose, volume del database e container restano distinti. In produzione fissare in `.env` anche `COMPOSE_PROJECT_NAME` (es. `telonio-prod`): il volume del database prende il nome del progetto, che altrimenti è quello della cartella, e un aggiornamento estratto in un'altra cartella partirebbe da un database vuoto.

### Indirizzo web con Tailscale

[Tailscale](https://tailscale.com/) crea una rete privata tra i propri dispositivi, senza aprire porte sul router:

1.  Installa Tailscale sull'host e su ogni PC da cui accedere, con lo stesso account.
2.  Nella console di amministrazione attiva **MagicDNS** e **HTTPS Certificates** (DNS → HTTPS Certificates) e, se vuoi, rinomina la macchina (es. `telonio`).
3.  Sull'host: `tailscale serve --bg 8080`.

L'applicativo è raggiungibile su `https://telonio.<nome-tailnet>.ts.net`, con certificato valido, solo dai dispositivi della propria rete Tailscale.

Per un nome del proprio dominio (es. `telonio.studio.it`) servono un record DNS che punti all'IP Tailscale dell'host (`100.x.y.z`, raggiungibile solo dalla rete privata) e un reverse proxy che ottenga il certificato con la verifica DNS (es. Caddy con il plugin del proprio provider DNS) e inoltri a `127.0.0.1:8080`.

### Backup e ripristino

Il servizio di backup esegue un dump compresso del database ogni notte (`BACKUP_SCHEDULE`) in `./backups`, con i file più vecchi di `BACKUP_RETENTION_DAYS` (default 30) eliminati. La copia locale protegge da errori e aggiornamenti non riusciti, non da guasto, furto o ransomware della macchina; per questo servono anche:

*   una **copia remota cifrata** (vedi sotto);
*   il **`.env` in un luogo sicuro** (es. password manager): senza `ENCRYPTION_PASSPHRASE` e le credenziali un backup non basta a ripristinare l'installazione;
*   una **prova periodica di ripristino** su un database temporaneo:

```bash
docker exec telonio_db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e "CREATE DATABASE telonio_ripristino"'
gunzip -c backups/telonio_AAAAMMGG_HHMMSS.sql.gz | docker exec -i telonio_db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" telonio_ripristino'
```

Per il ripristino effettivo si importa il dump nel database `telonio` (svuotato) con lo stesso comando.

### Copia remota cifrata (OneDrive, Google Drive)

Dopo ogni dump il servizio di backup copia i file con [rclone](https://rclone.org/) su ciascun remote elencato in `BACKUP_REMOTES` (`.env`, separati da spazio). I remote sono di tipo `crypt`: contenuto e nomi dei file sono cifrati prima dell'invio, il servizio cloud vede solo dati illeggibili. Si possono configurare più servizi e scegliere in qualsiasi momento quali usare cambiando solo `.env`:

```bash
BACKUP_REMOTES=onedrive-cifrato:                  # solo OneDrive
BACKUP_REMOTES=gdrive-cifrato:                    # solo Google Drive
BACKUP_REMOTES=onedrive-cifrato: gdrive-cifrato:  # entrambi
BACKUP_REMOTES=                                   # solo copia locale
```

Funzionamento:

*   la copia usa `rclone copy`, non `sync`: i dump spariti in locale (guasto, cancellazione, ransomware) non vengono cancellati in remoto;
*   in remoto si conservano i dump degli ultimi `BACKUP_REMOTE_RETENTION_DAYS` giorni (default 90), indipendentemente dalla retention locale;
*   un remote non raggiungibile non blocca gli altri: il dump resta in locale e l'errore compare nel log del container (`docker logs telonio_backup`).

La configurazione di rclone sta in `./rclone/rclone.conf` (esclusa da Git): contiene i token di accesso e le password di cifratura, va protetta come il `.env`. Dopo una modifica a `BACKUP_REMOTES` riavviare il servizio (`make start-backup`).

#### Configurazione (una volta)

L'accesso a OneDrive e Google Drive richiede un login dal browser, che sull'host spesso non c'è: il token si ottiene su un PC con [rclone installato](https://rclone.org/install/) (su Windows `winget install Rclone.Rclone`) e si incolla nella configurazione sull'host. Il token si rinnova da solo.

1.  Avvia il servizio di backup (`make start-backup`) e apri la configurazione guidata nel container: `docker exec -it telonio_backup rclone config`.
2.  **OneDrive**: `n` (nuovo remote), nome `onedrive`, tipo `onedrive`, `client_id` e `client_secret` vuoti, regione `global`. Alla domanda sul browser rispondi `n`: rclone mostra il comando da eseguire sul PC (`rclone authorize "onedrive"`), che apre il browser per l'accesso e stampa il token da incollare. Scegli poi "OneDrive Personal or Business" e il drive proposto.
3.  **Google Drive**: nome `gdrive`, tipo `drive`. È consigliato un `client_id` proprio ([istruzioni](https://rclone.org/drive/#making-your-own-client-id)): quello condiviso di rclone ha limiti di traffico. Come `scope` scegli `drive.file` (rclone vede solo i file che crea lui). Il token si ottiene come per OneDrive, con il comando `rclone authorize "drive" ...` mostrato da rclone.
4.  **Remote cifrati**, uno per servizio: nome `onedrive-cifrato`, tipo `crypt`, `remote` = `onedrive:Backup/Telonio`, cifratura dei nomi `standard`, cifratura delle cartelle `true`, password e salt (`password2`) scelti da te (`y`, poi digitati). Stesso procedimento per `gdrive-cifrato` con `remote` = `gdrive:Backup/Telonio`.
5.  **Conserva password e salt di ogni remote cifrato nel password manager**: senza, e senza `rclone.conf`, i dump remoti non sono recuperabili da nessuno, nemmeno da te.
6.  Imposta `BACKUP_REMOTES` in `.env`, riavvia con `make start-backup` e prova subito: `docker exec telonio_backup backup.sh` deve terminare con `Copia remota completata` per ogni remote.

#### Ripristino da remoto

```bash
docker exec telonio_backup rclone ls onedrive-cifrato:
docker exec telonio_backup rclone copy onedrive-cifrato:telonio_AAAAMMGG_HHMMSS.sql.gz /backups/
```

Il file arriva già decifrato in `./backups` e si importa come sopra. Su una macchina nuova basta rclone con un `rclone.conf` che abbia gli stessi remote (token di accesso nuovo, stesse password e salt).

### Aggiornamento

Prima di aggiornare eseguire un backup (`docker exec telonio_backup backup.sh`), poi aggiornare il codice (nuova release o `git pull` su un tag; se la nuova versione va in un'altra cartella, copiarvi `.env`, `backups/` e `rclone/` e verificare che `.env` contenga `COMPOSE_PROJECT_NAME`), applicare gli eventuali script di migrazione indicati nel `CHANGELOG.md` e ricostruire con `docker compose -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.backup.yml --profile internal-db up -d --build`. L'immagine di MariaDB è fissata a una versione (`docker-compose.yml`): passare alla successiva a mano, dopo un backup (MariaDB non supporta il ritorno a una versione precedente).

## Configurazione SMTP e limiti PHP

*   **SMTP (`MAIL_*` in `.env`)**: in sviluppo puntano a `mailpit:1025` senza autenticazione; in produzione basta sostituirli con i parametri del provider SMTP reale.
*   **Limiti PHP (`UPLOAD_MAX_FILESIZE`, `POST_MAX_SIZE`, `MEMORY_LIMIT`, `MAX_EXECUTION_TIME`, `MAX_INPUT_TIME`)**: renderizzati all'avvio del container `php` in `/usr/local/etc/php/conf.d/limits.ini` tramite `docker-entrypoint.sh`. `POST_MAX_SIZE` viene allineato automaticamente anche a `client_max_body_size` del container `nginx`.
*   **Backup (`BACKUP_SCHEDULE`, `BACKUP_RETENTION_DAYS`)**: il servizio opzionale `docker-compose.backup.yml` esegue dump periodici compressi di MariaDB in `./backups` (esclusa da Git) con pulizia automatica oltre la retention configurata (vedi "Backup e ripristino").

## Licenza e attribuzioni

Rilasciato sotto licenza **MIT** — vedi il file [LICENSE](LICENSE).

Per l'elenco completo delle librerie open source di terze parti e delle fonti istituzionali dei dati pubblici e dei modelli fiscali utilizzati (ANPR, ISTAT, Agenzia delle Entrate, MEF, BCE, Ufficio delle pubblicazioni dell'UE) consulta il file [NOTICE.md](NOTICE.md).
