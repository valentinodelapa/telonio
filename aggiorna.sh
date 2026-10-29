#!/bin/sh
# Aggiornamento di un'installazione di Telonio all'ultima versione del canale
# configurato (CANALE_AGGIORNAMENTI in .env), da lanciare nella cartella
# dell'installazione:
#
#   sh aggiorna.sh                 controlla, chiede conferma e aggiorna
#   sh aggiorna.sh --controlla     mostra solo se c'è un aggiornamento
#   sh aggiorna.sh --si            senza conferma (es. da cron)
#   sh aggiorna.sh --canale=beta   canale diverso da quello configurato
#   sh aggiorna.sh --archivio=FILE installa un archivio zip già scaricato
#   sh aggiorna.sh --senza-backup  con database esterno (backup a cura propria)
#
# Passi: scarica l'archivio della release e ne verifica l'impronta SHA-256
# (e la provenienza SLSA se slsa-verifier è installato), esegue il dump del
# database, ferma php e nginx, sposta il codice attuale in
# .aggiornamenti/telonio-<versione> (.env, backups/, rclone/,
# www/filesystemMedia e www/Logs restano al loro posto), mette al suo posto
# quello nuovo, ricostruisce e riavvia i container, aggiorna il database
# (sisma aggiorna-database) e controlla che l'applicazione risponda. Se un
# passo fallisce rimette il codice precedente e ripristina il dump.
#
# Per aggiornare servono i permessi di scrittura sulla cartella
# dell'installazione (se appartiene a root: sudo sh aggiorna.sh). La
# chiusura della sessione (es. SSH) non interrompe l'aggiornamento; i
# messaggi e l'output di Docker sono anche in .aggiornamenti/aggiornamento.log.
#
# Richiede docker compose, curl, unzip e sha256sum (Debian: apt install curl unzip).

set -eu

# La chiusura del terminale non deve interrompere l'aggiornamento a metà.
trap '' HUP

# Durante l'aggiornamento questo file viene spostato e sostituito: lo script
# prosegue da una copia temporanea.
if [ -z "${TELONIO_AGGIORNA_CARTELLA:-}" ]; then
    TELONIO_AGGIORNA_CARTELLA=$(cd "$(dirname "$0")" && pwd)
    export TELONIO_AGGIORNA_CARTELLA
    COPIA=$(mktemp)
    cp "$0" "$COPIA"
    exec sh "$COPIA" "$@"
fi
CARTELLA=$TELONIO_AGGIORNA_CARTELLA
trap 'rm -f "$0"' EXIT
cd "$CARTELLA"

LAVORO=.aggiornamenti
BLOCCO="$LAVORO/in-corso"
REGISTRO=""
SORGENTE_RELEASE=github.com/valentinodelapa/telonio

# I messaggi vanno anche nel registro (attivo dopo l'acquisizione del blocco);
# un terminale non più disponibile non deve fermare lo script.
registra() { [ -z "$REGISTRO" ] || printf '%s %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" >> "$REGISTRO" 2>/dev/null || true; }
messaggio() { registra "$*"; printf '%s\n' "$*" 2>/dev/null || true; }
errore() { registra "Errore: $*"; printf 'Errore: %s\n' "$*" >&2 || true; exit 1; }

CONTROLLA=0; CONFERMA=1; CANALE=""; ARCHIVIO_LOCALE=""; BACKUP=1
for opzione in "$@"; do
    case "$opzione" in
        --controlla) CONTROLLA=1 ;;
        --si) CONFERMA=0 ;;
        --canale=*) CANALE=${opzione#--canale=} ;;
        --archivio=*) ARCHIVIO_LOCALE=${opzione#--archivio=} ;;
        --senza-backup) BACKUP=0 ;;
        --help|-h) sed -n '2,28p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) errore "opzione non riconosciuta: $opzione (sh aggiorna.sh --help)" ;;
    esac
done

# --- Controlli preliminari ---
[ -f .env ] || errore "manca .env: lanciare lo script nella cartella dell'installazione"
[ -f www/composer.json ] || errore "manca www/composer.json: non sembra un'installazione di Telonio"
[ -e .git ] && errore "installazione da git: aggiornare con git pull su un tag e sisma aggiorna-database"
for comando in docker curl unzip sha256sum gzip; do
    command -v "$comando" >/dev/null 2>&1 || errore "comando $comando non trovato"
done
docker compose version >/dev/null 2>&1 || errore "serve Docker Compose v2 (docker compose)"

leggi_env() { sed -n "s/^$1=//p" .env | tail -n 1 | tr -d '\r'; }
versione_di() { sed -n 's/.*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$1" | head -n 1; }

# File e profili Compose: quelli di .env (COMPOSE_FILE, COMPOSE_PROFILES) o,
# se assenti, produzione con database interno e servizio di backup.
if [ -z "$(leggi_env COMPOSE_FILE)" ]; then
    COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml
    [ -f docker-compose.backup.yml ] && COMPOSE_FILE=$COMPOSE_FILE:docker-compose.backup.yml
    COMPOSE_PATH_SEPARATOR=:
    export COMPOSE_FILE COMPOSE_PATH_SEPARATOR
fi
if [ -z "$(leggi_env COMPOSE_PROFILES)" ]; then
    COMPOSE_PROFILES=internal-db
    export COMPOSE_PROFILES
fi
dc() { docker compose "$@"; }
servizio_attivo() { dc ps --status running --services 2>/dev/null | grep -qx "$1"; }
sisma() { dc exec -T php sh -c "cd /var/www/html && sisma $*"; }

INSTALLATA=$(versione_di www/composer.json)

# --- Versione da installare ---
if [ -n "$ARCHIVIO_LOCALE" ]; then
    [ -f "$ARCHIVIO_LOCALE" ] || errore "archivio non trovato: $ARCHIVIO_LOCALE"
    VERSIONE=$(unzip -p "$ARCHIVIO_LOCALE" www/composer.json | sed -n 's/.*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -n 1)
    [ -n "$VERSIONE" ] || errore "l'archivio non contiene www/composer.json"
    TAG="v$VERSIONE"; SHA256=""; ATTESTAZIONE=""; NOTE=""
    messaggio "Versione installata: $INSTALLATA"
    messaggio "Archivio: $ARCHIVIO_LOCALE (versione $VERSIONE)"
    [ "$VERSIONE" != "$INSTALLATA" ] || errore "la versione dell'archivio è già installata"
else
    servizio_attivo php || errore "il servizio php non è avviato: avviare l'installazione prima di aggiornarla"
    if [ -n "$CANALE" ]; then
        INFO=$(sisma aggiornamenti --formato=shell "--canale=$CANALE") || errore "controllo degli aggiornamenti non riuscito: $INFO"
    else
        INFO=$(sisma aggiornamenti --formato=shell) || errore "controllo degli aggiornamenti non riuscito: $INFO"
    fi
    valore() { printf '%s\n' "$INFO" | sed -n "s/^$1=//p" | tr -d '\r'; }
    VERSIONE=$(valore VERSIONE); TAG=$(valore TAG); SHA256=$(valore SHA256)
    ATTESTAZIONE=$(valore ATTESTAZIONE); NOTE=$(valore NOTE)
    messaggio "Versione installata: $INSTALLATA (canale $(valore CANALE))"
    if [ -z "$VERSIONE" ]; then
        messaggio "Nessun aggiornamento disponibile."
        exit 0
    fi
    messaggio "Disponibile: $VERSIONE"
    messaggio "Note di rilascio: $NOTE"
fi
[ "$CONTROLLA" -eq 1 ] && exit 0

# --- Permessi: il codice da spostare deve essere scrivibile ---
for percorso in . www "$LAVORO" ./*/ www/*/; do
    [ -e "$percorso" ] || continue
    case "$(basename "$percorso")" in backups|rclone|filesystemMedia|Logs) continue ;; esac
    [ -w "$percorso" ] || errore "permessi insufficienti su $percorso: lanciare lo script con sudo (o come proprietario della cartella dell'installazione)"
done

if [ "$CONFERMA" -eq 1 ]; then
    printf 'Aggiornare a %s? Le note di rilascio indicano eventuali passi manuali. [s/N] ' "$VERSIONE"
    read -r risposta < /dev/tty || risposta=""
    case "$risposta" in s|S|si|SI|sì|Si) ;; *) messaggio "Aggiornamento annullato."; exit 0 ;; esac
fi

# --- Blocco: un solo aggiornamento alla volta ---
# Il blocco contiene il numero del processo; quello di un processo non più
# attivo è rimasto da un aggiornamento interrotto e si rimuove, a meno che
# l'interruzione sia avvenuta durante la sostituzione del codice.
mkdir -p "$LAVORO"
BLOCCO_RIMOSSO=""
if ! mkdir "$BLOCCO" 2>/dev/null; then
    [ -d "$BLOCCO" ] || errore "impossibile creare $BLOCCO: $(mkdir "$BLOCCO" 2>&1 || true)"
    PROCESSO=$(cat "$BLOCCO/processo" 2>/dev/null || true)
    if [ -n "$PROCESSO" ] && kill -0 "$PROCESSO" 2>/dev/null; then
        errore "un altro aggiornamento è in corso (processo $PROCESSO)"
    fi
    if [ -f "$BLOCCO/sostituzione" ]; then
        errore "un aggiornamento si è interrotto durante la sostituzione del codice: il codice della versione precedente è in $(cat "$BLOCCO/sostituzione"). Controllare l'installazione ($LAVORO/aggiornamento.log), poi eliminare $BLOCCO e riprovare"
    fi
    BLOCCO_RIMOSSO="processo $PROCESSO non più attivo"
    rm -rf "$BLOCCO"
    mkdir "$BLOCCO"
fi
printf '%s\n' "$$" > "$BLOCCO/processo"
trap 'rm -rf "$CARTELLA/$BLOCCO"; rm -f "$0"' EXIT
REGISTRO="$LAVORO/aggiornamento.log"
registra "--- Aggiornamento da $INSTALLATA a $VERSIONE ---"
messaggio "Registro dell'aggiornamento: $REGISTRO"
[ -z "$BLOCCO_RIMOSSO" ] || messaggio "Rimosso il blocco di un aggiornamento interrotto prima della sostituzione del codice ($BLOCCO_RIMOSSO)."

# --- Download e verifica ---
if [ -n "$ARCHIVIO_LOCALE" ]; then
    ARCHIVIO=$ARCHIVIO_LOCALE
    messaggio "Impronta SHA-256 dell'archivio: $(sha256sum "$ARCHIVIO" | cut -d ' ' -f 1)"
else
    mkdir -p "$LAVORO/scaricati"
    ARCHIVIO="$LAVORO/scaricati/telonio-$TAG.zip"
    messaggio "Download di $TAG..."
    curl -fsSL --retry 3 -o "$ARCHIVIO" "$(valore ARCHIVIO)" || errore "download non riuscito"
    printf '%s  %s\n' "$SHA256" "$ARCHIVIO" | sha256sum -c - >/dev/null 2>&1 \
        || { rm -f "$ARCHIVIO"; errore "l'impronta SHA-256 dell'archivio non corrisponde a quella pubblicata su GitHub"; }
    messaggio "Impronta SHA-256 verificata."
    if command -v slsa-verifier >/dev/null 2>&1 && [ -n "$ATTESTAZIONE" ]; then
        curl -fsSL --retry 3 -o "$ARCHIVIO.intoto.jsonl" "$ATTESTAZIONE" || errore "download dell'attestazione non riuscito"
        slsa-verifier verify-artifact "$ARCHIVIO" --provenance-path "$ARCHIVIO.intoto.jsonl" \
            --source-uri "$SORGENTE_RELEASE" --source-tag "$TAG" >/dev/null 2>&1 \
            || errore "verifica della provenienza SLSA non riuscita"
        messaggio "Provenienza SLSA verificata."
    else
        messaggio "Provenienza SLSA non verificata (installare slsa-verifier per verificarla)."
    fi
fi

NUOVA="$LAVORO/nuova-$VERSIONE"
rm -rf "$NUOVA"
unzip -q "$ARCHIVIO" -d "$NUOVA" || errore "archivio non valido"
[ "$(versione_di "$NUOVA/www/composer.json")" = "$VERSIONE" ] && [ -f "$NUOVA/docker-compose.yml" ] && [ -d "$NUOVA/www/Telonio" ] \
    || { rm -rf "$NUOVA"; errore "l'archivio non contiene un'installazione di Telonio $VERSIONE"; }

# --- Backup del database ---
DUMP=""
if [ "$BACKUP" -eq 1 ]; then
    servizio_attivo db || errore "il servizio db non è avviato: con un database esterno fare il backup e usare --senza-backup"
    mkdir -p backups
    DUMP="backups/$(leggi_env DATABASE_NAME)_$(date +%Y%m%d_%H%M%S)_prima-di-$VERSIONE.sql.gz"
    messaggio "Backup del database in $DUMP..."
    dc exec -T db sh -c 'mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction --routines --events "$MARIADB_DATABASE"' 2>> "$REGISTRO" | gzip > "$DUMP"
    gzip -cd "$DUMP" | tail -n 1 | grep -q 'Dump completed' || { rm -f "$DUMP"; errore "backup del database non riuscito"; }
fi

# --- Sostituzione del codice ---
# Sposta il codice da una cartella all'altra lasciando dove sono i dati
# dell'installazione: .env, backups/, rclone/, .aggiornamenti/,
# www/filesystemMedia (documenti caricati) e www/Logs.
sposta_codice() {
    da=$1; a=$2
    mkdir -p "$a/www"
    for voce in "$da"/* "$da"/.[!.]* "$da"/..?*; do
        [ -e "$voce" ] || continue
        case "$(basename "$voce")" in
            .env.example) ;;
            .env|.env.*|backups|rclone|.aggiornamenti|www) continue ;;
        esac
        mv "$voce" "$a/"
    done
    [ -d "$da/www" ] || return 0
    for voce in "$da"/www/* "$da"/www/.[!.]* "$da"/www/..?*; do
        [ -e "$voce" ] || continue
        case "$(basename "$voce")" in filesystemMedia|Logs) continue ;; esac
        mv "$voce" "$a/www/"
    done
}

PRECEDENTE="$LAVORO/telonio-$INSTALLATA"
[ -e "$PRECEDENTE" ] && PRECEDENTE="$PRECEDENTE-$(date +%Y%m%d%H%M%S)"
CODICE_SOSTITUITO=0

ripristina() {
    trap - INT TERM
    set +e
    messaggio ""
    messaggio "Aggiornamento non riuscito: ripristino della versione $INSTALLATA..."
    dc stop php nginx >> "$REGISTRO" 2>&1
    if [ "$CODICE_SOSTITUITO" -eq 1 ]; then
        sposta_codice . "$LAVORO/non-riuscita-$VERSIONE-$(date +%Y%m%d%H%M%S)"
        sposta_codice "$PRECEDENTE" .
        rm -rf "$PRECEDENTE"
    fi
    if [ -n "$DUMP" ] && servizio_attivo db; then
        messaggio "Ripristino del database da $DUMP..."
        dc exec -T db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e "DROP DATABASE IF EXISTS \`$MARIADB_DATABASE\`; CREATE DATABASE \`$MARIADB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"' >> "$REGISTRO" 2>&1
        gzip -cd "$DUMP" | dc exec -T db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' >> "$REGISTRO" 2>&1
    fi
    rm -f www/Cache/referenceCache.json
    dc up -d --build --remove-orphans >> "$REGISTRO" 2>&1
    messaggio "Ripristinata la versione $INSTALLATA. Il codice della versione non riuscita è in $LAVORO/, i dettagli in $REGISTRO."
    exit 1
}

messaggio "Arresto dell'applicazione..."
dc stop php nginx >> "$REGISTRO" 2>&1
trap 'ripristina' INT TERM
printf '%s\n' "$PRECEDENTE" > "$BLOCCO/sostituzione"
mkdir -p "$PRECEDENTE"
sposta_codice . "$PRECEDENTE"
CODICE_SOSTITUITO=1
sposta_codice "$NUOVA" . || ripristina
rm -rf "$NUOVA"
mkdir -p www/Cache www/Logs www/filesystemMedia

messaggio "Avvio della versione $VERSIONE (ricostruzione delle immagini)..."
dc up -d --build --remove-orphans >> "$REGISTRO" 2>&1 || ripristina

messaggio "Aggiornamento del database..."
USCITA=$(mktemp)
if sisma aggiorna-database > "$USCITA" 2>&1; then ESITO=0; else ESITO=1; fi
cat "$USCITA" >> "$REGISTRO" 2>/dev/null || true
cat "$USCITA" 2>/dev/null || true
rm -f "$USCITA"
[ "$ESITO" -eq 0 ] || ripristina

# --- Verifica ---
INDIRIZZO=$(leggi_env HTTP_BIND); INDIRIZZO=${INDIRIZZO:-127.0.0.1}
[ "$INDIRIZZO" = "0.0.0.0" ] && INDIRIZZO=127.0.0.1
PORTA=$(leggi_env HTTP_PORT); PORTA=${PORTA:-8080}
messaggio "Verifica dell'applicazione su http://$INDIRIZZO:$PORTA..."
tentativi=0
until [ "$(curl -s -o /dev/null -w '%{http_code}' "http://$INDIRIZZO:$PORTA/auth/login" || true)" = "200" ]; do
    tentativi=$((tentativi + 1))
    [ "$tentativi" -ge 30 ] && ripristina
    sleep 2
done
trap - INT TERM

# --- Pulizia: restano le due versioni precedenti e gli ultimi due archivi ---
ls -1dt "$LAVORO"/telonio-* 2>/dev/null | tail -n +3 | while read -r vecchia; do rm -rf "$vecchia"; done
ls -1t "$LAVORO"/scaricati/*.zip 2>/dev/null | tail -n +3 | while read -r vecchio; do rm -f "$vecchio" "$vecchio.intoto.jsonl"; done

messaggio ""
messaggio "Telonio aggiornato a $VERSIONE."
messaggio "Versione precedente in $PRECEDENTE, backup del database in ${DUMP:-(nessuno)}."
