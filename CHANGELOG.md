# Changelog

All notable changes to this project will be documented in this file.

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
