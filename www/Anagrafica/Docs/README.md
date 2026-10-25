# Modulo Anagrafica

Archivio unico dei soggetti dello studio: ogni altro modulo della suite
referenzia `Anagrafica\Application\Entities\Soggetto` invece di gestire una
propria anagrafica.

## Installazione

Il modulo è registrato in `MODULE_FOLDERS`. Script da eseguire come root
sullo schema del progetto, in quest'ordine:

1. `CommSuite/Database/schema.sql`
2. `Anagrafica/Database/schema.sql`
3. `Anagrafica/Database/dati-riferimento.sql` (comuni e stati esteri:
   necessari anche in produzione, vedi "Tabelle di riferimento")

Sui database creati con le versioni precedenti alla 0.3.0 eseguire
`Anagrafica/Database/aggiornamento-0.3.sql` (causale dei dati catastali,
idempotente; elenca le variazioni dei fabbricati da verificare) ed
eliminare `Cache/referenceCache.json`.

Per lo sviluppo, i dati demo (studio, utente `admin`/`admin`, una società
con storico di denominazione, sede e soci, una ditta individuale divenuta
impresa familiare con collaboratrice, persone fisiche, tre immobili con
storico catastale e comproprietà) si caricano con le fixture dei
moduli (`Application/Fixtures`):

```bash
docker exec comm_suite_php sh -c 'cd /var/www/html && sisma fixtures'
```

Le fixture vanno eseguite su tabelle vuote e richiedono i dati di
riferimento (punto 3). Ogni modulo registrato deve avere la cartella
`Application/Fixtures`, anche vuota: il comando le scansiona tutte.

## Modello dei dati

| Entità | Contenuto | Storicizzata |
|---|---|---|
| `Soggetto` | tipo (PF, società di persone, società di capitali, ente), codice fiscale, dati di nascita, data di decesso (persone fisiche: apre la successione nel modulo Successioni), cliente sì/no | no |
| `DatiFiscali` | denominazione, natura giuridica (società/enti) o attività economica (persone fisiche), partita IVA, ATECO, ente non commerciale (art. 73, c. 1, lett. c, TUIR; solo enti) | sì |
| `Indirizzo` | residenza, domicilio fiscale, sedi, ... (una versione vigente per tipo) | sì |
| `Legame` | il soggetto collegato ricopre un ruolo presso il soggetto (socio con quota, amministratore, legale rappresentante, ...) | sì |
| `Recapito` | telefono, e-mail, PEC, ... con recapito principale per tipo | no |
| `ContoCorrente` | IBAN (controllo modulo 97 e CIN per i conti italiani, `CommSuite\HelperClasses\Iban`), conto predefinito per gli addebiti, conto estinto mantenuto come non attivo | no |
| `DocumentoIdentita` | documenti delle persone fisiche | no |
| `Immobile` | archivio immobili dello studio: fabbricato o terreno, identificativo catastale, ubicazione, dati del catasto tavolare (partita e corpo tavolare, comune catastale, porzione materiale) | no |
| `IdentificativoGraffato` | ulteriori identificativi catastali (sezione, foglio, particella, subalterno) delle unità graffate | no |
| `DatiCatastali` | classamento: categoria, classe, consistenza, rendita (fabbricati); qualità, redditi dominicale e agrario (terreni) | sì |
| `Possesso` | soggetto che possiede l'immobile: titolo, quota, utilizzo | sì |
| `Comune`, `Nazione`, `CodiceAteco` | tabelle di riferimento comuni a tutti gli studi | versioni con validità |

`Soggetto.nominativo`, `Soggetto.partitaIva` e `Soggetto.tipoAttivita` sono
copie dei valori correnti, aggiornate da `Services\SincronizzazioneSoggetto`
dopo ogni modifica.

### Enum o entità

Un vocabolario è un **enum** quando è chiuso, stabile e con comportamento
(es. `CategoriaCatastale`, fissata per legge, con gruppo e presenza della
rendita; `TitoloPossesso`; `ClassificazioneAteco`). È un'**entità** quando
è ampio, aggiornato da una fonte esterna o ha versioni nel tempo: comuni,
stati esteri e codici ATECO (oltre 3.000 voci per classificazione,
aggiornate dall'ISTAT, con 2022 e 2025 in uso contemporaneo nello storico).

## Codici ATECO

`CodiceAteco` contiene le classificazioni ATECO 2007 agg. 2022 e ATECO 2025
(ISTAT), con livello, codice padre e, per i codici 2022, la sottocategoria
2025 di raccordo suggerita dall'ISTAT (tabella operativa di
riclassificazione, aggiornamento 2026). Ai soggetti si attribuiscono solo
sottocategorie, della classificazione in vigore alla decorrenza della
versione dei dati fiscali: ATECO 2025 dal 01/04/2025, ATECO 2022 prima.

Una nuova variazione che eredita un codice ATECO 2022 propone il codice
2025 di raccordo da confermare; per i codici 2022 che corrispondono a più
sottocategorie 2025 (128 casi) il campo resta da scegliere.

## Immobili

L'archivio immobili è dello studio: un immobile è unico anche se posseduto
da più soggetti (comproprietari, nudo proprietario e usufruttuario). Dalla
scheda del soggetto si inserisce un fabbricato o un terreno con i dati
catastali e il possesso del soggetto; se l'identificativo catastale esiste
già nello studio l'immobile viene riusato e si registra solo il possesso.

- **Dati catastali** storicizzati: una variazione catastale (nuova rendita,
  cambio di categoria) è una nuova versione con la sua decorrenza e la sua
  **causale** (`CausaleClassamento`): costituzione o primo classamento
  registrato, variazione per interventi edilizi (solo fabbricati; decorrenza
  dall'ultimazione dei lavori o, se prima, dall'utilizzo), altra variazione
  (revisione o classamento d'ufficio, qualità o coltura, ...). La causale
  serve ai moduli che applicano le rendite con regole proprie: per l'IMU le
  variazioni non edilizie valgono dal 1° gennaio successivo (art. 1, c. 745,
  L. 160/2019). Correggere un dato, o la rendita proposta rettificata
  dall'Agenzia, non è una variazione: si rettifica la versione, che mantiene
  decorrenza e causale. Per la rendita proposta con il Docfa (art. 1, c. 3,
  D.M. 701/1994) e poi rettificata, la notifica (art. 74, c. 1, L. 342/2000)
  è condizione di efficacia ma non ha effetto costitutivo: una volta
  notificata, la rendita definitiva si applica anche ai periodi d'imposta
  precedenti ancora suscettibili di accertamento, liquidazione o rimborso
  (Cass. SS.UU. 9 febbraio 2011, n. 3160; Agenzia delle Entrate, circolare
  n. 7/E del 17 marzo 2022, par. 3). Rettificando la versione, i moduli
  ricalcolano gli anni aperti con la rendita definitiva.
  Inserendo l'immobile dalla scheda del soggetto la decorrenza dei primi
  dati catastali, se non indicata, è quella del possesso: il classamento
  copre tutto il possesso e non parte dal giorno dell'inserimento.
- **Possesso** storicizzato per soggetto, immobile e titolo: acquisti,
  cessioni di quote e cambi di utilizzo (es. da abitazione principale a
  locato) sono variazioni.
- **Quote**: alla decorrenza di un possesso, la somma delle quote non può
  superare il 100% per componente del diritto: la piena proprietà conta sia
  come titolarità sia come godimento, la nuda proprietà come titolarità,
  usufrutto, uso e abitazione come godimento (`TitoloPossesso::getComponenti`).

Per gli altri moduli (redditi fondiari, IMU):

```php
// dati catastali vigenti alla data (per la decorrenza ai fini dei tributi vedi la causale)
$classamento = (new DatiCatastaliModel($dataMapper))->getVigenteAl($immobile, new SismaDate('2025-12-31'));
// in mancanza di dati alla data, i primi successivi (classamento presunto)
$classamento = (new DatiCatastaliModel($dataMapper))->getVigenteOSuccessivo($immobile, new SismaDate('2010-01-01'));
$possessori = (new PossessoModel($dataMapper))->getVigentiAl($immobile, new SismaDate('2025-12-31'));
```

## Ditte individuali e lavoratori autonomi

Non sono un tipo di soggetto a sé: l'imprenditore individuale e il
professionista sono la persona fisica stessa (stesso codice fiscale). Il
tipo di attività (`Enumerations\TipoAttivita`: impresa individuale, impresa
familiare, lavoro autonomo) è un dato storicizzato di `DatiFiscali`, insieme
a partita IVA, denominazione della ditta e ATECO:

- inizio attività: prima versione con attività, decorrenza = data di inizio;
- trasformazione (es. in impresa familiare): nuova variazione;
- cessazione: variazione senza attività con decorrenza dal giorno successivo;
- i collaboratori dell'impresa familiare sono `Legame` con ruolo
  `collaboratoreFamiliare` e quota di partecipazione.

Partita IVA e attività vanno indicate insieme; una partita IVA non può
appartenere a due soggetti dello stesso studio, nemmeno nello storico.
Dall'elenco, "Nuovo soggetto" offre direttamente ditta individuale, impresa
familiare e lavoratore autonomo.

## Storicizzazione

Le entità storicizzate implementano `Interfaces\Storicizzabile` (proprietà
`validoDal`/`validoAl`, `validoAl = null` = tuttora vigente) e passano da
`Services\StoricoService`:

- **variazione**: una nuova versione chiude la precedente il giorno prima
  della sua decorrenza; può essere inserita anche nel passato, nel qual caso
  viene chiusa il giorno prima della versione successiva;
- **rettifica**: correzione di una versione esistente; se la decorrenza
  cambia, la versione precedente resta contigua;
- **annullamento**: eliminando una versione, la precedente contigua ne
  eredita la fine validità.

Non sono ammesse due versioni con la stessa decorrenza né sovrapposizioni
nello stesso ambito (es. le sedi legali del soggetto).

Per leggere i dati a una data dagli altri moduli:

```php
$datiFiscali = (new DatiFiscaliModel($dataMapper))->getVigenteAl($soggetto, new SismaDate('2024-12-31'));
$indirizzi = (new IndirizzoModel($dataMapper))->getVigentiAl($soggetto, new SismaDate('2024-12-31'));
```

## Tabelle di riferimento (comuni, stati esteri, ATECO)

Comuni e stati esteri provengono dalle tabelle di decodifica ANPR del
Ministero dell'Interno
(https://www.anagrafenazionale.interno.it/area-tecnica/tabelle-di-decodifica/),
con codici catastali attribuiti dall'Agenzia delle Entrate; le
classificazioni ATECO dall'ISTAT (https://www.istat.it/classificazione/ateco-2025/):

- **comuni** (tabella 03, CSV): archivio storico dal 1861; ogni cambio di
  denominazione, provincia o territorio è una versione con date di
  istituzione e cessazione. I soggetti puntano alla versione in vigore
  alla data di nascita (allineata in automatico dal form), così i dati
  restano corretti anche dopo fusioni e ridenominazioni. Sono escluse le
  28 versioni di comuni soppressi prima dell'attribuzione dei codici
  catastali (codice `ND`).
- **stati esteri** (tabella 02, xlsx): codice catastale Z, ISO alpha-3 e
  validità; l'ISO alpha-2 (partita IVA comunitaria, fattura elettronica) è
  ricavato da `Database/sorgenti/iso3166-1.csv` (Debian iso-codes, più il
  codice `XK` del Kosovo).
- **ATECO 2025** (struttura, xlsx) e **ATECO 2007 agg. 2022** (dalla
  tabella operativa di riclassificazione 2022 -> 2025, xlsx), vedi
  "Codici ATECO".

`Database/dati-riferimento.sql` è generato e idempotente (`INSERT ... ON
DUPLICATE KEY UPDATE` sull'id ANPR, o su classificazione e codice per
ATECO): serve sia per la prima installazione sia per aggiornare. Il comando
del modulo:

```bash
# aggiorna il database dalle fonti (inserisce le nuove righe, aggiorna le
# esistenti, non cancella nulla: le righe sono referenziate)
docker exec comm_suite_php sh -c 'cd /var/www/html && sisma tabelle-riferimento'

# rigenera lo script SQL versionato nel modulo
docker exec comm_suite_php sh -c 'cd /var/www/html && sisma tabelle-riferimento --sql=Anagrafica/Database/dati-riferimento.sql'
```

Opzioni `--comuni=`, `--stati=`, `--ateco2025=` e `--ateco2022=` per usare
file locali al posto del download. Il comando si ferma senza modificare
nulla se il tracciato delle fonti cambia o se una tabella risulta
incompleta.

L'archivio dell'Agenzia delle Entrate ("Archivio Comuni e Stati esteri")
è distribuito solo via FTP come eseguibile autoestraente senza tracciato
documentato; ANPR ne riprende i codici con date di validità ed è
scaricabile via HTTPS.

## Multi-studio

Ogni soggetto appartiene a uno studio (`CommSuite\Application\Entities\Studio`).
I controller verificano l'accesso con `Permissions\SoggettoPermission`
(`Voters\SoggettoVoter`): un utente opera solo sui soggetti del proprio
studio. Il codice fiscale è univoco per studio.

## Da completare

- stati esteri cessati (es. Jugoslavia, URSS, Cecoslovacchia): la tabella
  ANPR contiene solo quelli attuali, ma i loro codici Z compaiono nei codici
  fiscali dei nati in quegli stati; serve una fonte storica (archivio
  dell'Agenzia delle Entrate o "Storico Paesi esteri" ISTAT/SITUAS);
- verifica del codice fiscale per i soggetti con codice fiscale provvisorio;
- import dell'anagrafica da altri gestionali.
