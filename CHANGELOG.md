# Changelog

All notable changes to this project will be documented in this file.

## [0.11.0-alpha] - 2026-10-29 - Versioni nell'Interfaccia e Aggiornamento più Robusto

Undicesima versione alpha: la versione installata della suite e dei moduli è visibile nell'interfaccia; lo script di aggiornamento segnala i permessi insufficienti, riconosce i blocchi lasciati dagli aggiornamenti interrotti e non si ferma se la sessione si chiude. Valgono le avvertenze della 0.1.0-alpha: la versione non è indicata per l'uso in produzione su dati reali.

Versioni dei moduli: `Telonio` 0.6.0; `Anagrafica` 0.5.1, `Deleghe` 0.3.0, `Imu` 0.3.2, `Successioni` 0.3.2 e `GestioneStudio` 0.1.1 invariati.

### ✨ Nuove Funzionalità

#### `Telonio`

- **versione installata nell'interfaccia**: nel piè di pagina di ogni pagina la versione della suite (`www/composer.json`) e, nelle pagine di un applicativo, quella del suo modulo (`module.json`); in scrivania ogni applicativo disponibile riporta la propria versione (`HelperClasses\Versioni`, `ModuloSuite::getVersione()`).

### 🐛 Correzioni

#### Aggiornamento dell'installazione (`aggiorna.sh`)

- **permessi insufficienti segnalati come aggiornamento in corso**: senza i permessi di scrittura sulla cartella dell'installazione (es. `/opt/telonio` di root lanciato senza `sudo`) la creazione del blocco falliva e lo script rispondeva "un altro aggiornamento è in corso". Ora, prima di modificare qualcosa, verifica i permessi sulle cartelle da spostare e indica di usare `sudo`; "aggiornamento in corso" solo se il blocco esiste davvero, altrimenti l'errore effettivo. Il solo controllo (`--controlla`) non richiede più di poter scrivere nella cartella;
- **blocco rimasto dopo un'interruzione**: il blocco `.aggiornamenti/in-corso` contiene il numero del processo; quello di un processo non più attivo viene rimosso da solo. Se l'interruzione è avvenuta durante la sostituzione del codice, lo script si ferma e indica dove si trova il codice della versione precedente, da controllare prima di eliminare il blocco;
- **chiusura della sessione durante l'aggiornamento**: la chiusura del terminale (es. disconnessione SSH) terminava lo script anche a metà della sostituzione del codice, senza il ritorno alla versione precedente. Ora la chiusura è ignorata, un terminale non più disponibile non interrompe lo script e messaggi, output di Docker e di `sisma aggiorna-database` sono anche in `.aggiornamenti/aggiornamento.log`.

### 🔄 Aggiornamento da 0.10.1-alpha

Nessuna modifica al database: `sh aggiorna.sh` (con `sudo` se la cartella dell'installazione appartiene a root). Lo script in uso è quello della versione installata: le correzioni valgono dal prossimo aggiornamento.

## [0.10.1-alpha] - 2026-10-29 - File Telematico delle Successioni e Allegati TIFF

Versione di correzione: in `Successioni` il file telematico si scarica con il nome e l'estensione `.suc` del software dell'Agenzia e il controllo degli allegati TIFF riguarda tutte le pagine e non si interrompe sui file danneggiati. Valgono le avvertenze della 0.1.0-alpha: la versione non è indicata per l'uso in produzione su dati reali.

Versioni dei moduli: `Successioni` 0.3.2; `Telonio` 0.5.0, `Anagrafica` 0.5.1, `Deleghe` 0.3.0, `Imu` 0.3.2 e `GestioneStudio` 0.1.1 invariati.

### 🐛 Correzioni

#### `Successioni`

- **nome del file telematico**: il file si scarica come `<codice fiscale del defunto>_SUC13.suc`, il nome e l'estensione con cui lo salva il software di compilazione dell'Agenzia e che si apre con il modulo di controllo di Desktop Telematico (prima `SUC13_<codice fiscale>_<id>.xml`); il contenuto, il documento XML delle specifiche SUC13, non cambia.
- **allegati TIFF di più pagine**: compressione CCITT Group IV, bianco e nero e risoluzione fino a 300 DPI si controllano su tutte le pagine, non solo sulla prima.
- **allegati TIFF danneggiati o troncati**: il caricamento si fermava con un errore dell'applicazione; ora sono rifiutati con il messaggio "Il TIFF non è leggibile".

### 🔄 Aggiornamento da 0.10.0-alpha

Nessuna modifica al database: `sh aggiorna.sh`.

## [0.10.0-alpha] - 2026-10-29 - Aggiornamento Automatico

Decima versione alpha: l'installazione si aggiorna con un comando (`sh aggiorna.sh`), che scarica e verifica la release del canale scelto, esegue il backup, sostituisce il codice, aggiorna il database e, se qualcosa non va, torna alla versione precedente. Il database si porta alla versione del codice con `sisma aggiorna-database`, che sostituisce gli script SQL da eseguire a mano, anche nella prima installazione. Gli amministratori vedono in scrivania l'avviso delle nuove versioni. Valgono le avvertenze della 0.1.0-alpha: la versione non è indicata per l'uso in produzione su dati reali.

Versioni dei moduli: `Telonio` 0.5.0; `Anagrafica` 0.5.1, `Deleghe` 0.3.0, `Imu` 0.3.2, `Successioni` 0.3.1 e `GestioneStudio` 0.1.1 invariati.

### ✨ Nuove Funzionalità

#### Aggiornamento dell'installazione (`aggiorna.sh`)

- **`sh aggiorna.sh`** (o `make aggiorna`) nella cartella dell'installazione: cerca su GitHub l'ultima release del canale, scarica l'archivio e ne verifica l'impronta SHA-256 pubblicata da GitHub (e la provenienza SLSA se è installato `slsa-verifier`), esegue il dump del database in `backups/`, ferma `php` e `nginx`, sposta il codice attuale in `.aggiornamenti/telonio-<versione>` lasciando al loro posto `.env`, `backups/`, `rclone/`, `www/filesystemMedia` e `www/Logs`, mette al suo posto quello nuovo, ricostruisce e riavvia i container, aggiorna il database e controlla che l'applicazione risponda;
- **ritorno automatico alla versione precedente** se un passo fallisce: codice precedente rimesso al suo posto e database ripristinato dal dump; la versione non riuscita resta in `.aggiornamenti/` per l'analisi. Restano le due versioni precedenti e gli ultimi due archivi;
- opzioni `--controlla`, `--si` (senza conferma), `--canale=`, `--archivio=FILE` (archivio già scaricato), `--senza-backup` (database esterno);
- **canali** (`CANALE_AGGIORNAMENTI` in `.env`: `alpha`, `beta`, `rc`, `stabile`; ogni canale riceve anche le versioni più stabili; senza la variabile, il canale della versione installata);
- file e profili di Compose da `COMPOSE_FILE` e `COMPOSE_PROFILES` in `.env` o, se assenti, produzione con database interno e backup.

#### `Telonio`

- **`sisma aggiorna-database`**: esegue come root (`DB_ROOT_PASSWORD`) gli script dei moduli nell'ordine di `MODULE_FOLDERS`: per un modulo nuovo `schema.sql` e `dati-riferimento.sql`; per un modulo installato gli `aggiornamento-<versione>.sql` non ancora applicati, in ordine di versione, poi `schema.sql` e `dati-riferimento.sql`. Gli script applicati sono registrati nella nuova tabella `aggiornamento_database`; le `SELECT` di controllo degli script vengono stampate; al termine elimina `Cache/referenceCache.json`. `--simula` mostra il piano. Vale anche per la prima installazione (README, "Avvio e installazione");
- **`sisma aggiornamenti`**: versione installata, canale e ultima versione disponibile con le note di rilascio (`--canale=`, `--formato=shell` per lo script);
- **avviso in scrivania** per gli amministratori quando è disponibile una nuova versione del canale, con le note di rilascio e il comando da lanciare: GitHub è interrogato al massimo ogni 12 ore (un'ora dopo un errore di rete, timeout di 5 secondi); `CONTROLLO_AGGIORNAMENTI=false` lo disattiva.

### 🧩 Installazione

- README: installazione con `sisma aggiorna-database` al posto dell'elenco degli script SQL; nuova sezione "Aggiornamento". `.env.example`: `CANALE_AGGIORNAMENTI`, `CONTROLLO_AGGIORNAMENTI` e, commentati, `COMPOSE_FILE`, `COMPOSE_PATH_SEPARATOR`, `COMPOSE_PROFILES`. `.aggiornamenti/` esclusa da Git e dall'archivio delle release.
- Convenzione per gli script `aggiornamento-<versione>.sql` dei moduli: devono essere idempotenti e creare le tabelle che modificano (documentata in `www/Telonio/Docs/README.md`).

### 🔄 Aggiornamento da 0.9.0-alpha

Lo script di aggiornamento arriva con questa versione, quindi per l'ultima volta il codice si sostituisce a mano: le installazioni precedenti non hanno il comando `sisma aggiornamenti` che lo script usa per cercare la release. Con l'archivio della release scaricato:

```bash
curl -fLO https://github.com/valentinodelapa/telonio/releases/download/v0.10.0-alpha/telonio-v0.10.0-alpha.zip
sha256sum telonio-v0.10.0-alpha.zip   # da confrontare con l'impronta della pagina della release
unzip -p telonio-v0.10.0-alpha.zip aggiorna.sh > aggiorna.sh
sh aggiorna.sh --archivio=telonio-v0.10.0-alpha.zip
```

Lo script esegue il backup, sostituisce il codice e aggiorna il database: `sisma aggiorna-database` crea il registro e riesegue una volta, senza effetti, gli script di aggiornamento già applicati. Lo stesso procedimento vale dalle versioni precedenti alla 0.9.0-alpha: gli script delle versioni intermedie (es. `Deleghe/Database/aggiornamento-0.3.sql` della 0.9.0-alpha) non ancora applicati vengono eseguiti in automatico. Dalla versione successiva basta `sh aggiorna.sh`. Facoltativi in `.env`: `CANALE_AGGIORNAMENTI`, `CONTROLLO_AGGIORNAMENTI` e, se i file di Compose non sono quelli predefiniti, `COMPOSE_FILE` e `COMPOSE_PROFILES`.

## [0.9.0-alpha] - 2026-10-29 - Pagamento Rateale e Ravvedimento Frazionato

In `Deleghe` il pagamento rateale del saldo e del primo acconto (art. 20 D.Lgs. 241/1997) e del secondo acconto differito dalle norme straordinarie del 2023 e del 2024, il versamento tardivo dei contributi INPS e INAIL con le sanzioni civili e il ravvedimento frazionato. Valgono le avvertenze della 0.1.0-alpha: la versione non è indicata per l'uso in produzione su dati reali.

Versioni dei moduli: `Deleghe` 0.3.0; `Telonio` 0.4.0, `Anagrafica` 0.5.1, `Imu` 0.3.2, `Successioni` 0.3.1 e `GestioneStudio` 0.1.1 invariati.

### ✨ Nuove Funzionalità

#### `Deleghe` — pagamento rateale del saldo e del primo acconto

- **piani di rateazione** (`Entities\PianoRateazione`, `Services\CalcoloRateazione`, `Services\RateazioneService`, `RateazioneController`) secondo l'art. 20, c. 1, D.Lgs. 241/1997 nel testo del D.Lgs. 1/2024 (circolare n. 9/E del 2024): dalla scheda del contribuente, "Rateizza" sui versamenti da versare di una data sceglie i versamenti rateizzabili (codici con rateazione `NNRR`, esclusi secondo acconto e IMU; contributi INPS con causali AP, CP, P10, PXX) e il numero di rate, fino al 16 dicembre;
- **date proposte e modificabili**: prima rata alla scadenza, le altre il 16 di ogni mese; versamento al primo giorno lavorativo e, dal 1° al 20 agosto, al 20 agosto;
- **rate di pari importo** (centesimi residui sulla prima) con la rateazione `NNRR`; per l'INPS tutte le rate con la causale del pagamento rateale APR, CPR, P10R, PXXR (circolare INPS n. 105 del 2025);
- **interessi del 4% annuo** dalla seconda rata come nelle tabelle delle istruzioni (frazione fino alla seconda rata, poi 0,33% al mese: 0,18 - 0,51 - 0,84 - 1,17%...), su righi separati: 1668 per l'Erario (per anno di riferimento), 3805 per le Regioni (per codice regione), 3857 per l'addizionale comunale, API, CPI e DPPI per l'INPS; anteprima di quote e interessi per rata prima della registrazione;
- le rate sono versamenti generati (origine `Rateazione`) nell'archivio; i versamenti originari restano collegati al piano e non sono più da versare. Il piano si modifica (rate ricalcolate) o si elimina, con ritorno dei versamenti originari, finché nessuna rata è in una delega confermata, regolarizzata con un ravvedimento o versata in ritardo con le sanzioni civili;
- **rata non pagata**: si regolarizza con "Ravvedi" (tributi) o "Versa in ritardo" (contributi INPS), come gli altri versamenti scaduti; il rigo degli interessi di dilazione a cui appartiene la quota passa nella stessa delega (senza sanzione) e torna alla data della rata se il calcolo viene eliminato. Anche eliminando la delega i ravvedimenti e le sanzioni civili passano dai loro servizi, che riportano versamenti e interessi.

- **secondo acconto differito da una norma straordinaria** (`Entities\RateazioneSecondoAcconto`, dati in `Database/sorgenti/rateazione-secondo-acconto.csv`: art. 4, c. 2, D.L. 145/2023 per il periodo 2023 e art. 7-quater D.L. 155/2024 per il 2024): per le persone fisiche, il secondo acconto dei codici della norma (IRPEF 4034, forfetario 1791, regime di vantaggio 1794, cedolare 1841, IVIE 4045, IVAFE 4048) si rateizza separatamente dagli altri versamenti, in unica soluzione al 16 gennaio o fino a cinque rate il 16 di ogni mese, con gli interessi del 4% dalla seconda rata (0,33% al mese) sul 1668; il campo rateazione resta vuoto come richiede il formato `0000` dei codici. "Rateizza" compare anche dopo la scadenza ordinaria. Una nuova norma si aggiunge con una riga del file e `sisma tabelle-f24`.

#### `Deleghe` — versamento tardivo dei contributi e ravvedimento frazionato

- **contributi INPS e premi INAIL scaduti**: "Versa in ritardo" nella scheda del contribuente apre la delega in bozza della data scelta e il calcolo delle sanzioni civili con contributi e scadenza del versamento, collegato alla sanzione (`SanzioneCivile::$versamentoTardivo`): il versamento passa nella delega e torna alla sua scadenza se il calcolo viene eliminato; i moduli che lo hanno generato non lo sostituiscono al ricalcolo e ne segnalano la differenza;
- **ravvedimento frazionato** (circolari n. 27/E del 2013 e n. 42/E del 2016): il ravvedimento di un versamento dell'archivio può riguardarne solo una parte, con sanzione e interessi su quella parte; il versamento resta da versare con il residuo (mostrato nella scheda del contribuente) per i ravvedimenti successivi, che lo propongono e non lo possono superare;
- la delega in bozza del ravvedimento e del versamento tardivo è scelta con le stesse regole della generazione (modello e secondo codice fiscale compatibili).

### 🔄 Aggiornamento da 0.8.1-alpha

Sui database esistenti eseguire come root l'aggiornamento di `Deleghe`, poi eliminare `www/Cache/referenceCache.json`:

```bash
docker exec -i telonio_db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" telonio' < www/Deleghe/Database/aggiornamento-0.3.sql
```

`aggiornamento-0.3.sql` (idempotente) crea le tabelle `piano_rateazione` e `rateazione_secondo_acconto` (con le norme del 2023 e del 2024, le stesse di `dati-riferimento.sql`) e aggiunge le colonne `versamento.piano_rateazione_id` e `sanzione_civile.versamento_tardivo_id`.

## [0.8.1-alpha] - 2026-10-27 - Cartelle Scrivibili e Aliquote IMU senza Prospetto

Versione di correzione: le cartelle scrivibili vengono create all'avvio del container `php` (errore 500 su host Linux) e in `Imu` le aliquote di un comune senza prospetto per l'anno sono quelle dell'ultimo prospetto pubblicato (art. 1, comma 767, L. 160/2019). Valgono le avvertenze della 0.1.0-alpha: la versione non è indicata per l'uso in produzione su dati reali.

Versioni dei moduli: `Imu` 0.3.2; `Telonio` 0.4.0, `Anagrafica` 0.5.1, `Deleghe` 0.2.1, `Successioni` 0.3.1 e `GestioneStudio` 0.1.1 invariati.

### 🐛 Correzioni

- **errore 500 al salvataggio su host Linux** (es. creazione di un soggetto): le cartelle scrivibili `www/Cache`, `www/Logs` e `www/filesystemMedia`, escluse da Git e dall'archivio di rilascio, non venivano create. PHP-FPM gira come `www-data`, che non può crearle nella cartella del progetto montata dall'host (di proprietà dell'utente), e `docker-entrypoint.sh` ne impostava i permessi solo se già presenti: senza `Cache` il salvataggio delle entità falliva e senza `Logs` l'errore non veniva registrato. Ora l'entrypoint le crea all'avvio del container. Con Docker Desktop su Windows il problema non si presentava, perché le cartelle condivise non applicano questi permessi.

#### `Imu`

- **aliquote di un comune senza prospetto per l'anno** (art. 1, comma 767, L. 160/2019): dal 2025 il calcolo applicava le aliquote di base di legge a ogni anno senza prospetto pubblicato, anche se il comune ne aveva pubblicato uno negli anni precedenti. Le aliquote di base valgono solo finché il comune non pubblica un primo prospetto; dopo, in mancanza del prospetto dell'anno, si applicano quelle dell'ultimo prospetto pubblicato (aliquote vigenti nell'anno precedente, come chiarito dal Dipartimento delle Finanze per il 2026). Il calcolo segnala quando l'anno, o l'acconto, usa il prospetto di un anno precedente, e la differenziata scelta per l'anno di quel prospetto continua a valere. Ricalcolare gli anni dal 2026 dei comuni senza prospetto dell'anno.

### 🔄 Aggiornamento da 0.8.0-alpha

Nessuna modifica al database. Ricostruire l'immagine `php` (`--build`); in alternativa, sulle installazioni esistenti creare le cartelle a mano nella cartella del progetto (`mkdir -p www/Cache www/Logs www/filesystemMedia`) e riavviare il container `php`.

## [0.8.0-alpha] - 2026-10-26 - Telonio

Ottava versione alpha: il progetto cambia nome da CommSuite a **Telonio** (dal latino *telonium*, il banco dell'esattore delle imposte), perché "CommSuite" è un marchio registrato di terzi nel settore del software; il modulo base `CommSuite` diventa `Telonio`. In `Anagrafica` i codici ISO degli stati esteri vengono da una fonte ufficiale compatibile con la licenza MIT. Nessuna modifica allo schema del database. Valgono le avvertenze della 0.1.0-alpha: la versione non è indicata per l'uso in produzione su dati reali.

Versioni dei moduli: `Telonio` 0.4.0 (già `CommSuite` 0.3.0), `Anagrafica` 0.5.1, `Deleghe` 0.2.1, `Imu` 0.3.1, `Successioni` 0.3.1 e `GestioneStudio` 0.1.1 (richiedono `Telonio` 0.4.0).

### 🔧 Modifiche

#### Nome del progetto

- **CommSuite diventa Telonio**: modulo base rinominato (cartella `www/Telonio`, namespace `Telonio\`, `PROJECT` e `MODULE_FOLDERS` in `configFramework.php`, nome nei titoli delle pagine), requisiti dei moduli (`"Telonio": ">=0.4.0"` in `module.json`), pacchetto Composer `valentinodelapa/telonio`, nome delle release. Nessun dato del database contiene il nome del modulo.
- **nomi tecnici delle nuove installazioni**: in `.env.example` database `telonio`, utente `telonio_user`, prefisso dei container `telonio` (anche default in `docker-compose*.yml`), mittente `noreply@telonio.localhost`; in sviluppo `http://telonio.localhost` (phpMyAdmin e Mailpit su `db.` e `mail.telonio.localhost`).
- **inizializzazione del database**: `comm_suite.sql`, con nome dello schema e utente scritti nel file, è sostituito da `db-init.sh`, che li legge da `.env` (`DATABASE_NAME`, `DATABASE_USERNAME`): un `.env` con i vecchi nomi funziona anche su un volume nuovo.

### 🧩 Installazione

- **`COMPOSE_PROJECT_NAME`** documentato in `.env.example` e nel README: il volume del database prende il nome del progetto Compose, che senza la variabile è quello della cartella, quindi una nuova versione estratta in un'altra cartella partirebbe da un database vuoto. In produzione va fissato alla prima installazione.
- `.gitignore`: ignorate anche le copie di `.env` (`.env.*`, tranne `.env.example`), che contengono le credenziali.

### 🔧 Modifiche ai dati di riferimento

#### `Anagrafica`

- **codici ISO alpha-2 degli stati esteri da EU Vocabularies**: la corrispondenza tra i codici ISO 3166-1 alpha-3 (riportati da ANPR) e alpha-2 non viene più da `Database/sorgenti/iso3166-1.csv`, estratto dal pacchetto Debian `iso-codes` sotto licenza LGPL-2.1+ e quindi non coperto dalla licenza MIT del progetto, ma dalla tabella di autorità "Countries and territories" dell'Ufficio delle pubblicazioni dell'Unione europea (CC BY 4.0), scaricata da `sisma tabelle-riferimento` dall'endpoint SPARQL pubblico come le altre fonti (nuova opzione `--iso=FILE|URL`, servizio `CodiciIsoUe`). Il file è eliminato; il Kosovo mantiene il codice `XK`. I codici risultano identici ai precedenti, salvo l'Atollo di Clipperton (cessato nel 2019), che ora ha i codici `CP`/`CPT`. `dati-riferimento.sql` rigenerato.

### 🔄 Aggiornamento da 0.7.0-alpha

Nessuna modifica allo schema. Le installazioni esistenti mantengono il proprio `.env`: database e utente (`comm_suite`, `comm_suite_user`) restano quelli, senza interventi sui dati. Prima di aggiornare eseguire un backup.

1.  Aggiungere a `.env` `COMPOSE_PROJECT_NAME` con il nome attuale del progetto, cioè il prefisso del volume del database (`docker volume ls`, es. `comm-suite_db_data` → `COMPOSE_PROJECT_NAME=comm-suite`): così il nuovo codice usa lo stesso volume anche se estratto in un'altra cartella, dove vanno copiati `.env`, `backups/` e `rclone/`.
2.  Per mantenere i nomi dei container (`comm_suite_*`), e quindi i comandi `docker exec` già in uso, impostare in `.env` `CONTAINER_PREFIX=comm_suite` (o il prefisso attuale); altrimenti al prossimo avvio i container si chiamano `telonio_*`, con gli stessi dati. Facoltativo: `MAIL_FROM_NAME=Telonio`.
3.  Avviare la nuova versione con `--build` (es. `docker compose -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.backup.yml --profile internal-db up -d --build`) ed eliminare `www/Cache/referenceCache.json`, che contiene i vecchi nomi delle classi.
4.  Facoltativo, per allineare le tabelle di riferimento: rieseguire `www/Anagrafica/Database/dati-riferimento.sql` o `sisma tabelle-riferimento`.

Da una versione precedente alla 0.7.0-alpha applicare prima gli aggiornamenti delle versioni intermedie (per la 0.7.0-alpha anche lo schema e il calendario fiscale di `GestioneStudio`, senza i quali la scrivania non si apre).

## [0.7.0-alpha] - 2026-10-26 - Gestione dello Studio, Scadenzario e Regimi Fiscali

Settima versione alpha: nuovo modulo `GestioneStudio`, con lo scadenzario dello studio (le scadenze di tutti gli applicativi), il calendario fiscale degli adempimenti (con le proroghe, anche riservate ai soggetti ISA) e gli adempimenti dei clienti. In `Anagrafica` il regime fiscale dei titolari di partita IVA. Le scadenze degli altri moduli arrivano da un contratto definito in `CommSuite`, senza che `GestioneStudio` dipenda da loro. Richiede l'aggiornamento del database. Valgono le avvertenze della 0.1.0-alpha: la versione non è indicata per l'uso in produzione su dati reali.

Versioni dei moduli: `GestioneStudio` 0.1.0 (nuovo, richiede `CommSuite` 0.3.0 e `Anagrafica` 0.5.0), `CommSuite` 0.3.0, `Anagrafica` 0.5.0, `Deleghe` 0.2.0, `Imu` 0.3.0 e `Successioni` 0.3.0 (richiedono `CommSuite` 0.3.0).

### ✨ Nuove Funzionalità

#### `GestioneStudio` — scadenzario e adempimenti dei clienti

- **scadenzario dello studio** (`/scadenzario/index`, ingresso del modulo): scadenze di tutti gli applicativi del periodo, raggruppate per mese, con filtri per stato (da fare e in lavorazione, completate e non dovute, tutte), responsabile (anche senza responsabile) e applicativo; le scadute aperte sono evidenziate. Da ogni riga si segna la scadenza come completata o la si riapre, oppure se ne registrano stato, responsabile e note (`LavorazioneScadenza`); per le scadenze che il modulo di origine considera eseguite lo stato si gestisce nel modulo;
- **scheda del cliente** (`/scadenzario/soggetto`, elenco dei clienti in `/scadenzario/clienti`): adempimenti a cui il cliente è tenuto e scadenze dell'anno di tutti gli applicativi;
- **calendario fiscale degli adempimenti** comune a tutti gli studi (22 adempimenti: versamenti IVA mensili, trimestrali, acconto e saldo, dichiarazione IVA, LIPE, ritenute, Certificazione Unica, 770, dichiarazioni dei redditi di persone fisiche, società ed enti, IRAP, saldo e acconti delle imposte, 730, contributi IVS, bollo sulle fatture elettroniche, Intrastat, tassa sui libri sociali, diritto camerale), con la norma per ogni adempimento, curato in `GestioneStudio/Database/sorgenti` e importato con il nuovo comando `sisma calendario-fiscale`: l'Agenzia delle Entrate non pubblica lo scadenzario in un formato importabile;
- **termini effettivi**: termine ordinario dell'anno di riferimento, proroga dell'anno (dello studio, che prevale, o del calendario fiscale; per tutti o per i soli soggetti con i requisiti), differimento al 20 agosto dei termini dal 1° al 20 agosto e slittamento al primo giorno lavorativo; vista dei termini dell'anno nel calendario (`/adempimento/index`) e dei termini per anno di riferimento con le proroghe nella scheda dell'adempimento;
- **proroghe condizionate** (`CondizioneProroga`, `Services\RequisitiProroga`): una proroga può valere solo per i soggetti ISA, i forfetari, i contribuenti in regime di vantaggio e chi partecipa a società trasparenti o imprese familiari con questi requisiti (artt. 5, 115 e 116 TUIR), verificati sui dati fiscali dell'Anagrafica. Nel calendario fiscale la proroga al 20 luglio 2026 (o al 20 agosto con lo 0,80%) dei versamenti del saldo 2025 e del primo acconto 2026 e del diritto camerale 2026 per questi soggetti (art. 1-sexies D.L. 63/2026, conv. L. 113/2026);
- **adempimenti e proroghe dello studio**: adempimenti propri (es. scadenze interne) con i loro termini, proroghe inserite dallo studio anche sugli adempimenti del calendario fiscale;
- **adempimenti dei clienti** (`AssegnazioneAdempimento`, storicizzata): assegnazione dalla scheda del cliente o a più clienti dall'adempimento, con i clienti proposti in base all'Anagrafica (tipo di soggetto e, dai dati fiscali in vigore, partita IVA, attività d'impresa, regime fiscale, periodicità IVA e sostituto d'imposta: es. IVA mensile ai soli mensili, LIPE e dichiarazione IVA non ai forfetari; esclusi deceduti e cessati; Intrastat a richiesta), responsabile, rettifica, variazione da una data e annullamento;
- indicatori della scrivania: scadenze aperte scadute nell'ultimo anno, entro 7 ed entro 30 giorni;
- fixture demo: adempimenti del calendario assegnati ai soggetti demo e un adempimento dello studio.

#### `CommSuite`

- **contratto delle scadenze dei moduli**: `Interfaces\FonteScadenze`, `Services\Scadenza` e `Services\RaccoltaScadenze`, che raccoglie le scadenze dalle classi `<Modulo>\Application\Services\ScadenzeModulo` dei moduli disponibili: un modulo futuro entra nello scadenzario dichiarando la sua fonte;
- `HelperClasses\Calendario` (spostato da `Deleghe`, che lo usava insieme a `Imu` e `Successioni`) con `termineFiscale()`: differimento di agosto (art. 37, c. 11-bis, D.L. 223/2006) e primo giorno lavorativo;
- `ModuloSuite::gestioneStudio` disponibile con indirizzo `/scadenzario/index`; in `suite.js` la casella "seleziona tutti" (`data-seleziona-tutti`).

#### `Anagrafica`

- **regime fiscale** nei dati fiscali storicizzati (`Enumerations\RegimeFiscale`): ordinario, semplificato, forfetario, di vantaggio, forfetario degli enti non commerciali (art. 145 TUIR) e L. 398/1991, con i regimi ammessi per tipo di soggetto (es. solo ordinario per le società di capitali, forfetario e vantaggio solo per le persone fisiche con attività) e le decorrenze di legge; **periodicità IVA** (`Enumerations\PeriodicitaIva`), obbligatoria se il regime prevede le liquidazioni ed esclusa per forfetario e vantaggio; **applicazione degli ISA**, **sostituto d'imposta** (anche senza partita IVA) e **trasparenza fiscale** delle società di capitali. Nei form dei dati fiscali e del nuovo soggetto, nella scheda e nello storico; fixture demo aggiornate;
- pulsante "Scadenze" nella scheda del soggetto, verso la sua scheda dello scadenzario, se il modulo `GestioneStudio` è disponibile.

#### `Deleghe`

- scadenze per lo scadenzario (`Services\ScadenzeModulo`): versamenti dell'archivio, anche quelli registrati dagli altri moduli, raggruppati per contribuente e data, con saldo e stato delle deleghe; completati quando tutti in deleghe trasmesse o quietanzate o regolarizzati con ravvedimento. Nuovo `VersamentoModel::getPerPeriodo()`.

#### `Imu`

- scadenze per lo scadenzario: dichiarazioni IMU con termine al 30 giugno dell'anno successivo, completate quando presentate.

#### `Successioni`

- scadenze per lo scadenzario: termine di presentazione delle dichiarazioni (dodici mesi dall'apertura), riferito al defunto, completato quando presentata.

### 🐛 Correzioni

#### `CommSuite`, `Deleghe`, `Imu`, `Successioni`

- **4 ottobre non festivo**: dal 2026 il 4 ottobre è festa nazionale (L. 151/2025), quindi i termini che vi cadono passano al primo giorno lavorativo successivo. Il calendario dei termini non lo considerava: dal 2027, primo anno in cui cade in un giorno feriale, scadenze, termini delle dichiarazioni nel ravvedimento, sanzioni civili, rate delle successioni e scadenze IMU calcolati per il 4 ottobre sarebbero risultati un giorno prima del dovuto.

### 🔄 Aggiornamento da 0.6.0-alpha

Sui database esistenti eseguire come root l'aggiornamento di `Anagrafica`, lo schema e il calendario fiscale del nuovo modulo, poi eliminare `www/Cache/referenceCache.json`:

```bash
docker exec -i comm_suite_db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" --table comm_suite' < www/Anagrafica/Database/aggiornamento-0.5.sql
docker exec -i comm_suite_db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" comm_suite' < www/GestioneStudio/Database/schema.sql
docker exec -i comm_suite_db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" comm_suite' < www/GestioneStudio/Database/dati-riferimento.sql
```

`aggiornamento-0.5.sql` (idempotente) aggiunge a `dati_fiscali` le colonne del regime fiscale, assegna il regime ordinario alle società di capitali con partita IVA ed elenca le versioni in vigore con partita IVA da completare (regime e periodicità IVA) con la rettifica dei dati fiscali: finché non sono completate, la rettifica le richiede e le proposte e le proroghe condizionate non le riconoscono. Lo schema di `GestioneStudio` crea solo tabelle nuove (`adempimento`, `termine_adempimento`, `proroga_adempimento`, `assegnazione_adempimento`, `lavorazione_scadenza`); il calendario fiscale è idempotente e va rieseguito (o aggiornato con `sisma calendario-fiscale`) a ogni nuova versione delle sorgenti. Il modulo è registrato in `MODULE_FOLDERS`: senza le sue tabelle la scrivania non si apre.

## [0.6.0-alpha] - 2026-10-25 - Variazione del Possesso

Sesta versione alpha: in `Anagrafica` la variazione del possesso (quota o utilizzo) dalla scheda del soggetto o dell'immobile, con chiusura automatica della versione precedente. Valgono le avvertenze della 0.1.0-alpha: la versione non è indicata per l'uso in produzione su dati reali.

Versioni dei moduli: `Anagrafica` 0.4.0; `CommSuite` 0.2.0, `Deleghe` 0.1.3, `Imu` 0.2.1 e `Successioni` 0.2.1 invariati.

### ✨ Nuove Funzionalità

#### `Anagrafica`

- **variazione del possesso**: pulsante "Nuova variazione" sulle righe dei possessi in vigore nelle schede del soggetto e dell'immobile (`/possesso/variazione`). Il form parte dalla versione più recente dello stesso soggetto, immobile e titolo (quota e utilizzo); alla decorrenza indicata la versione precedente si chiude il giorno prima, con il controllo della somma delle quote. Il titolo non si cambia con una variazione. Prima la variazione si otteneva solo con "Nuovo possesso" sullo stesso immobile e titolo, e il pulsante della riga era solo la rettifica.

### 🔄 Aggiornamento da 0.5.1-alpha

Nessuna modifica al database.

## [0.5.1-alpha] - 2026-10-25 - Avviso sulle Aliquote dell'Acconto IMU

Versione di correzione: in `Imu` il calcolo segnala il prospetto delle aliquote dell'anno precedente mancante, con cui si determina l'acconto. Valgono le avvertenze della 0.1.0-alpha: la versione non è indicata per l'uso in produzione su dati reali.

Versioni dei moduli: `Imu` 0.2.1; `CommSuite` 0.2.0, `Anagrafica` 0.3.0, `Deleghe` 0.1.3 e `Successioni` 0.2.1 invariati.

### 🐛 Correzioni

#### `Imu`

- **prospetto dell'anno precedente mancante non segnalato**: l'acconto si calcola con le aliquote dell'anno precedente (comma 762); se il prospetto di quell'anno non era in archivio (es. non importato) l'acconto usava le aliquote di base di legge senza alcun avviso, con un saldo diverso dall'acconto senza spiegazione. Ora il calcolo lo segnala, invitando a importare il prospetto o a inserire le aliquote del comune.

### 🔄 Aggiornamento da 0.5.0-alpha

Nessuna modifica al database.

## [0.5.0-alpha] - 2026-10-25 - Causale Catastale e Decorrenza delle Rendite

Quinta versione alpha: causale delle variazioni catastali e decorrenza dei dati catastali dal possesso in `Anagrafica`; in `Imu` decorrenza delle rendite secondo l'art. 1, comma 745, L. 160/2019 (rendite vigenti al 1° gennaio, variazioni per interventi edilizi dall'ultimazione dei lavori) e classamento presunto per il possesso anteriore al primo classamento registrato; correzioni ai form con autocompletamento di `Deleghe` e `Imu`. Richiede l'aggiornamento del database. Valgono le avvertenze della 0.1.0-alpha: la versione non è indicata per l'uso in produzione su dati reali.

Versioni dei moduli: `Anagrafica` 0.3.0, `Imu` 0.2.0 (richiede `Anagrafica` 0.3.0), `Successioni` 0.2.1 (richiede `Anagrafica` 0.3.0: causale nelle fixture dei dati catastali), `Deleghe` 0.1.3; `CommSuite` 0.2.0 invariato.

### ✨ Nuove Funzionalità

#### `Anagrafica`

- **causale dei dati catastali** (`Enumerations\CausaleClassamento`, colonna `dati_catastali.causale`, obbligatoria): costituzione o primo classamento registrato, variazione per interventi edilizi (solo fabbricati), altra variazione. La prima versione di un nuovo immobile è la costituzione; nelle variazioni la causale si sceglie ogni volta; la correzione di un dato (anche della rendita proposta rettificata dall'Agenzia) resta una rettifica della versione. Visibile nello storico catastale della scheda dell'immobile;
- **decorrenza dei dati catastali dal possesso**: nell'inserimento di un immobile dalla scheda del soggetto "Dati in vigore dal" è facoltativa e parte vuota; se non indicata vale la data di inizio del possesso (prima era proposta la data del giorno, e il classamento risultava in vigore solo dall'inserimento);
- `DatiCatastaliModel::getVigenteOSuccessivo()`: dati catastali in vigore alla data o, in mancanza, i primi successivi.

#### `Imu`

- **decorrenza delle rendite secondo il comma 745** (`Services\ClassamentoImu`): valgono le rendite e i redditi dominicali vigenti al 1° gennaio; le variazioni per interventi edilizi valgono dalla loro decorrenza (ultimazione dei lavori o utilizzo), le altre dal 1° gennaio successivo, con nota nel rigo e avviso. Prima ogni variazione era applicata dalla sua decorrenza catastale;
- **classamento presunto per il possesso anteriore al primo classamento registrato**: i mesi di possesso precedenti alla decorrenza dei primi dati catastali dell'immobile si calcolano con quel classamento, con nota nel rigo e avviso da verificare sulla visura storica (prima l'immobile non era calcolato per quei mesi, "dati catastali mancanti"); non per i fabbricati D a valore contabile, per i quali la rendita vale solo dall'attribuzione. Le stesse regole valgono per il valore nella proposta della dichiarazione.

### 🐛 Correzioni

#### `Deleghe`

- **campo del codice tributo vuoto dopo un errore** nei form del versamento e del credito: ripresentando il form per correggere un altro campo, il codice tributo scelto restava valido (e veniva salvato) ma il testo del campo con autocompletamento era vuoto, e l'aiuto sul formato della rateazione mancava. Ora mostrano il codice inviato.

#### `Imu`

- **stesso difetto sul dichiarante** del form della dichiarazione IMU/IMPi: dopo un errore il campo con autocompletamento ora mostra il dichiarante inviato.

### 🔄 Aggiornamento da 0.4.0-alpha

Sui database esistenti eseguire come root `www/Anagrafica/Database/aggiornamento-0.3.sql`, poi eliminare `www/Cache/referenceCache.json`:

```bash
docker exec -i comm_suite_db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" --table comm_suite' < www/Anagrafica/Database/aggiornamento-0.3.sql
```

Lo script (idempotente) aggiunge `dati_catastali.causale` e la valorizza: prima versione di ogni immobile "costituzione"; versioni successive dei fabbricati "interventi edilizi", cioè con effetto dalla decorrenza come finora (i calcoli IMU già fatti non cambiano); versioni successive dei terreni "altra variazione", perché per i terreni la legge non prevede effetti in corso d'anno. Al termine elenca le variazioni dei fabbricati: quelle non dovute a interventi edilizi vanno corrette (rettifica della versione, causale "Altra variazione") e l'IMU degli anni interessati ricalcolata.

## [0.4.0-alpha] - 2026-10-24 - Self-hosting e Backup Remoti

Quarta versione alpha, dedicata all'installazione: configurazione Docker di produzione per il self-hosting privato (accesso tramite reverse proxy locale, es. Tailscale), immagine di produzione senza strumenti di debug, modalità dell'applicativo da `.env` e copia remota cifrata dei backup su OneDrive, Google Drive o altri servizi supportati da rclone. Valgono le avvertenze della 0.1.0-alpha: la versione non è indicata per l'uso in produzione su dati reali.

Versioni dei moduli: `CommSuite` 0.2.0, `Anagrafica` 0.2.0, `Deleghe` 0.1.2, `Imu` 0.1.0 e `Successioni` 0.2.0 invariati.

### 🧩 Installazione

- **self-hosting**: `nginx` pubblicato in produzione su `HTTP_BIND:HTTP_PORT` (default `127.0.0.1:8080`) per un reverse proxy locale (es. `tailscale serve`); nuova sezione "Self-hosting" del README (installazione, indirizzo web con Tailscale, backup e ripristino, aggiornamento);
- `Dockerfile` multi-stage: Xdebug e pcov solo nello stage `dev` (selezionato da `docker-compose.dev.yml`), immagine di produzione senza strumenti di debug;
- `DEVELOPMENT_ENVIRONMENT` e `HTTPS_IS_FORCED` lette da `.env` (assenti = `false`);
- prefisso dei nomi dei container configurabile (`CONTAINER_PREFIX`, default `comm_suite`) per una seconda istanza sulla stessa macchina;
- MariaDB fissata alla 12.2 (era `latest`) con aggiornamento automatico delle tabelle di sistema;
- backup: retention predefinita di 30 giorni (era 7), dump leggibili solo dal proprietario, servizio riavviato automaticamente;
- **copia remota cifrata dei backup** con rclone (remote `crypt`): dopo ogni dump i file vengono copiati su uno o più servizi scelti in `BACKUP_REMOTES` (es. OneDrive, Google Drive o entrambi), con retention propria (`BACKUP_REMOTE_RETENTION_DAYS`, default 90 giorni); configurazione in `./rclone/rclone.conf`, esclusa da Git e dall'archivio di rilascio;
- `nginx` risolve il container `php` a ogni richiesta: niente più 502 dopo la ricreazione di `php`.

### 🔄 Aggiornamento da 0.3.0-alpha

Nessuna modifica al database. Nel `.env` (vedi `.env.example`):

- in sviluppo aggiungere `DEVELOPMENT_ENVIRONMENT=true`: senza, l'applicativo passa in modalità produzione;
- in produzione impostare `DEVELOPMENT_ENVIRONMENT=false` e, dietro un reverse proxy HTTPS, `HTTPS_IS_FORCED=true`;
- facoltative: `CONTAINER_PREFIX`, `HTTP_BIND`/`HTTP_PORT` (produzione), `BACKUP_REMOTES`/`BACKUP_REMOTE_RETENTION_DAYS` (copia remota dei backup).

Ricostruire poi le immagini una volta con `--build` (es. `docker compose -f docker-compose.yml -f docker-compose.dev.yml --profile internal-db up -d --build`, aggiungendo `-f docker-compose.backup.yml` se si usa il backup).

## [0.3.0-alpha] - 2026-10-23 - Trust, Immobili Graffati ed Esteri

Terza versione alpha: immobili graffati in `Anagrafica`; in `Successioni` eredità giacente, beni dell'assente e trust testamentario, immobili graffati ed esteri, credito d'imposta per il riacquisto della prima casa e sanzione fissa per la tardiva presentazione; correzioni a `Deleghe` e `Successioni`. Valgono le avvertenze della 0.1.0-alpha: la versione non è indicata per l'uso in produzione su dati reali.

Versioni dei moduli: `Anagrafica` 0.2.0, `Successioni` 0.2.0 (richiede `Anagrafica` 0.2.0), `Deleghe` 0.1.2; `CommSuite` 0.2.0 e `Imu` 0.1.0 invariati.

### ✨ Nuove Funzionalità

#### `Anagrafica`

- **immobili graffati** (`Entities\IdentificativoGraffato`, tabella `identificativo_graffato`): ulteriori identificativi catastali (sezione, foglio, particella, subalterno) delle unità accatastate insieme, gestiti dalla scheda dell'immobile ed eliminati con l'immobile.

#### `Successioni`

- **cariche e beneficiari speciali**: curatore dell'eredità giacente (carica 5, curatela come unico rigo del quadro EA), immesso nel possesso dei beni dell'assente (carica 8) e **trust testamentario** (carica 9, tipo soggetto 5): righi del trustee per beneficiario finale con codice fiscale del trust, imposte ipotecaria e catastale fisse sugli immobili conferiti (righi EF3bis ed EF10bis), imposta di successione all'attribuzione ai beneficiari finali o con il pagamento anticipato scelto dal trustee, esenzione del trust per persone con disabilità grave (art. 6 L. 112/2016);
- per eredità giacente, eredità amministrata e trust **volture non automatiche**: casella "casi particolari", tassa ipotecaria di 65 euro per circoscrizione, nessuna proposta di variazione dei possessi;
- **immobili graffati** nei quadri EC ed EM (fino a quattro per rigo, gli altri con la continuazione) e **immobili all'estero** nei quadri EB ed EC (Stato, codice dello Stato, località e indirizzo; esclusi dalle imposte ipotecaria e catastale);
- **credito d'imposta per il riacquisto della prima casa** (art. 7 L. 448/1998, quadro EH sezione III) con gli estremi di acquisto e vendita: riduce l'imposta ipotecaria e, per il resto, la catastale;
- **sanzione per la tardiva presentazione senza imposta dovuta** in misura fissa ridotta (15, 27,78, 31,25, 35,71 euro), con F24 codice 1549 di chi presenta la dichiarazione;
- fixture della successione demo estese ai quadri EI, EM, EN ed EQ.

### 🐛 Correzioni

#### `Deleghe`

- **cambio della data di versamento di una delega con ravvedimenti o sanzioni civili**: sanzione e interessi restavano quelli calcolati alla data precedente. Ora si ricalcolano alla nuova data, con i versamenti generati; se un calcolo non è possibile (es. data non successiva alla scadenza originale) la data non viene cambiata.

#### `Successioni`

- **file telematico non valido con la prima casa del dichiarante**: nella sezione II del quadro EH i dati catastali dell'immobile erano nel namespace del registro invece che in quello della successione e il file non superava lo schema.
- **terreni tavolari del comune G831** (Pontebba): l'esclusione dall'imposta catastale prevista dalle specifiche per "G831A" vale ora solo per la sezione censuaria A, non per tutto il comune.

### 🔄 Aggiornamento da 0.2.0-alpha

Sui database esistenti:

```sql
CREATE TABLE IF NOT EXISTS `identificativo_graffato` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `immobile_id` INT UNSIGNED NOT NULL,
  `sezione` VARCHAR(5) NULL,
  `foglio` VARCHAR(10) NOT NULL,
  `particella` VARCHAR(10) NOT NULL,
  `subalterno` VARCHAR(10) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_identificativo_graffato_immobile` (`immobile_id`),
  CONSTRAINT `fk_identificativo_graffato_immobile` FOREIGN KEY (`immobile_id`) REFERENCES `immobile` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `beneficiario_successione`
  ADD COLUMN `codice_fiscale_trust` VARCHAR(16) NULL AFTER `grado_parentela_rappresentato`,
  ADD COLUMN `beneficiario_finale_id` INT UNSIGNED NULL AFTER `codice_fiscale_trust`,
  ADD CONSTRAINT `fk_beneficiario_successione_beneficiario_finale` FOREIGN KEY (`beneficiario_finale_id`) REFERENCES `soggetto` (`id`);

ALTER TABLE `bene_successione`
  ADD COLUMN `stato_estero_id` INT UNSIGNED NULL AFTER `dimensione`,
  ADD COLUMN `codice_stato_estero` CHAR(3) NULL AFTER `stato_estero_id`,
  ADD COLUMN `localita_estero` VARCHAR(50) NULL AFTER `codice_stato_estero`,
  ADD COLUMN `indirizzo_estero` VARCHAR(80) NULL AFTER `localita_estero`,
  ADD CONSTRAINT `fk_bene_successione_stato_estero` FOREIGN KEY (`stato_estero_id`) REFERENCES `nazione` (`id`);

ALTER TABLE `dichiarazione_successione`
  ADD COLUMN `pagamento_anticipato_trust` TINYINT(1) NOT NULL DEFAULT 0 AFTER `numero_rate`,
  ADD COLUMN `credito_tipo` CHAR(1) NULL AFTER `credito_successione`,
  ADD COLUMN `credito_importo` INT UNSIGNED NULL AFTER `credito_tipo`,
  ADD COLUMN `credito_acquisto_ufficio_id` INT UNSIGNED NULL AFTER `credito_importo`,
  ADD COLUMN `credito_acquisto_serie` VARCHAR(2) NULL AFTER `credito_acquisto_ufficio_id`,
  ADD COLUMN `credito_acquisto_numero` VARCHAR(6) NULL AFTER `credito_acquisto_serie`,
  ADD COLUMN `credito_acquisto_sottonumero` VARCHAR(3) NULL AFTER `credito_acquisto_numero`,
  ADD COLUMN `credito_acquisto_data` DATE NULL AFTER `credito_acquisto_sottonumero`,
  ADD COLUMN `credito_vendita_ufficio_id` INT UNSIGNED NULL AFTER `credito_acquisto_data`,
  ADD COLUMN `credito_vendita_serie` VARCHAR(2) NULL AFTER `credito_vendita_ufficio_id`,
  ADD COLUMN `credito_vendita_numero` VARCHAR(6) NULL AFTER `credito_vendita_serie`,
  ADD COLUMN `credito_vendita_sottonumero` VARCHAR(3) NULL AFTER `credito_vendita_numero`,
  ADD COLUMN `credito_vendita_data` DATE NULL AFTER `credito_vendita_sottonumero`,
  ADD CONSTRAINT `fk_dichiarazione_successione_credito_acquisto` FOREIGN KEY (`credito_acquisto_ufficio_id`) REFERENCES `ufficio_registrazione` (`id`),
  ADD CONSTRAINT `fk_dichiarazione_successione_credito_vendita` FOREIGN KEY (`credito_vendita_ufficio_id`) REFERENCES `ufficio_registrazione` (`id`);
```

### ⚠️ Limiti Noti

- **Successioni**: sanzione per la tardiva presentazione tra 31 e 90 giorni secondo la prassi applicativa (13,33%, 1/9 del minimo) da confermare rispetto all'art. 13, c. 1, lett. c), D.Lgs. 472/1997 (1/10); grado di parentela della curatela scelto dal professionista; codice dello Stato degli immobili all'estero indicato a mano (nessuna tabella ufficiale importabile); trust di scopo, trust misti e trustee beneficiario esclusi come dal modello telematico (modello 4 cartaceo).

## [0.2.0-alpha] - 2026-10-22 - Studi, Utenti e Ruoli

Seconda versione alpha: gestione degli studi e dei loro utenti, da riga di comando e da web, con i ruoli di amministratore e operatore; correzioni a `Deleghe`. Valgono le avvertenze della 0.1.0-alpha: la versione non è indicata per l'uso in produzione su dati reali.

Versioni dei moduli: `CommSuite` 0.2.0, `Deleghe` 0.1.1; `Anagrafica`, `Imu` e `Successioni` invariati (0.1.0). Tutti i moduli dichiarano ora in `module.json` SismaFramework 12.14.0, la versione già richiesta dalla 0.1.0-alpha (cifre nei nomi di proprietà e colonne).

### ✨ Nuove Funzionalità

#### `CommSuite` — gestione studi, utenti, ruoli e profilo

- **ruoli utente** (`Enumerations\RuoloUtente`, colonna `utente.ruolo`): distinzione tra `amministratore` dello studio (abilitato a modificare i dati dello studio e gestire gli utenti) e `operatore`;
- **comando CLI `sisma studio`** (`Console\Commands\StudioCommand`): elenco degli studi e degli utenti registrati, creazione di un nuovo studio con il primo utente amministratore (interattiva o tramite opzioni), creazione o reimpostazione di un amministratore per uno studio esistente, attivazione e disattivazione di uno studio;
- **gestione dello studio e degli utenti via web** (`StudioController`, `UtenteController`, `StudioForm`, `UtenteForm`, `StudioPermission`, `UtentePermission`): modifica dei dati dello studio (denominazione, codice fiscale, partita IVA) e gestione degli operatori dello studio (elenco, creazione con password iniziale, modifica ruolo e stato attivo, reimpostazione password), con protezione contro la disattivazione o la revoca del ruolo all'ultimo amministratore attivo;
- **profilo personale e cambio password** (`ProfiloController`, `ProfiloForm`, `PasswordForm`): modifica dei propri dati personali (cognome, nome, e-mail) e cambio della propria password previa verifica della password attuale; helper `Campo::password`;
- **documentazione del modulo** in `www/CommSuite/Docs/README.md`.

### 🐛 Correzioni

#### `Deleghe`

- **eliminazione di una delega con ravvedimenti o sanzioni civili**: andava in errore ("Errore vincoli integrità") perché i calcoli collegati alla delega non venivano eliminati prima della delega, e il controllo dei riferimenti di SismaFramework ne bloccava la cancellazione; ora ravvedimenti e sanzioni civili si eliminano insieme ai loro versamenti, gli altri versamenti tornano da versare.
- **ricalcolo dei moduli dopo un ravvedimento** (`Services\ArchivioVersamenti::registra`, usato da `Imu` e `Successioni`): un versamento da versare regolarizzato con un ravvedimento veniva sostituito al ricalcolo, con lo stesso errore. Ora è trattato come quelli già in deleghe confermate: resta com'è, insieme agli altri versamenti della stessa data, e l'esito riporta la differenza tra importo registrato e ricalcolato.

#### `CommSuite`

- modificando il proprio utente, il messaggio sulla revoca del proprio ruolo di amministratore non è più sostituito da quello generico sull'ultimo amministratore attivo.

### 🔄 Aggiornamento da 0.1.0-alpha

Lo schema di `CommSuite` aggiunge la colonna `utente.ruolo`. Sui database esistenti va aggiunta a mano; gli utenti esistenti vanno resi amministratori (con l'SQL seguente) o promossi singolarmente con `sisma studio amministratore --studio=ID|CF --username=USER`, altrimenti nessuno potrà gestire lo studio da web:

```sql
ALTER TABLE `utente` ADD COLUMN `ruolo` ENUM('amministratore','operatore') NOT NULL DEFAULT 'operatore' AFTER `email`;
UPDATE `utente` SET `ruolo` = 'amministratore';
```

Gli schemi degli altri moduli non cambiano.

## [0.1.0-alpha] - 2026-10-22 - Prima Versione Alpha della Suite

Prima versione pubblica di CommSuite, suite gestionale multi-studio per studi commerciali e tributari su SismaFramework 12.14.0. La versione è **alpha**: le funzionalità dei moduli sono complete nei flussi principali, ma struttura del database, interfacce tra moduli e file telematici possono ancora cambiare senza percorso di aggiornamento garantito. Non è indicata per l'uso in produzione su dati reali.

Moduli inclusi: `CommSuite` (involucro della suite), `Anagrafica`, `Deleghe`, `Imu` e `Successioni`.

### ✨ Nuove Funzionalità

#### `CommSuite` — involucro della suite

Il modulo base che contiene e collega gli applicativi:

- **studi e utenti**: ogni utente appartiene a uno studio e opera solo sui dati del proprio studio (isolamento multi-studio verificato dai permessi dei moduli);
- **accesso** con autenticazione e password cifrate;
- **layout** Bootstrap 5 con menu dei moduli (`Enumerations\ModuloSuite`: i moduli non ancora disponibili sono mostrati come "In arrivo") e scrivania con gli indicatori dei moduli;
- **componenti comuni** ai moduli: campi e helper HTML dei form (`Campo`, `Html`, `Layout`), validatori di codice fiscale, partita IVA e IBAN (controllo modulo 97 e CIN per i conti italiani).

#### `Anagrafica` — archivio unico dei soggetti

Archivio dei soggetti dello studio referenziato da tutti gli altri moduli (`Anagrafica\Application\Entities\Soggetto`):

- **soggetti**: persone fisiche, società di persone, società di capitali, enti; codice fiscale univoco per studio; flag cliente; data di decesso delle persone fisiche, con la ricerca dei deceduti;
- **dati storicizzati** con decorrenza (variazione, rettifica, annullamento tramite `Services\StoricoService`): dati fiscali (denominazione, natura giuridica, partita IVA, ATECO, ente non commerciale), indirizzi per tipo, legami tra soggetti (soci con quota, amministratori, legali rappresentanti, collaboratori familiari);
- **ditte individuali, imprese familiari e lavoratori autonomi** come attività storicizzata della persona fisica (inizio, trasformazione, cessazione), non come tipo di soggetto;
- **recapiti**, **conti correnti** (conto predefinito per gli addebiti, conti estinti mantenuti come non attivi) e **documenti d'identità**;
- **archivio immobili dello studio**: fabbricati e terreni con dati catastali storicizzati (anche del catasto tavolare: partita e corpo tavolare, comune catastale, porzione materiale) e possessi per soggetto, titolo, quota e utilizzo, con controllo delle quote per componente del diritto (piena proprietà, nuda proprietà, usufrutto, uso, abitazione);
- **codici ATECO 2007 agg. 2022 e ATECO 2025** (ISTAT) con il raccordo 2022 → 2025 proposto nelle nuove variazioni;
- **tabelle di riferimento**: comuni storici dal 1861 e stati esteri (tabelle di decodifica ANPR), classificazioni ATECO (ISTAT), importati e aggiornabili con `sisma tabelle-riferimento`.

#### `Deleghe` — deleghe di pagamento F24

Punto di raccolta dei versamenti della suite, con gestione per contribuente:

- **archivio dei versamenti** da versare e generazione delle deleghe per data di versamento; **archivio dei crediti** con residuo e compensazione manuale o automatica fino a capienza;
- **modelli** ordinario, semplificato, ELIDE e accise; stati `bozza → confermata → trasmessa → quietanzata`;
- **copia e ripetizione** dei versamenti e **duplicazione** della delega, con avanzamento dei riferimenti (mese, rata, periodo, anno) e della data al primo giorno lavorativo;
- **controlli di compilazione** secondo i formalismi delle tabelle dell'Agenzia delle Entrate per codice tributo e causale (Erario, INPS, Regioni, IMU e tributi locali, INAIL, altri enti, ELIDE, accise, crediti verso la PA con numero di certificazione);
- **ripartizione su più modelli** con saldo non negativo per modello e collocazione dei crediti;
- **ravvedimento operoso** (art. 13 D.Lgs. 471/1997 e 472/1997, testi vigenti, regimi per le violazioni fino al 31/08/2024 e successive, circostanze dopo verbale o schema d'atto) con interessi al tasso legale;
- **sanzioni civili INPS e INAIL** (art. 116 L. 388/2000) come prospetto, con rigo INPS generato quando è indicata la causale; tasso BCE dall'API dati della BCE;
- **stampa** sui modelli F24 ufficiali dell'Agenzia (sfondi SVG aggiornabili con `sisma modelli-f24`);
- **file telematico Entratel** secondo le specifiche tecniche per gli intermediari (provvedimento del 9 marzo 2015), con addebito sul conto del contribuente o dell'intermediario;
- **contratto per gli altri moduli** `Services\ArchivioVersamenti::registra`, con sostituzione dei versamenti non ancora in deleghe confermate e segnalazione delle differenze su quelli già confermati; secondo codice fiscale richiesto sul singolo versamento (es. il defunto per l'imposta di successione), con deleghe separate per secondo soggetto e controllo di coerenza;
- **tabelle di riferimento** dall'archivio ufficiale delle tabelle F24 dell'Agenzia, tassi BCE, tassi di interesse legale e regole di ravvedimento, aggiornabili con `sisma tabelle-f24`.

#### `Imu` — calcolo dell'imposta municipale propria

Calcolo dell'IMU (art. 1, commi 738-783, L. 160/2019) sugli immobili e sui possessi dell'Anagrafica, senza archivio immobili proprio:

- **calcolo per contribuente e anno**: soggetto passivo, mesi di possesso, base imponibile, riduzioni, esenzioni, pertinenze, aliquote, detrazione per l'abitazione principale, quota dello Stato sul gruppo D, acconto con le aliquote dell'anno precedente;
- **fabbricati D a valore contabile** con costi per anno e coefficienti del decreto annuale;
- **enti non commerciali**: esenzione del comma 759, lett. g), utilizzo misto con i rapporti del D.M. 200/2012 e versamento in tre rate;
- **aliquote dei comuni**: importazione dei prospetti pubblicati dal Dipartimento delle Finanze (`sisma aliquote-imu`), aliquote inserite dallo studio, comuni nati da fusione con prospetto per ex comune, riconoscimento parziale delle aliquote differenziate dalle condizioni del prospetto;
- **versamenti** di acconto e saldo (o unica soluzione) registrati nell'archivio di `Deleghe`, con acconti versati fuori dalla procedura e importo minimo; stampa del prospetto per il contribuente;
- **dichiarazione IMU/IMPi e IMU ENC**: proposta delle bozze dalle vicende dell'anno, quadri modificabili, sostitutiva, file telematico secondo le specifiche tecniche 2024;
- **tabelle di riferimento**: coefficienti dei fabbricati D, comuni montani e parzialmente montani (circolare n. 9/1993), isole minori (L. 448/2001), aggiornabili con `sisma tabelle-imu`.

#### `Successioni` — dichiarazione di successione e domanda di volture catastali

Dichiarazione di successione (D.Lgs. 346/1990) con ingresso dal defunto, persona fisica dell'Anagrafica con la data di decesso:

- **dichiarazione** prima, sostitutiva o integrativa: frontespizio con devoluzione per legge e/o per testamento, dichiarante e carica, intermediario, dati del quadro EH (luogo del decesso, separazione, prima casa del dichiarante, esenzioni e riduzioni); **sostitutiva creata da quella presentata**, con gli estremi della prima e le imposte già versate;
- **beneficiari** (quadro EA) dall'Anagrafica con grado di parentela, disabilità, rinuncia, rappresentazione; **quote di legge proposte** (artt. 565-586 c.c., allegato 5 alle istruzioni);
- **attivo** proposto dai possessi del defunto alla data del decesso con il valore catastale: immobili del catasto ordinario (quadri EB, EC) e tavolare (EL, EM), aziende con gli immobili aziendali collegati (EN), titoli e quote (EO), aeromobili (EP), navi e imbarcazioni (EQ), crediti, conti e altri beni (ER); devoluzione con agevolazioni e riduzioni; estremi di provenienza per le volture con dati dell'intestatario discordanti o passaggi senza atti legali (EI); **passività** (ED) con la ripartizione;
- **liquidazione** del quadro EF (imposte ipotecaria e catastale, prima casa, agevolazioni, tassa ipotecaria, bollo, tributi speciali) e **autoliquidazione dell'imposta di successione** per beneficiario (successioni dal 1° gennaio 2025, D.Lgs. 139/2024) secondo la guida di calcolo dell'Agenzia;
- **ravvedimento** della dichiarazione presentata in ritardo: sanzioni e interessi della sezione VI del quadro EF, sanzione per la tardiva presentazione e ravvedimento dell'imposta versata in ritardo;
- **versamenti F24 per beneficiario** (codici 1539, 1635, 1549, 1535 e 1537, risoluzione 2/E/2025), anche a rate con gli interessi, registrati nell'archivio di `Deleghe` con il defunto come secondo codice fiscale (codice 08);
- **allegati** (quadro EG) PDF/A e TIFF con controllo del formato;
- **file telematico** XML SUC13 (specifiche tecniche del 2 luglio 2025) **validato sullo schema ufficiale** prima dello scaricamento; stampa del prospetto di liquidazione;
- **volture** riportate nei possessi dell'Anagrafica dopo la presentazione, su conferma;
- **tabelle di riferimento**: uffici di registrazione, servizi di pubblicità immobiliare e comuni catastali di Trento e Bolzano; il comando `sisma specifiche-successioni` aggiorna gli XSD dalle specifiche dell'Agenzia, confronta i codici degli enum con gli schemi e importa le tabelle.

### 🧩 Installazione

Ogni modulo ha in `Database/` lo schema e, dove previsto, lo script idempotente dei dati di riferimento, necessari anche in produzione. Ordine di esecuzione come root: `CommSuite`, `Anagrafica`, `Deleghe`, `Imu`, `Successioni` (vedi il `README.md` di ciascun modulo in `Docs/`). Le fixture di sviluppo (`sisma fixtures`) caricano uno studio demo con utente `admin`/`admin`, soggetti, immobili, deleghe, dati IMU e una successione di esempio.

Ambiente: PHP 8.5-FPM, Nginx, MariaDB su Docker Compose, con file distinti per sviluppo, produzione e backup schedulato del database. L'immagine PHP include `libarchive-tools` e `poppler-utils`, richiesti da `Deleghe` e `Imu`.

### ⚠️ Limiti Noti

- **Anagrafica**: stati esteri cessati (es. Jugoslavia, URSS) non presenti nella tabella ANPR; verifica dei codici fiscali provvisori; import da altri gestionali non disponibile.
- **Deleghe**: limiti alle compensazioni (tetto annuo, visto di conformità) non verificati; standard CBI per l'home banking non implementato (specifica non pubblica); sanzioni delle casse professionali non gestite; F24 EP escluso; tabelle T3 e T5 controllate solo nel formato.
- **Imu**: file telematici delle dichiarazioni da verificare con il modulo di controllo del Dipartimento delle Finanze (in particolare anno della dichiarazione e periodo d'imposta nel record B); nuovi modelli attuativi del comma 768-bis non ancora approvati; fogli dei comuni parzialmente montani da confermare sull'immobile; condizioni delle aliquote differenziate non ricavabili dai dati restano "da verificare".
- **Successioni**: trust, eredità giacente e assenza, immobili graffati e immobili all'estero non gestiti; misure della sanzione per tardiva presentazione e tasso di interesse delle rate da verificare; ravvedimento delle sostitutive presentate dopo il termine da calcolare a mano; file telematico da verificare con il software di controllo dell'Agenzia delle Entrate.
- Moduli `Contabilita`, `Dichiarativi`, `Bilancio`, `Parcellazione` e `GestioneStudio` non ancora disponibili.
