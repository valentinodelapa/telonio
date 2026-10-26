# Modulo Telonio

Modulo base della suite: studi professionali (tenant), operatori e ruoli,
autenticazione, scrivania con gli indicatori dei moduli, layout comune e
componenti condivisi dagli applicativi.

Non dipende da nessun altro modulo della suite ed è il primo schema da
caricare nel database.

## Installazione

Il modulo è registrato in `MODULE_FOLDERS`. Script da eseguire come root
sullo schema del progetto prima degli script di qualsiasi altro modulo:

1. `Telonio/Database/schema.sql`

Per creare uno studio in produzione (o un nuovo studio aggiuntivo) con il
suo primo utente amministratore si usa il comando CLI `sisma studio`:

```bash
# creazione guidata interattiva
docker exec -it telonio_php sh -c 'cd /var/www/html && sisma studio crea'

# oppure non interattiva tramite opzioni
docker exec telonio_php sh -c 'cd /var/www/html && sisma studio crea \
  --denominazione="Studio Rossi Associati" \
  --cf=80012345676 \
  --piva=01234567897 \
  --username=m.rossi \
  --cognome=Rossi \
  --nome=Mario \
  --email=m.rossi@studiorossi.it \
  --password=PasswordIniziale123'
```

Per lo sviluppo, lo studio demo (`Studio Demo Associati`), l'utente
amministratore `admin`/`admin` e l'operatore `operatore`/`operatore` vengono
creati dalle fixture (`Application/Fixtures`) eseguendo `sisma fixtures`.

## Modello dei dati

| Entità | Contenuto |
|---|---|
| `Studio` | studio professionale titolare dei dati (tenant): denominazione, codice fiscale (11 cifre per società/studi associati o 16 caratteri per professionista singolo), partita IVA, attivo sì/no |
| `Utente` | operatore dello studio (`Studio`), username univoco, cognome, nome, e-mail, ruolo (`Enumerations\RuoloUtente`: `amministratore` o `operatore`), attivo sì/no, data e ora dell'ultimo accesso |
| `Password` | hash bcrypt della password dell'utente con data di creazione; le password precedenti restano storicizzate ed è valida quella più recente |

Ogni utente appartiene sempre a uno studio (`utente.studio_id NOT NULL`):
non esiste un account web trasversale a più studi. L'autenticazione
(`UtenteModel::getValidAuthenticableInterfaceByIdentifier` e
`SuiteController::caricaUtente`) richiede che siano attivi sia l'utente sia
lo studio di appartenenza.

## Ruoli e gestione via web

All'interno dello studio gli utenti hanno uno dei due ruoli definiti in
`Enumerations\RuoloUtente`:

- **`amministratore`**: oltre a operare su tutti gli applicativi della suite,
  può modificare i dati identificativi del proprio studio (`/studio/update`)
  e gestire gli utenti dello studio (`/utente/index`, `/utente/create`,
  `/utente/update`, `/utente/password`), verificati da `StudioPermission`
  (`StudioVoter`) e `UtentePermission` (`UtenteVoter`).
- **`operatore`**: opera sugli applicativi dello studio e gestisce solo il
  proprio profilo personale (`/profilo/update`) e il cambio della propria
  password (`/profilo/password`, previa verifica della password attuale).

Regole di sicurezza applicate da `UtenteForm`:

- un amministratore non può disattivare il proprio account né revocare a se
  stesso il ruolo di amministratore;
- non è possibile disattivare o declassare un amministratore se nello studio
  non resta almeno un altro amministratore attivo.

## Comando CLI (`sisma studio`)

`Console\Commands\StudioCommand` gestisce il ciclo di vita degli studi
(tenant) e il recupero degli accessi di amministrazione da terminale:

```bash
# elenca gli studi registrati e i relativi utenti con ruolo e stato
docker exec telonio_php sh -c 'cd /var/www/html && sisma studio'

# crea un nuovo studio e il suo primo amministratore
docker exec -it telonio_php sh -c 'cd /var/www/html && sisma studio crea'

# aggiunge un amministratore a uno studio esistente (per ID o codice fiscale),
# oppure promuove/riattiva e reimposta la password se lo username esiste già nello studio
docker exec -it telonio_php sh -c 'cd /var/www/html && sisma studio amministratore --studio=80012345676'

# sospende o riattiva l'accesso per tutti gli utenti di uno studio
docker exec telonio_php sh -c 'cd /var/www/html && sisma studio disattiva --studio=1'
docker exec telonio_php sh -c 'cd /var/www/html && sisma studio attiva --studio=1'
```

## Architettura e componenti condivisi

- **`BaseClasses\SuiteController`**: controller base esteso dai controller
  di tutti i moduli della suite; verifica la sessione autenticata, espone
  `$this->utente` e `$this->studio`, gestisce il token CSRF, i messaggi
  flash e la validazione congiunta di più form (`isInvioValido`).
- **`Enumerations\ModuloSuite`**: registro degli applicativi della suite
  mostrati nella barra laterale e nella scrivania; un modulo risulta
  disponibile quando è registrato in `Config\MODULE_FOLDERS`.
- **Scrivania (`HomeController`)**: interroga automaticamente per ciascun
  modulo disponibile l'eventuale servizio `<Modulo>\Application\Services\RiepilogoScrivania::getIndicatori(Studio)`.
- **Scadenze dei moduli (`Interfaces\FonteScadenze`, `Services\Scadenza`,
  `Services\RaccoltaScadenze`)**: ogni modulo che ha scadenze le espone
  con la classe `<Modulo>\Application\Services\ScadenzeModulo`, che
  implementa `FonteScadenze::getScadenze(Studio, dal, al, ?soggetto)` e
  restituisce oggetti `Scadenza` (modulo, riferimento stabile, termine
  effettivo, descrizione, soggetto, importo, collegamento, completata);
  `RaccoltaScadenze` le raccoglie dai moduli disponibili in ordine di
  data. Lo scadenzario del modulo `GestioneStudio` le usa senza dipendere
  dai moduli che le producono: un nuovo modulo vi entra dichiarando la
  sua fonte.
- **Helper di vista (`HelperClasses`)**:
  - `Layout::percorso`: inclusione di `header` e `footer` comuni da
    qualsiasi modulo;
  - `Html`: escaping, formattazione di date e valori delle entità, classi e
    messaggi di errore dei form;
  - `Campo`: generazione uniforme dei campi Bootstrap 5 (`testo`, `data`,
    `password`, `areaTesto`, `selezione`, `selezioneEnum`, `spunta`,
    `autocompleta`).
- **Validatori fiscali e bancari (`HelperClasses`)**:
  - `CodiceFiscale`: normalizzazione, validazione formale (persone fisiche a
    16 caratteri, omocodie comprese, e codici numerici a 11 cifre), calcolo
    ed estrazione di sesso, data di nascita e codice catastale;
  - `PartitaIva`: normalizzazione e controllo di validità a 11 cifre;
  - `Iban`: normalizzazione, formattazione, verifica modulo 97 (ISO 13616) e
    controllo del CIN nazionale per i conti italiani.
- **Termini fiscali (`HelperClasses\Calendario`)**: giorni lavorativi con
  le festività nazionali (art. 2 L. 260/1949, Lunedì dell'Angelo e, dal
  2026, il 4 ottobre, L. 151/2025), primo giorno lavorativo per i termini
  che cadono di sabato o in un giorno festivo (art. 7, c. 1, lett. h, D.L.
  70/2011) e `termineFiscale`, che prima differisce al 20 agosto i termini
  dal 1° al 20 agosto (art. 37, c. 11-bis, D.L. 223/2006); usato da tutti
  i moduli che calcolano scadenze.
