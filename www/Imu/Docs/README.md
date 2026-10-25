# Modulo IMU

Calcolo dell'imposta municipale propria (art. 1, commi 738-783, L.
160/2019) dei soggetti dello studio: per ogni contribuente e anno il
prospetto di calcolo per immobile, i versamenti di acconto e saldo (o le tre
rate degli enti non commerciali), registrati nell'archivio di Deleghe F24 da
cui si generano le deleghe, e la dichiarazione IMU o IMU ENC con il file
telematico.

Il modulo non ha un archivio immobili proprio: usa gli immobili
dell'Anagrafica posseduti dal soggetto (`Possesso` con titolo, quota e
utilizzo, `DatiCatastali` storicizzati). Aggiunge solo ciò che serve
all'imposta: aliquote dei comuni, caratteristiche IMU dell'immobile,
agevolazioni del possessore, aliquota scelta per l'immobile, costi contabili
dei fabbricati D non accatastati, utilizzo degli immobili degli enti non
commerciali, acconti versati fuori dalla procedura e dichiarazioni.

## Flusso di lavoro

"IMU" apre l'elenco dei contribuenti (di default i soli clienti) per anno;
la scheda del contribuente mostra:

- **immobili posseduti nell'anno**, con classamento e possessi
  dell'Anagrafica (si modificano lì), caratteristiche e agevolazioni IMU,
  aliquote del comune e aliquota scelta; dal menu del rigo si inseriscono
  caratteristiche, agevolazioni e aliquota dell'anno, i costi contabili (per
  i fabbricati a valore contabile) e l'utilizzo non commerciale (per gli
  enti non commerciali);
- **prospetto di calcolo** dell'ultima elaborazione, con avvisi (prospetto
  del comune mancante, pertinenze eccedenti, dati catastali assenti,
  aliquote differenziate possibili, ...) e differenze con i versamenti già
  in deleghe confermate;
- **acconti versati fuori dalla procedura**;
- **versamenti registrati** per le deleghe F24, con lo stato (da versare o
  nella delega);
- **dichiarazioni IMU** dell'anno.

"Calcola e registra i versamenti" (eventualmente in unica soluzione)
elabora l'anno; "Stampa il prospetto" produce il prospetto da consegnare al
contribuente. Le deleghe si generano poi in Deleghe F24, scheda "Da
versare" del contribuente. "Proponi le dichiarazioni dell'anno" crea le
bozze delle dichiarazioni dovute.

"Aliquote dei comuni" elenca i comuni degli immobili dello studio con le
aliquote applicate nell'anno: si importano i prospetti pubblicati o si
inseriscono le aliquote dello studio.

## Installazione

Il modulo è registrato in `MODULE_FOLDERS` e in `ModuloSuite`; richiede
CommSuite, Anagrafica e Deleghe. Script da eseguire come root dopo quelli
di Deleghe:

1. `Imu/Database/schema.sql`
2. `Imu/Database/dati-riferimento.sql` (coefficienti dei fabbricati D e
   comuni con terreni esenti, generato da `sisma tabelle-imu --sql`)

Dopo l'installazione importare i prospetti delle aliquote (vedi sotto). Le
fixture (`sisma fixtures`) aggiungono agli immobili demo dell'Anagrafica:

- la villa A/8 con box C/6 di Bianchi a Roma, acquistati il 10 marzo 2026
  (abitazione principale con detrazione, mesi di possesso);
- il capannone D/7 di Alfa a Brescia (quota dello Stato), un lotto di Alfa
  divenuto area fabbricabile nel 2025 e un magazzino D di Alfa non
  accatastato a valore contabile, con costo di acquisto 2023 e costi
  incrementativi 2026;
- l'associazione culturale "Il Faro" (ente non commerciale, rappresentata
  da Bianchi) con la sede B/5 a Roma utilizzata in parte per attività
  didattiche e culturali con modalità commerciali.

Con i dati demo già esistenti si calcolano anche il negozio C/1 in
comproprietà di Verdi e Neri a Milano, il terreno di Verdi e l'abitazione
principale esente di Rossi.

## Modello dei dati

| Entità | Contenuto | Storicizzata |
|---|---|---|
| `ProspettoAliquote` | aliquote di un comune (o di un ex comune) per un anno: pubblicate dal Dipartimento delle Finanze (senza studio, comuni a tutti gli studi) o inserite dallo studio (prevalgono); aliquote di base per fattispecie, assimilazione per anziani e disabili, detrazione, importo minimo, esenzioni del comune | per anno |
| `AliquotaDifferenziata` | aliquote del prospetto con condizioni (categoria, contratto, requisiti), con la descrizione del prospetto | per anno |
| `CaratteristicheImu` | dell'immobile, per tutti i possessori: interesse storico, inagibilità, ruralità strumentale, fabbricato merce, area fabbricabile con valore venale, fabbricato D a valore contabile, ex comune, esenzione oggettiva | sì |
| `AgevolazioneImu` | del possessore sull'immobile: comodato ai parenti, conduzione da coltivatore diretto o IAP, assimilazione all'abitazione principale, pensionato estero | sì |
| `AliquotaApplicata` | aliquota dell'immobile del contribuente per l'anno: una differenziata del prospetto o un'aliquota indicata dallo studio | per anno |
| `CostoContabileImu` | costi di acquisizione e incrementativi del fabbricato a valore contabile, per anno di sostenimento | per anno |
| `UtilizzoMistoEnc` | utilizzo dell'immobile dell'ente non commerciale per le attività del comma 759, lett. g): attività, esenzione totale o rapporti di imponibilità del D.M. 200/2012 | per anno |
| `AccontoVersatoImu` | acconto per comune e codice tributo versato senza la procedura | per anno |
| `CalcoloImu`, `RigoCalcoloImu` | ultima elaborazione per contribuente e anno con il prospetto per immobile e periodo, avvisi e differenze | no |
| `DichiarazioneImu`, `RigoDichiarazioneImu` | dichiarazione per contribuente, anno e comune (modello IMU/IMPi o ENC, nuova o sostitutiva, bozza o presentata) con i quadri degli immobili e, per gli ENC, i quadri C e D | no |
| `CoefficienteFabbricatiD`, `ComuneEsenzioneTerreni` | tabelle di riferimento: coefficienti del decreto annuale; comuni montani, parzialmente montani e isole minori | no |

Le fattispecie di base sono l'enum `FattispecieImu` (voci del prospetto,
aliquota di legge, codice tributo: 3912 abitazione principale A/1, A/8, A/9,
3913 rurali strumentali, 3914 terreni, 3916 aree fabbricabili, 3918 altri
fabbricati, 3925/3930 gruppo D Stato/comune, 3939 fabbricati merce
2020-2021); `EsenzioneImu`, `AssimilazioneAbitazione`, `AttivitaEnc`,
`CaratteristicaDichiarata`, `CodiceCarica` e gli altri sono enum per lo
stesso criterio di Anagrafica (vocabolari chiusi fissati per legge o dalle
specifiche tecniche). Prospetti, coefficienti ed elenchi dei comuni sono
entità: cambiano ogni anno o per atto amministrativo e si aggiornano dalla
fonte.

Perché caratteristiche e agevolazioni sono separate: inagibilità, interesse
storico o valore dell'area sono dell'immobile e valgono per tutti i
comproprietari (si indicano una volta); comodato, conduzione agricola,
assimilazioni e pensionato estero dipendono dal possessore. L'utilizzo
(abitazione principale, pertinenza, locato a canone concordato, ...) resta
quello del possesso in Anagrafica, già storicizzato.

L'ente non commerciale è un flag dei dati fiscali del soggetto in
Anagrafica (`DatiFiscali::$enteNonCommerciale`, solo per gli enti): serve
anche ad altri adempimenti, non solo all'IMU.

## Calcolo

`Services\CalcoloImposta` per ogni possesso dell'anno divide l'immobile in
periodi con possesso, classamento, caratteristiche e agevolazioni costanti:

- **soggetto passivo** (comma 743): proprietario, usufruttuario, titolare di
  uso, abitazione, enfiteusi, superficie, locatario finanziario; il nudo
  proprietario no;
- **mesi** (comma 761, `MesiPossesso`): conta il mese posseduto per più
  della metà dei giorni; il giorno del trasferimento è dell'acquirente, al
  quale resta il mese a parità di giorni; le variazioni a metà mese seguono
  la stessa regola;
- **classamento** (commi 745 e 746, `ClassamentoImu`): valgono la rendita e
  il reddito dominicale vigenti al 1° gennaio dell'anno; le variazioni di
  rendita per interventi edilizi valgono dall'ultimazione dei lavori o, se
  antecedente, dall'utilizzo, cioè dalla decorrenza della versione in
  Anagrafica con causale "Variazione per interventi edilizi"; le altre
  variazioni (e quelle dei terreni) dal 1° gennaio successivo, con nota nel
  rigo e avviso. Una variazione superata da un'altra efficace prima non si
  applica. La causale è in Anagrafica: le versioni esistenti prima della
  0.3.0 la ricevono dallo script di aggiornamento, che elenca quelle da
  verificare. La rendita proposta con il Docfa e rettificata dall'Agenzia
  si registra rettificando la versione in Anagrafica: una volta notificata
  vale anche per gli anni precedenti ancora aperti (Cass. SS.UU. n.
  3160/2011, circolare n. 7/E del 2022), e il ricalcolo ne mostra la
  differenza rispetto ai versamenti già eseguiti;
- **classamento presunto**: per i mesi di possesso anteriori ai primi dati
  catastali registrati si usano questi, con nota nel rigo e avviso da
  verificare sulla visura storica (spesso la decorrenza in Anagrafica è
  quella della visura o dell'inserimento, non quella catastale); non per i
  fabbricati D a valore contabile, la cui rendita vale dall'attribuzione;
- **base imponibile** (commi 745-746, `ValoreImmobile`): rendita rivalutata
  del 5% per il moltiplicatore (160 gruppo A escluso A/10 e C/2, C/6, C/7;
  140 gruppo B e C/3, C/4, C/5; 80 A/10 e D/5; 65 gruppo D; 55 C/1); reddito
  dominicale rivalutato del 25% per 135; valore venale delle aree
  fabbricabili; riduzione del 50% per interesse storico, inagibilità e
  comodato ai parenti (comma 747, cumulabili);
- **fabbricati D a valore contabile** (comma 746, terzo periodo): fabbricati
  del gruppo D senza rendita, interamente posseduti da imprese e
  distintamente contabilizzati; valore al 1° gennaio pari alla somma dei
  costi per i coefficienti del decreto dell'anno (`CoefficienteFabbricatiD`);
  i costi sostenuti nell'anno contano dall'anno successivo, salvo che
  l'immobile sia acquistato nell'anno; con la rendita attribuita si passa
  alla rendita;
- **esenzioni**: abitazione principale e pertinenze non di lusso (comma
  740) e assimilate (comma 741, lett. c), fabbricati merce dal 2022 (comma
  751), terreni e aree di coltivatori diretti e IAP (commi 741 lett. d e
  758 lett. a), terreni delle isole minori e dei comuni montani (comma 758,
  lett. b e d, riconosciuti dal comune dell'immobile, vedi sotto), gruppo E
  (comma 759), esenzioni oggettive indicate; fabbricati senza rendita
  (gruppo F) imponibili solo come area fabbricabile;
- **enti non commerciali** (comma 759, lett. g): immobili utilizzati solo
  con modalità non commerciali esenti; con utilizzo misto imponibile la
  percentuale commerciale dei rapporti del D.M. 200/2012 (superficie,
  soggetti, giorni; per la didattica il rapporto tra corrispettivo medio e
  costo medio per studente, con esenzione se il primo è inferiore);
- **pertinenze** (comma 741, lett. b): solo C/2, C/6, C/7, una per
  categoria (quella con rendita maggiore), seguono l'abitazione principale
  dello stesso comune;
- **riduzioni dell'imposta**: canone concordato al 75% (comma 760);
  pensionato estero al 50% (L. 178/2020, c. 48; 37,5% per il 2022);
- **aliquota**: quella scelta per l'immobile nell'anno, la differenziata
  riconosciuta dai dati (vedi sotto) o quella della fattispecie; per il
  gruppo D lo 0,76% è dello Stato (comma 753);
- **detrazione** (comma 749): 200 euro (o quanto deliberato) rapportati ai
  mesi e ripartiti tra i possessori che vi dimorano in proporzione alle
  quote, fino a concorrenza dell'imposta dell'abitazione e delle pertinenze;
- **acconto** (comma 762): imposta del primo semestre con aliquote e
  detrazione dell'anno precedente.

`Services\ElaborazioneImu` salva il prospetto e registra con
`ArchivioVersamenti` un rigo F24 per comune e codice tributo (origine
`Imu`, riferimento `imu-<anno>`): acconto al 16 giugno e saldo al 16
dicembre (primo giorno lavorativo), oppure tutto al 16 giugno in unica
soluzione; importi arrotondati all'euro (art. 1, c. 166, L. 296/2006);
numero immobili, anno, acconto/saldo e detrazione nel rigo (riportata in
delega). Il saldo è l'imposta annua meno l'acconto già in una delega
confermata o indicato tra gli acconti versati, se c'è, altrimenti meno
quello calcolato; un acconto eccedente è segnalato. Nessun versamento per
il comune se l'imposta annua non supera l'importo minimo (12 euro se il
comune non ne indica un altro). Un nuovo calcolo sostituisce i versamenti
da versare o in bozza; le differenze con quelli in deleghe confermate,
trasmesse o quietanzate restano nel calcolo (ravvedimento e integrazioni si
gestiscono in Deleghe).

**Acconti versati fuori dalla procedura**: l'acconto pagato dal
contribuente da solo, da un altro intermediario o prima di usare il modulo
si indica per comune e codice tributo; non si registra tra i versamenti e il
saldo si calcola su quell'importo. Per il 2020 l'acconto era la metà di IMU
e TASI 2019 (comma 762): non ricostruibile dai dati, il calcolo lo
segnala e va indicato qui.

**Enti non commerciali** (comma 763): tre rate, le prime due (16 giugno e 16
dicembre) pari ciascuna al 50% dell'imposta corrisposta per l'anno
precedente, la terza a conguaglio entro il 16 giugno dell'anno successivo.
L'imposta corrisposta è quella dei versamenti registrati dalla procedura per
l'anno precedente; in mancanza quella del calcolo salvato per quell'anno;
senza nemmeno quello le prime due rate sono pari all'imposta del primo
semestre, con avviso (vanno indicati gli acconti effettivamente versati).
Rate superiori all'imposta dovuta producono un credito da indicare nella
dichiarazione ENC, compensabile con i versamenti successivi allo stesso
comune.

## Aliquote dei comuni

Dal 2025 le aliquote sono quelle del prospetto approvato con il modello
ministeriale (D.M. 7 luglio 2023, allegato A, e successivi) e pubblicato
dal Dipartimento delle Finanze entro il 28 ottobre (commi 757 e 767). Ordine
di applicazione (`Services\AliquoteComune`): aliquote dello studio,
prospetto pubblicato, poi in mancanza (comma 767, testo vigente verificato
su Normattiva) le aliquote di base di legge dal 2025, primo anno di obbligo
del prospetto, e fino al 2024 quelle dell'anno precedente. Un prospetto
pubblicato dopo il 28 ottobre è segnalato (non è efficace per l'anno).

```bash
# importa i prospetti dell'anno per i comuni degli immobili di tutti gli studi
docker exec comm_suite_php sh -c 'cd /var/www/html && sisma aliquote-imu --anno=2026'

# solo alcuni comuni, salvando i file scaricati
docker exec comm_suite_php sh -c 'cd /var/www/html && sisma aliquote-imu --anno=2026 --comuni=F205,H501 --copia=/tmp/prospetti-imu'
```

La stessa importazione per i comuni dello studio è in "Aliquote dei
comuni". Fonte: servizio "Ricerca delibere" del Dipartimento delle Finanze
(https://www1.finanze.gov.it/finanze2/dipartimentopolitichefiscali/fiscalitalocale/nuova_imu/sceltaregione.htm):

- elenco dei prospetti dell'anno: CSV "Delibere aliquote"
  (`dati/download_csv_IMU.php?tipo=D&anno=`), con delibera, data di
  pubblicazione, ex comune e nome del PDF; per comune ed ex comune vale
  l'ultima pubblicazione (errata corrige);
- PDF del prospetto: il link richiede una chiave che il CSV non riporta,
  si ricava dalla pagina dei risultati del comune (`risultato.htm`);
- testo del PDF con `pdftotext -layout` (poppler-utils, nel Dockerfile),
  letto da `LettoreProspetto`: voci di base per etichetta, assimilazione
  per anziani e disabili, aliquote differenziate (fattispecie, condizioni,
  aliquota), esenzioni del comune. Se intestazione o voci di base non sono
  riconosciute il comune è segnalato e non importato.

**Comuni nati da fusione**: il comune che applica aliquote diverse negli ex
comuni ha un prospetto per ex comune; si importano tutti e nelle
caratteristiche IMU dell'immobile si indica l'ex comune (elenco proposto dai
prospetti). Senza l'indicazione vale il primo prospetto, con avviso.

**Aliquote differenziate**: il prospetto non è disponibile in formato
strutturato, le condizioni sono testo. `CompatibilitaDifferenziate` ricava
dalla descrizione le categorie catastali e gli utilizzi (locato, canone
concordato, comodato, abitazione principale, ...) e li confronta con i
dati dell'immobile; le condizioni che i dati non permettono di verificare
(contratto registrato, requisiti del soggetto, destinazione d'uso,
collocazione, ...) restano "da verificare". Se una sola differenziata ha
tutte le condizioni verificate è applicata come proposta; quelle
compatibili con condizioni da verificare sono suggerite negli avvisi. La
scelta resta dello studio (aliquota dell'immobile); l'anno successivo il
calcolo propone la differenziata del nuovo prospetto con la stessa
descrizione. Una reimportazione aggiorna il prospetto riconoscendo le
differenziate per fattispecie e descrizione; le scelte di una differenziata
non più presente diventano aliquote dello studio "da verificare".
Detrazione e importo minimo non sono nel prospetto: valgono 200 e 12 euro,
modificabili con le aliquote dello studio.

## Tabelle di riferimento

```bash
# aggiorna coefficienti e comuni esenti nel database
docker exec comm_suite_php sh -c 'cd /var/www/html && sisma tabelle-imu'

# rigenera lo script dati-riferimento.sql
docker exec comm_suite_php sh -c 'cd /var/www/html && sisma tabelle-imu --sql=Imu/Database/dati-riferimento.sql'
```

- **Coefficienti dei fabbricati D** (comma 746): decreto annuale del MEF;
  i valori 2020-2026 sono in `Database/sorgenti/coefficienti-fabbricati-d.csv`
  con il riferimento al decreto, da aggiornare ogni anno. Senza i
  coefficienti dell'anno il calcolo lo segnala.
- **Comuni montani e parzialmente montani** (comma 758, lett. d): elenco
  della circolare n. 9 del 14 giugno 1993 (G.U. n. 141 del 18 giugno 1993,
  suppl. ord.), ancora vigente (risoluzione n. 1/DF del 1° aprile 2026),
  letto dal testo della Gazzetta Ufficiale e abbinato ai codici catastali
  dell'Anagrafica per denominazione e provincia del 1993 (anche dei comuni
  poi soppressi o fusi, con il codice del comune attuale). Gli abbinamenti
  non automatici sono in `Database/sorgenti/abbinamenti-circolare-9-1993.csv`.
  Per i comuni parzialmente montani ("PD") l'esenzione riguarda solo una
  parte del territorio, individuata dai fogli: il calcolo la segnala e va
  confermata sull'immobile (esenzione oggettiva).
- **Isole minori** (comma 758, lett. b): allegato A della L. 448/2001, in
  `Database/sorgenti/isole-minori.csv` (comuni interamente o parzialmente
  insulari).

## Dichiarazione IMU

Dal 2026 il comma 768-bis (D.Lgs. 7 agosto 2026, n. 147, che abroga i
commi 769 e 770) prevede la dichiarazione solo in via telematica entro il 30
giugno dell'anno successivo, nei casi delle istruzioni ministeriali; gli
enti non commerciali la presentano ogni anno. Fino all'approvazione di
nuovi modelli restano in uso quelli dei D.M. del 2022-2024 (IMU/IMPi del 24
aprile 2024 e IMU ENC).

`Services\PropostaDichiarazione` confronta i dati dell'anno con quelli
precedenti e crea per comune la bozza con un quadro per ogni vicenda:

- riduzioni: interesse storico (inizio e termine), fine dell'inagibilità,
  comodato ai parenti, pensionato estero;
- informazioni non conoscibili dal comune: aree fabbricabili (acquisto,
  cessione, variazione del valore, terreno divenuto area), locazione
  finanziaria, fabbricati D a valore contabile (costi incrementativi),
  conduzione da coltivatori diretti e IAP, inizio e fine di un'esenzione
  (occupazione abusiva), fabbricati merce, alloggi sociali e del personale
  delle Forze armate;
- acquisti e cessioni degli altri immobili non si dichiarano (atti con
  modello unico informatico).

Per gli enti non commerciali la dichiarazione è annuale con tutti gli
immobili: quadro A gli imponibili, quadro B quelli con utilizzo per le
attività del comma 759, lett. g) (con i rapporti di imponibilità), quadri C
e D con imposta dovuta, rate versate, eccedenze, crediti a rimborso o in
compensazione, ricalcolati dal calcolo e dai versamenti.

La bozza si completa nella scheda della dichiarazione (frontespizio con
dichiarante e codice di carica proposti dall'Anagrafica per società ed enti,
intermediario dello studio, quadri modificabili e rinumerabili). "File
telematico" genera il file secondo le specifiche tecniche 2024
(`Services\Telematico`, record da 1900 caratteri: A, B, D, Z per IMU/IMPi;
A, B, C, D, E, Z per ENC), dopo il controllo dei dati obbligatori; va
controllato con il modulo di controllo del Dipartimento delle Finanze e
trasmesso con Entratel o Fisconline. Una dichiarazione segnata come
presentata (data e protocollo) non è più modificabile; per correggerla si
crea la sostitutiva, che va trasmessa integralmente.

## Multi-studio

Calcoli, scelte di aliquota, caratteristiche, agevolazioni, costi, utilizzi
e dichiarazioni riguardano soggetti e immobili dello studio
(`SoggettoPermission` e `ImmobilePermission` di Anagrafica). I prospetti
pubblicati e le tabelle di riferimento sono comuni e in sola lettura; le
aliquote inserite da uno studio sono visibili e modificabili solo da quello
studio.

## Fonti

- L. 27 dicembre 2019, n. 160, art. 1, commi 738-783 (Normattiva, testo
  vigente);
- prospetti delle aliquote e specifiche tecniche delle dichiarazioni IMU/IMPi
  e IMU ENC: Dipartimento delle Finanze (www.finanze.gov.it, fiscalità
  locale);
- decreti annuali dei coefficienti dei fabbricati del gruppo D;
- circolare n. 9/1993 (Gazzetta Ufficiale) e risoluzione n. 1/DF del 1°
  aprile 2026;
- L. 28 dicembre 2001, n. 448, allegato A (isole minori);
- art. 74, c. 1, L. 21 novembre 2000, n. 342 e Agenzia delle Entrate,
  circolare n. 7/E del 17 marzo 2022 (efficacia e utilizzabilità della
  rendita rettificata);
- D.M. 19 novembre 2012, n. 200 (immobili degli enti non commerciali).

## Da completare

- verifica dei file telematici con il modulo di controllo del Dipartimento
  delle Finanze: in particolare anno della dichiarazione (oggi l'anno della
  data della dichiarazione) e periodo d'imposta nel record B;
- nuovi modelli di dichiarazione attuativi del comma 768-bis, quando
  approvati;
- delimitazione per fogli dei comuni parzialmente montani (oggi esenzione
  da confermare sull'immobile);
- condizioni delle aliquote differenziate non ricavabili dai dati
  (contratto registrato, requisiti soggettivi, collocazione): richiederebbero
  dati strutturati dal portale del federalismo fiscale.
