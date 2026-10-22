# Modulo Deleghe

Deleghe di pagamento F24 dei soggetti dello studio: archivio dei versamenti e
dei crediti di ogni contribuente, generazione delle deleghe per data di
versamento con le compensazioni, controlli di compilazione secondo le tabelle e
le specifiche tecniche dell'Agenzia delle Entrate, ripartizione su più modelli,
ravvedimento operoso, sanzioni civili INPS e INAIL, ciclo di vita fino alla
quietanza, stampa sul modello ufficiale e file telematico per Entratel.

È il punto di raccolta dei versamenti calcolati dagli altri moduli (IMU,
IVA, dichiarazioni): ogni modulo registra i versamenti dovuti
nell'archivio del contribuente, Deleghe genera le deleghe.

## Flusso di lavoro

Si lavora per contribuente, come nella gestione per ditta: "Deleghe F24"
apre l'elenco dei contribuenti (di default i soli clienti) e la scheda del
contribuente ha tre schede:

- **Da versare**: i versamenti non ancora in una delega, raggruppati per
  data di versamento. Per ogni data "Genera la delega" li raccoglie nella
  delega in bozza di quella data (creata se manca), separando ELIDE e accise
  nei loro modelli, e a richiesta li compensa con i crediti disponibili. I
  versamenti con data superata non si generano: "Ravvedi" apre il calcolo
  del ravvedimento nella delega della data scelta, con i dati del
  versamento, che non risulta più da versare.
- **Deleghe**: le deleghe del contribuente, filtrabili per stato e anno.
- **Crediti**: i crediti da utilizzare in compensazione con importo,
  utilizzi e residuo.

"Nuova delega" chiede la data di versamento (proponendo le date che hanno
versamenti da versare) e vi inserisce subito i versamenti di quella data
ammessi dal modello, con le compensazioni se richieste. Il riepilogo dei
crediti con residuo è anche in testa alla scheda "Da versare".

I versamenti si inseriscono nell'archivio ("Nuovo versamento") o
direttamente in una delega in bozza; dalla delega un versamento si può
togliere (torna da versare) ed eliminando la delega tornano tutti da
versare, tranne gli utilizzi dei crediti e i versamenti calcolati nella
delega (ravvedimenti e sanzioni civili). Gli invii telematici, gli
intermediari e i codici tributo restano a livello di studio.

## Installazione

Il modulo è registrato in `MODULE_FOLDERS` e richiede CommSuite e
Anagrafica (tabella `conto_corrente`). Script da eseguire come root dopo
quelli di Anagrafica:

1. `Deleghe/Database/schema.sql`
2. `Deleghe/Database/dati-riferimento.sql` (tabelle F24, tassi, regole di
   ravvedimento: necessari anche in produzione, vedi "Tabelle di
   riferimento")

L'immagine Docker deve contenere `bsdtar` (pacchetto `libarchive-tools`,
nel `Dockerfile`) per estrarre l'archivio RAR delle tabelle dell'Agenzia e
`pdftocairo`/`pdftotext` (pacchetto `poppler-utils`) per convertire i
modelli F24 ufficiali usati dalla stampa. Gli SVG già convertiti sono nel
modulo (`Application/Assets/svg`): `sisma modelli-f24` serve solo ad
aggiornarli.

Le fixture (`sisma fixtures`) creano: i conti correnti di Rossi, Alfa e
Verdi; lo studio come soggetto e intermediario Entratel; quattro deleghe
demo (Rossi quietanzata; Alfa con ritenute, INPS e compensazione del
credito IVA 2025 dell'archivio crediti; Verdi con contributi artigiani e un
rigo IMU con origine `Imu`; Rossi F24 ELIDE per l'imposta di registro di
una locazione); versamenti da versare (ritenute di novembre di Alfa,
secondo acconto IRPEF di Rossi, acconto IMU scaduto di Verdi da
ravvedere).

## Modello dei dati

| Entità | Contenuto |
|---|---|
| `Delega` | contribuente (`Soggetto`), modello (ordinario, semplificato, ELIDE, accise), data di versamento, stato, secondo codice fiscale con codice identificativo, conto di addebito (`ContoCorrente`), codice ufficio e atto, identificativo operazione, detrazione IMU, invio telematico, quietanza |
| `Versamento` | versamento del contribuente con la sua data, da versare (senza delega) o in una delega, con l'eventuale secondo codice fiscale richiesto: è il rigo di una sezione del modello (codice tributo o causale, riferimenti dell'ente, rateazione, anno o periodo, flag IMU e detrazione dell'abitazione principale, tipo ed elementi identificativi ELIDE, provincia, codice identificativo e mese accise, numero di certificazione crediti PA, importo a debito o a credito), con descrizione, origine e credito utilizzato |
| `Credito` | credito del contribuente da compensare: dati del rigo a credito, importo, data da cui è utilizzabile, origine; il residuo è l'importo meno gli utilizzi (versamenti collegati) |
| `Ravvedimento` | versamento omesso regolarizzato nella delega: tributo, importo, scadenza originale, termini, circostanza, codici di sanzione e interessi, calcolo, versamento omesso dell'archivio |
| `SanzioneCivile` | contributi INPS o premi INAIL versati in ritardo: importo, scadenza, regime, calcolo per periodi di tasso, causale facoltativa per il rigo |
| `Intermediario`, `InvioTelematico` | intermediari Entratel dello studio; file generati con le deleghe incluse |
| `CodiceTributo`, `CodiceTabellaF24`, `CausaleContributo` | tabelle dell'Agenzia delle Entrate, comuni a tutti gli studi |
| `TassoRiferimento` | tasso delle operazioni di rifinanziamento principali della BCE (ex TUR), dalla fonte BCE |
| `TassoInteresseLegale`, `RegolaRavvedimento` | dati di riferimento curati nel modulo (`Database/sorgenti`) |

Le sezioni sono l'enum `SezioneF24` (Erario, INPS, Regioni, IMU e altri
tributi locali, INAIL, altri enti previdenziali, ELIDE, accise), con i
campi del rigo (`getCampi`) e i righi del modello (`getRigheModello`); i
modelli l'enum `ModelloF24` (sezioni ammesse, 10 righi complessivi del
semplificato, tipo del record telematico). `Versamento` ha colonne
generiche il cui significato dipende dalla sezione: `codiceEnte` è il
codice regione, il codice del comune o dell'ente; `posizione` la matricola
INPS, il codice ditta INAIL o il codice posizione.

### Enum o entità

Come in Anagrafica: i codici tributo (oltre 2.500), le causali (oltre 600)
e le altre tabelle di decodifica sono **entità** importate dalla fonte;
sezioni, modelli, stati e codici identificativi del secondo codice fiscale
sono **enum** (tabella breve, con comportamento, pubblicata solo in PDF).

Le cifre nei nomi di proprietà e colonne sono ammesse solo come parte di
una parola: nella conversione da camelCase a snake_case sono trattate come
lettere minuscole (`CodiceTabellaF24` → `codice_tabella_f24`), quindi una
colonna non può avere una cifra subito dopo un underscore (`codice_24`).

## Stati della delega

`bozza → confermata → trasmessa → quietanzata`, con passaggi indietro per
correggere. Solo la bozza è modificabile e aggiornata dai moduli che
generano versamenti; cambiando la data di versamento cambia anche quella
dei suoi versamenti; la conferma richiede una delega senza errori; la
generazione del file telematico porta le deleghe a "trasmessa" e
l'annullamento dell'invio le riporta a "confermata"; la quietanza registra
data di addebito e protocollo.

## Copia e ripetizione dei versamenti

Per i versamenti che si ripetono nel tempo (IMU caricata a mano, ritenute,
contributi fissi, rate), `Services\CopiaVersamenti` crea copie inserite a
mano, da versare e indipendenti dall'originale:

- **Copia** (su ogni versamento, anche di deleghe trasmesse o quietanzate):
  apre il form già compilato al mese successivo, da correggere e salvare;
- **Ripeti**: cadenza (mensile, trimestrale, semestrale, annuale) e numero
  di copie, con anteprima; per la rateazione NNRR si ferma all'ultima rata;
- **Duplica** (della delega): riporta a una data scelta i versamenti
  inseriti a mano, esclusi compensazioni (si rifanno sui crediti) e
  versamenti generati dagli altri moduli (li ricalcola il modulo), e a
  richiesta genera subito la delega di quella data.

Spostando la data si aggiornano i riferimenti secondo il formato della
rateazione del codice tributo: mese di riferimento `00MM` (e anno a fine
anno), rata `NNRR`, periodi MM/AAAA, mese delle accise; negli altri casi
l'anno di riferimento avanza degli anni interi (IMU da un anno all'altro).
La data parte dalla scadenza nominale (un 17-19 preceduto solo da giorni
non lavorativi dal 16 è il 16 slittato) ed è spostata al primo giorno
lavorativo. Importi e altri dati restano quelli dell'originale.

## Crediti e compensazioni

I crediti si registrano nella scheda del contribuente con i dati del rigo a
credito (es. 6099, rateazione 0101, anno 2025) e la data da cui sono
utilizzabili (es. per il credito IVA annuale oltre 5.000 euro il decimo
giorno successivo alla presentazione della dichiarazione, art. 17 D.Lgs.
241/1997). `Services\Compensazioni` crea il rigo a credito nella delega
collegato al credito:

- dalla delega, per ogni credito disponibile, con l'importo proposto pari
  al minimo tra residuo e capienza;
- automaticamente, alla generazione o con "Compensa con i crediti
  disponibili", nell'ordine in cui i crediti sono diventati utilizzabili.

La capienza è il saldo che il credito può ridurre: debiti IMU dello stesso
comune per i crediti IMU, debiti dell'elenco del D.M. 14/1/2014 per i
crediti verso la PA, altrimenti il saldo delle sezioni che ammettono
compensazioni. `ControlloDelega` verifica che gli utilizzi non superino il
credito, che il credito sia del contribuente e utilizzabile alla data della
delega.

## Controlli di compilazione

`Services\ControlloDelega`, usato dai form e dai moduli che generano righe,
applica i formalismi delle tabelle dell'Agenzia per codice tributo e
causale:

- **codice tributo**: sezione giusta, in uso, utilizzo a debito o a credito
  ("modalità di utilizzo"), anno di riferimento richiesto o da non indicare
  e anno massimo rispetto al versamento, rateazione secondo il formalismo
  (`0000` da non compilare, `00MM` mese, `NNRR` rata, `FFFF` rata
  facoltativa, `00T0`/`00T1`/`00T2` codice della regione, dell'ente o della
  provincia, `00T4` codice del comune), codice ufficio e atto richiesti o
  ammessi, identificativo operazione;
- **IMU e tributi locali**: codice ente sulle tabelle indicate dal codice
  (T4 comuni dell'archivio di Anagrafica, T1, T2, T6; T5 solo nel formato);
  un credito IMU richiede debiti IMU dello stesso comune almeno pari;
- **INPS**: sede nella tabella delle sedi, causale nella tabella delle
  causali, matricola nel formato ammesso per la causale (tabella dei
  formati, es. 10 cifre per la DM10, 17 per AF), periodo obbligatorio,
  facoltativo o da non indicare secondo la causale;
- **INAIL**: sede nella tabella, codice ditta, cifra di controllo, numero
  di riferimento, causale di una lettera;
- **altri enti**: ente nella tabella, sede ENPALS nella tabella, causale
  nella tabella dell'ente (se pubblicata), codice posizione numerico;
- **ELIDE**: tipo nella tabella, elementi identificativi obbligatori, da
  non compilare o facoltativi secondo il codice, nessun credito;
- **accise** (modello F24 Accise): ente, provincia (tabella T2), codice
  identificativo, rateazione e mese/anno secondo i formalismi della tabella
  delle accise, nessun credito; codice ufficio e atto nella sezione accise
  se richiesti da un codice accise, altrimenti nella sezione Erario (una
  sola coppia per modello);
- **crediti verso la PA** (codice PPAA, art. 28-quinquies D.P.R.
  602/1973): numero di certificazione della piattaforma obbligatorio (vietato
  negli altri righi); il credito compensa solo i debiti dell'elenco del D.M.
  14/1/2014 (file "Elenco codici tributo pagabili 28 quinquies" dell'archivio,
  colonna `compensabile_crediti_pa`), e la ripartizione lo colloca solo nei
  modelli con quei debiti;
- **delega**: sezioni ammesse dal modello, saldo non negativo, crediti
  collocabili nei modelli, conto di addebito intestato al contribuente (o
  al secondo soggetto per i codici identificativi che lo richiedono).

Avvisi: canale di presentazione con compensazioni (servizi telematici
dell'Agenzia per i crediti erariali, regionali, INPS e INAIL, art. 37,
c. 49-bis, D.L. 223/2006; a saldo zero sempre), più modelli necessari,
IMU senza acconto/saldo, scadenza superata.

## Ripartizione sui modelli

`Services\RipartizioneModelli` divide i righi sui modelli necessari (usati
dalla stampa e dal file telematico):

- righi per sezione nell'ordinario e nell'accise (7 righi accise), 10
  complessivi nel semplificato, 28 nell'ELIDE;
- un solo codice ente per la sezione altri enti in ogni modello;
- saldo non negativo per ogni modello: i crediti si collocano dopo i debiti
  e si dividono tra i modelli se serve; collocando i debiti si lasciano
  liberi tanti righi quanti sono i crediti della sezione;
- un credito IMU solo nei modelli con debiti IMU dello stesso comune, un
  credito PPAA solo nei modelli con debiti compensabili con esso;
- la detrazione per l'abitazione principale una sola volta.

## Ravvedimento operoso

Dalla delega in bozza, "Ravvedimento" per le sezioni Erario, Regioni e IMU:
si indicano tributo, importo non versato e scadenza originale; la data di
versamento è quella della delega. Per un versamento dell'archivio con data
superata, "Ravvedi" nella scheda del contribuente apre lo stesso calcolo
nella delega in bozza della data scelta (creata se manca) con i dati del
versamento, collegato al ravvedimento (`versamentoOmesso`): non risulta più
da versare, e torna tale se il ravvedimento viene eliminato. `Services\CalcoloRavvedimento` applica
(testi vigenti su Normattiva):

- sanzione dell'art. 13, c. 1, D.Lgs. 471/1997: 30%, o 25% per le
  violazioni dal 1/9/2024 (D.Lgs. 87/2024); dimezzata fino a 90 giorni di
  ritardo, ridotta a 1/15 per giorno fino a 15 giorni;
- riduzione dell'art. 13, c. 1, D.Lgs. 472/1997: 1/10 entro 30 giorni, 1/9
  entro 90, 1/8 entro il termine della dichiarazione dell'anno della
  violazione (o un anno), poi 1/7; per le violazioni fino al 31/08/2024 1/7
  entro il termine della dichiarazione dell'anno successivo (o due anni) e
  1/6 oltre;
- circostanza (`CircostanzaRavvedimento`, lett. b-ter e seguenti): dopo il
  verbale di constatazione 1/5; dopo lo schema d'atto (art. 6-bis L.
  212/2000) 1/6, o 1/4 se seguito al verbale. Lo schema d'atto vale solo per
  le violazioni dal 1/9/2024; la riduzione per circostanza sostituisce
  quella per tempo;
- interessi al tasso legale giorno per giorno, base 365, per i periodi di
  vigenza dei tassi.

Il termine della dichiarazione è proposto da `DichiarazioneRiferimento`
(redditi, IRAP e 770: 31 ottobre; IVA: 30 aprile; IMU: 30 giugno dell'anno
successivo; spostato al primo giorno lavorativo da `HelperClasses\Calendario`)
ed è modificabile per i termini prorogati.

`Services\RavvedimentoService` genera i versamenti nella delega con origine
`Ravvedimento` tramite `ArchivioVersamenti`: tributo, sanzione e interessi con i codici
della regola (es. IRPEF 4001 + 8901 + 1989); senza codice degli interessi
questi si sommano al tributo (ritenute, Ris. 109/E/2007); senza codice della
sanzione si somma anche la sanzione e il rigo IMU riporta il flag "Ravv."
(Ris. 35/E/2012). Per i tributi senza regola i codici si indicano nel form.
Modificando il ravvedimento le righe vengono ricalcolate.

## Sanzioni civili INPS e INAIL

Dalla delega in bozza, menu "Ravvedimento" → "Sanzioni civili INPS/INAIL":
si indicano contributi, scadenza originale, regime ed eventuale pagamento
spontaneo. `Services\CalcoloSanzioniCivili` applica l'art. 116, c. 8 e
seguenti, L. 388/2000 (regimi in `RegimeSanzioneCivile`):

- omissione: tasso di riferimento BCE + 5,5 punti annui, tetto 40%; con
  pagamento spontaneo entro 120 giorni il solo tasso di riferimento;
- evasione denunciata spontaneamente: tasso + 5,5 punti con pagamento entro
  30 giorni dalla denuncia, + 7,5 entro 90 giorni, tetto 40%;
- evasione: 30% annuo, tetto 60%.

Il calcolo è giornaliero (base 365) per i periodi di vigenza del tasso; il
dettaglio per periodo resta nella sanzione. Di norma l'INPS richiede le
sanzioni con un avviso, quindi il calcolo è un **prospetto**: solo
indicando la causale (es. quella delle sanzioni per artigiani e
commercianti) con sede, matricola e periodo `Services\SanzioniCiviliService`
genera il rigo INPS con origine `SanzioniCivili` tramite
`ArchivioVersamenti`. Per l'INAIL le sanzioni sono richieste dall'istituto e
non si versano con una causale: solo prospetto. Le casse professionali
(sezione altri enti) hanno regolamenti propri e non sono gestite.

## Stampa

"Stampa" compila i modelli F24 ufficiali dell'Agenzia delle Entrate
(ordinario, semplificato, elementi identificativi, accise), uno per ogni
modello della ripartizione: lo sfondo è la pagina del PDF ufficiale
convertita in SVG, i dati sono posizionati sulle caselle con le coordinate
in punti rilevate dal PDF (`Services\Stampa\ModuloUfficialeF24`). Di
default si stampa la copia per il soggetto che effettua il versamento;
"Tutte le copie" aggiunge la 1ª e la 2ª copia per la banca, con l'IBAN del
conto di addebito (il semplificato ha le due copie sulla stessa pagina).
Stampare in A4, senza margini e in scala 100%.

```bash
# aggiorna gli sfondi dai modelli pubblicati dall'Agenzia
docker exec comm_suite_php sh -c 'cd /var/www/html && sisma modelli-f24'
```

Il comando trova il PDF nella scheda "Modello e istruzioni" di ogni
modello, verifica che alcune parole siano nella posizione attesa e
converte ogni pagina con `pdftocairo -svg`; se l'Agenzia cambia
l'impaginazione si ferma, perché le coordinate dei campi vanno rilevate di
nuovo. `--pdf=CARTELLA` usa PDF già scaricati.

## File telematico (Entratel)

"Invii telematici" genera il file con le deleghe confermate secondo le
"Specifiche tecniche per la trasmissione telematica dei modelli F24 da
parte degli intermediari" (Allegato 2 al provvedimento del 9 marzo 2015,
`Services\Telematico\TracciatoF24`): record A (intermediario), per ogni
delega un record M (contribuente, versante o coobbligato, conto, saldo,
data) e un record V per ogni modello della ripartizione (tipo "A" con
sezione IMU, tipo "B" per l'accise con la sezione accise da posizione 1385,
tipo "8" per l'ELIDE; il semplificato si trasmette come ordinario), record
Z. Il numero di certificazione dei crediti PA è nei righi della sezione
Erario. Record di 1.900 caratteri, campi numerici a zeri e
alfanumerici a spazi, importi in centesimi.

- Addebito sul conto di ciascun contribuente (flag "E", conto della delega)
  o, con addebito unico, sul conto dell'intermediario (flag "Y",
  provvedimento del 21 giugno 2007 prot. 2007/101553).
- Codici identificativi 02, 03 e 07: il secondo soggetto è indicato come
  versante con i dati anagrafici, quindi deve essere in anagrafica; gli
  altri codici come coobbligato.
- Prima della generazione ogni delega è verificata (stato, errori, dati
  anagrafici obbligatori, conto); quelle con problemi non sono
  selezionabili.

Il file va controllato e autenticato con il software dell'Agenzia
(Desktop Telematico) e trasmesso con Entratel; resta salvato nell'invio per
essere scaricato di nuovo. Se viene scartato, "Annulla invio" riporta le
deleghe a confermate.

**CBI (home banking)**: il file F24 per l'home banking segue gli standard
del Consorzio CBI, la cui documentazione è riservata alle banche e alle
aziende aderenti e non è pubblica; il flusso non è implementato. Con la
specifica (dalla banca dello studio o dal Consorzio CBI) si aggiunge un
generatore accanto a `GeneratoreFileF24`, riusando ripartizione e
controlli.

## Versamenti generati dagli altri moduli

Un modulo che calcola versamenti (es. IMU) li registra nell'archivio del
contribuente con `Services\ArchivioVersamenti::registra`, indicando
origine (il modulo) e riferimento (es. id del calcolo); ogni versamento ha
la sua data di versamento:

```php
$acconto = new Versamento($dataMapper);
$acconto->scadenza = new SismaDate('2026-06-16');
$acconto->descrizione = 'IMU acconto 2026';
$acconto->sezione = SezioneF24::tributiLocali;
$acconto->codiceTributo = (new CodiceTributoModel($dataMapper))->getByCodice(SezioneCodiceTributo::tributiLocali, '3918');
$acconto->codiceEnte = 'H501';
$acconto->acconto = true;
$acconto->numeroImmobili = 1;
$acconto->annoRiferimento = 2026;
$acconto->importoDebito = 412.00;
$saldo = ...; // scadenza 16/12
$esito = (new ArchivioVersamenti($dataMapper))->registra($soggetto, 'Imu', 'calcolo-15', [$acconto, $saldo]);
```

- i versamenti restano da versare finché non si genera la delega della loro
  data; con `$destinazione` vanno direttamente in una delega in bozza
  (usato da ravvedimento e sanzioni civili);
- una nuova registrazione con gli stessi origine e riferimento
  **sostituisce** i versamenti precedenti: quelli da versare e quelli in una
  delega in bozza (i nuovi vanno nella stessa delega); un elenco vuoto li
  rimuove;
- i versamenti già in una delega confermata, trasmessa o quietanzata non si
  toccano: l'esito (`EsitoRegistrazione::$differenze`) riporta per ogni data
  l'importo in delega e quello ricalcolato, da gestire a mano (riportando la
  delega in bozza o con un versamento integrativo);
- i versamenti non validi sollevano `InvalidArgumentException` (stesse
  regole dei form, compresi i formalismi dei codici);
- un rigo IMU dell'abitazione principale (3912) può riportare in
  `Versamento::$detrazioneImu` la detrazione già sottratta dall'importo: la
  delega ne indica la somma nel campo "detrazione" della sezione
  (`GenerazioneDeleghe::aggiornaDetrazioneImu`, ricalcolata quando un rigo
  con detrazione entra o esce dalla delega).
- un versamento che la norma vuole con un secondo codice fiscale (es.
  l'imposta di successione versata dall'erede con il codice fiscale del
  defunto e il codice identificativo 08, modulo Successioni) lo indica in
  `Versamento::$codiceFiscaleCoobbligato` e `$codiceIdentificativo`: la
  generazione delle deleghe lo mette solo in una delega con lo stesso
  secondo soggetto (creata con quei dati se manca) e non vi aggiunge i
  versamenti senza secondo soggetto (`GenerazioneDeleghe::isCompatibile`);
  il controllo della delega segnala se il secondo codice fiscale viene
  cambiato.

Nell'interfaccia i versamenti generati mostrano l'origine e non si
modificano; si possono eliminare (un nuovo calcolo li ripropone).

## Tabelle di riferimento

```bash
# aggiorna il database dalle fonti
docker exec comm_suite_php sh -c 'cd /var/www/html && sisma tabelle-f24'

# rigenera lo script SQL versionato nel modulo
docker exec comm_suite_php sh -c 'cd /var/www/html && sisma tabelle-f24 --sql=Deleghe/Database/dati-riferimento.sql'
```

Fonti:

- **archivio delle tabelle F24** dell'Agenzia delle Entrate ("Tabelle
  zippate" della pagina
  [Riepilogo tabelle](https://www.agenziaentrate.gov.it/portale/strumenti/codici-attivita-e-tributo/f24-codici-tributo-per-i-versamenti/tabelle-dei-codici-tributo-e-altri-codici-per-il-modello-f24/riepilogo-tabelle)):
  il comando trova nella pagina il link al RAR più recente, lo estrae e
  legge i file xls/xlsx (`LettoreXls` per il formato binario BIFF5/BIFF8,
  `LettoreXlsx` di Anagrafica): codici erariali, regionali e locali,
  IMU e tributi locali, F24 ELIDE, accise con i formalismi, elenco dei
  codici pagabili con i crediti PA, causali INPS e degli altri enti, sedi
  INPS, INAIL ed ENPALS, altri enti, T0, T1, T2, T6, tipi ELIDE, formati
  della matricola INPS (da cui si ricava l'espressione regolare di
  controllo);
- **tasso di riferimento BCE** (operazioni di rifinanziamento principali,
  per le sanzioni civili): serie `FM.B.U2.EUR.4F.KR.MRR_FR.LEV` dalla
  [API dati della BCE](https://data.ecb.europa.eu/data/datasets/FM/FM.B.U2.EUR.4F.KR.MRR_FR.LEV),
  in CSV; `--bce=FILE|URL` per usare una copia;
- **uffici**: tabella del servizio "Ricerca codici tributo" (pagina HTML,
  `--copia=CARTELLA` per riusarla);
- **Database/sorgenti** (nessuna fonte leggibile da programma):
  `tassi-interesse-legale.csv` (decreti annuali del MEF, art. 1284 c.c.) e
  `regole-ravvedimento.csv` (codici di sanzione e interessi per tributo,
  con la risoluzione di riferimento); il comando verifica che i codici
  esistano nelle tabelle dell'Agenzia. Vanno aggiornati a ogni nuovo
  decreto o risoluzione.

`--archivio=CARTELLA|FILE|URL` usa un archivio già scaricato. Il comando si
ferma senza modificare nulla se nomi dei file, intestazioni o formalismi
cambiano o se una tabella risulta incompleta. Le righe non più presenti
nelle fonti restano con `in_uso = 0`; lo script SQL è idempotente.

Alcune descrizioni delle tabelle dell'Agenzia sono corrotte da conversioni
di codifica (es. "dï¿‚ï¾’imposta"): `LettoreFoglio` ricostruisce il testo
originale; dove la fonte ha perso dei byte resta un carattere errato (es.
"ANNUALITÎ").

## Multi-studio

Deleghe, versamenti, crediti, intermediari e invii appartengono a uno
studio; i controller verificano l'accesso (`DelegaPermission`,
`SoggettoPermission` di Anagrafica per versamenti e crediti, controllo
dello studio per intermediari e invii).

## Da completare

- limiti alle compensazioni (tetto annuo, visto di conformità oltre le
  soglie): oggi non verificati, solo la data da cui il credito è
  utilizzabile;
- standard CBI per l'home banking (specifica non pubblica);
- sanzioni delle casse professionali (regolamenti delle singole casse);
- F24 EP (riservato agli enti pubblici, non ai clienti di uno studio);
- tabella T3 (enti territoriali per prestiti obbligazionari) e T5 (enti
  convenzionati), oggi controllati solo nel formato.
