# Modulo Successioni

Dichiarazione di successione e domanda di volture catastali (artt. 28 e
seguenti, D.Lgs. 346/1990, "TUS") dei soggetti dello studio: beneficiari,
attivo e passivo dall'Anagrafica, liquidazione delle imposte del quadro EF
con l'autoliquidazione dell'imposta di successione (successioni aperte dal
1° gennaio 2025, D.Lgs. 139/2024), versamenti F24 dei beneficiari
registrati in Deleghe F24, allegati, file telematico (fornitura XML SUC13)
e volture riportate nell'archivio immobili dell'Anagrafica.

Il modulo non ha un'anagrafica propria: defunto, eredi, legatari,
dichiarante, conti e immobili sono quelli dell'Anagrafica.

## Flusso di lavoro

Si entra dal defunto: "Successioni" elenca le persone fisiche dello studio
con la data di decesso (indicata nella scheda anagrafica, campo "Data di
decesso"); con "Cerca tra tutte le persone fisiche" si apre anche chi non
l'ha ancora. Dalla scheda del defunto "Nuova dichiarazione di successione"
crea la bozza con i dati proposti dall'Anagrafica:

- stato civile (per i coniugati la comunione, da confermare), comune di
  residenza come luogo del decesso, intermediario predefinito dello studio
  con l'impegno a trasmettere;
- attivo immobiliare: i possessi del defunto in vigore alla data del
  decesso diventano beni dei quadri EB (terreni) ed EC (fabbricati) con
  diritto, quota e valore catastale proposto; usufrutto, uso e abitazione
  si estinguono con la morte e non entrano nell'attivo.

La dichiarazione ha sei schede:

- **Frontespizio**: tipo (prima, sostitutiva, integrativa), devoluzione per
  legge e/o per testamento con gli estremi di pubblicazione e
  registrazione, dati del defunto, dichiarante e codice di carica,
  trasmissione; anche i dati del quadro EH (luogo del decesso, separazione,
  prima casa del dichiarante, estremi per esenzioni e riduzioni).
- **Eredi e legatari (EA)**: soggetti dell'Anagrafica con tipo, grado di
  parentela, disabilità grave, rinuncia con gli estremi di registrazione,
  successione per rappresentazione, parte riservata al trust (codice
  fiscale del trust e beneficiario finale). L'ordine determina rigo e
  modulo del quadro (tre beneficiari per modulo).
- **Attivo e passivo**: beni dei quadri EB, EC (catasto ordinario), EL, EM
  (catasto tavolare), EN (aziende, con gli immobili aziendali collegati),
  EO (azioni, obbligazioni, titoli, quote), EP (aeromobili), EQ (navi e
  imbarcazioni) ed ER (crediti, conti e depositi, denaro, altri beni) con
  la devoluzione ai beneficiari; terreni e fabbricati all'estero (Stato,
  codice dello Stato, località e indirizzo, senza l'archivio immobili);
  per gli immobili con i dati
  dell'intestatario discordanti o passaggi senza atti legali, gli estremi
  degli atti di provenienza (quadro EI); passività del quadro ED con la
  ripartizione. "Proponi la devoluzione per legge" applica a tutti i beni le
  quote del codice civile (`Services\QuoteLegittime`).
- **Liquidazione e F24**: riepilogo (EE), imposte (EF), imposta di
  successione per beneficiario con i passaggi di calcolo, piano dei
  versamenti e versamenti registrati in Deleghe F24.
- **Allegati (EG)**: documenti PDF/A o TIFF inseriti nel file telematico.
- **Volture**: dopo la presentazione, proposta delle variazioni dei
  possessi in Anagrafica, applicata su conferma.

Dopo la presentazione (con data, protocollo, volume e numero attribuiti
dall'ufficio) "Crea la sostitutiva" copia la dichiarazione con tutti i suoi
dati in una nuova bozza: estremi della prima e imposte già liquidate come
versate.

Il riquadro "Da completare" elenca i controlli non superati
(`Services\ControlloDichiarazione`): finché ci sono errori il file
telematico non si genera e la dichiarazione non si segna come presentata.
Una dichiarazione presentata non si modifica; si può riportare in bozza.

## Installazione

Il modulo è registrato in `MODULE_FOLDERS` e in `ModuloSuite`; richiede
CommSuite, Anagrafica e Deleghe. Script da eseguire come root dopo quelli
di Deleghe:

1. `Successioni/Database/schema.sql`
2. `Successioni/Database/dati-riferimento.sql` (uffici di registrazione e
   di trascrizione, comuni catastali di Trento e Bolzano, generato da
   `sisma specifiche-successioni --sql`)

Il modulo si appoggia anche a colonne e tabelle di altri moduli, già negli
schemi: `soggetto.data_decesso`, i dati tavolari di `immobile` e la tabella
`identificativo_graffato` (Anagrafica), `versamento.codice_fiscale_coobbligato`
/ `versamento.codice_identificativo` (Deleghe, vedi "Versamenti F24").

Le fixture (`sisma fixtures`) creano la successione demo di Giuseppe
Esposito, deceduto a Napoli il 12 settembre 2025: metà dell'appartamento
(con la prima casa per la moglie) e il terreno di Caserta dall'Anagrafica,
il conto corrente, le azioni legate per testamento al nipote, le spese
funerarie; eredi per legge la moglie e i due figli (entro la franchigia),
legatario il nipote (6%, 3.000 euro di imposta) che versa a rate. Ci sono
anche la ferramenta del defunto (quadro EN) passata ai figli con
l'agevolazione D, con il negozio di Trento del catasto tavolare (EM) come
bene aziendale, la barca a vela (EQ) e, per il terreno intestato in
catasto al venditore, gli estremi della trascrizione dell'acquisto (EI).

## Modello dei dati

| Entità | Contenuto |
|---|---|
| `DichiarazioneSuccessione` | defunto (`Soggetto`), tipo ed estremi della prima dichiarazione, devoluzione e testamento, stato civile e luogo del decesso, dichiarante e carica, rappresentato, intermediario e impegno, conto di addebito (`ContoCorrente`), tempistica e rateazione dell'imposta di successione, pagamento anticipato del trust, importi già versati e crediti (sostitutive), credito d'imposta della sezione III del quadro EH con gli estremi di acquisto e vendita, circoscrizioni, dati del quadro EH, stato e presentazione, volture applicate |
| `BeneficiarioSuccessione` | rigo del quadro EA: soggetto, tipo, grado di parentela, disabilità e certificatore, rinuncia con estremi, rappresentato e suo grado, codice fiscale del trust e beneficiario finale |
| `BeneSuccessione` | bene dell'attivo: quadro, immobile dell'Anagrafica (EB, EC, EL, EM) e azienda di cui è bene aziendale, diritto e quota del defunto, valore, valore esente (EO), valore nella successione precedente, natura e tipologia del terreno, diritto di abitazione del coniuge, discordanza e passaggi senza atti (EI), bene estero e imposta estera, descrizione e dati del titolo, del cespite, dell'azienda (inventario, deposito), dell'aeromobile o della nave, Stato, codice, località e indirizzo dell'immobile all'estero |
| `EstremoVoltura` | quadro EI: trascrizione (servizio di pubblicità immobiliare, registro particolare, anno) o registrazione (ufficio, anno, volume, numero) di provenienza dell'immobile |
| `DevoluzioneSuccessione` | quota del bene al beneficiario: quota, diritto, valore della quota, agevolazione, riduzione art. 25 |
| `PassivitaSuccessione`, `RipartizionePassivita` | quadro ED: tipo, documento, quota del defunto, valore, cespite collegato; quote sostenute dai beneficiari |
| `AllegatoSuccessione`, `ContenutoAllegato` | quadro EG: tipo di documento, nome, formato e dimensione; il contenuto in base64 è in una tabella separata, letta solo per scaricare l'allegato o generare il file |
| `UfficioRegistrazione`, `UfficioTrascrizione`, `ComuneCatastaleTavolare` | tabelle di riferimento comuni a tutti gli studi, dalle specifiche |

I codici delle specifiche sono **enum** (stesso criterio dell'Anagrafica:
vocabolari chiusi, fissati dalle specifiche, con comportamento):
`GradoParentela` (aliquota e franchigia), `CodiceDiritto` (raccordo con
i titoli di possesso), `Agevolazione` (esenzione, riduzione, effetti sulle
imposte ipotecaria e catastale e sul bollo), `QuadroBene` (diritti e
agevolazioni ammessi), `TipoBeneficiario`, `CaricaDichiarante`,
`TipoPassivita`, `TipoCespite`, `TipoTitolo`, `DirittoAbitazione`,
`TipoAllegato` e gli altri. Gli uffici di registrazione (oltre 1.200 voci,
con i periodi di operatività) sono un'**entità**.

`Soggetto::$dataDecesso` è in Anagrafica: apre la successione e servirà
anche agli altri adempimenti del defunto (dichiarazione dei redditi).

### Catasto tavolare

I dati del libro fondiario sono dell'immobile, in Anagrafica (partita e
corpo tavolare, comune catastale, porzione materiale; la particella si
scrive come numero/denominatore), come foglio e particella: un immobile è
tavolare se è nelle province di Bolzano, Trento, Gorizia o Trieste o se ha
la partita tavolare (alcuni comuni di Belluno, Brescia, Udine e Vicenza) e
va nei quadri EL ed EM. Per Trento e Bolzano il codice del comune catastale
si ricava dal nome con la tabella delle specifiche, senza che l'Anagrafica
dipenda da questo modulo. I terreni tavolari di Trento e Bolzano (e dei
comuni indicati dalle specifiche) non scontano l'imposta catastale.

Gli **immobili graffati** (più particelle o subalterni accatastati come
un'unica unità) sono identificativi dell'immobile in Anagrafica
(`IdentificativoGraffato`); nei quadri EC ed EM si riportano fino a quattro
per rigo, gli altri nel rigo successivo con la casella di continuazione.

Gli **immobili all'estero** (quadri EB ed EC, provincia "EE") non sono
nell'archivio immobili, che riguarda il catasto italiano: il bene ha lo
Stato dell'Anagrafica, il codice dello Stato dell'elenco dei Paesi esteri
delle istruzioni (indicato a mano: l'Agenzia non pubblica la tabella in un
formato importabile), località e indirizzo. Non scontano le imposte
ipotecaria e catastale né la tassa ipotecaria e non ammettono le
agevolazioni prima casa.

### Aziende e beni aziendali

Gli immobili dell'azienda restano nei quadri degli immobili, collegati
all'azienda del quadro EN (casella "bene aziendale" e sezione degli
immobili aziendali dell'azienda). Come prevede il quadro EE, il loro valore
conta solo nell'azienda: sono esclusi dal totale degli immobili e, per
coerenza, dalla quota ereditaria dell'imposta di successione, mentre
scontano le imposte ipotecaria e catastale. Sui beni aziendali sono ammesse
solo le agevolazioni D, G e Q.

## Liquidazione

`Services\Liquidazione` applica le regole di controllo delle specifiche
tecniche (documentazione degli elementi dello schema):

- **imposta ipotecaria e catastale** (sezioni I e II): 2% e 1% sul valore
  delle quote immobiliari con agevolazione assente o A, F, L, N, Q, R,
  minimo 200 euro ciascuna; 200 euro fissi per le agevolazioni G e M
  (ipotecaria) e per ogni immobile prima casa (P, Y, Z senza P, o diritto
  di abitazione del coniuge), estesa a tutti i beneficiari del bene; non
  dovute per C, D, E, H; con soli terreni non edificabili non agevolati,
  minimo per imposta e limite dei valori fiscali (reddito dominicale x
  112,5);
- **tassa ipotecaria** 120 euro (65 senza volture) e **imposta di bollo**
  85 euro per circoscrizione immobiliare, più 32 euro di bollo e 16 di
  tributi speciali per la copia conforme. Come dicono le istruzioni, la
  conservatoria si individua dal codice catastale del comune: le
  circoscrizioni si contano per comune e, se più comuni appartengono alla
  stessa conservatoria, il numero si indica nel pagamento (l'Agenzia non
  pubblica l'elenco dei comuni per conservatoria in un formato
  importabile);
- **imposta di successione** (sezione V-bis, successioni aperte dal 2025),
  per beneficiario secondo la guida dell'Agenzia allegata alle specifiche:
  quota ereditaria QE (esclusi i beni esenti A, C, D, E, G, H, N e il
  denaro), denaro, gioielli e mobilia QDN, passività QP, attivo netto,
  franchigia (1.000.000 coniuge e linea retta, 100.000 fratelli,
  1.500.000 disabili; unica per soggetto), presunzione del 10% su denaro,
  gioielli e mobilia (non per i legatari, né con beni inventariati),
  aliquota (4, 6, 8%; Stato ed enti esenti), riduzioni L, Q, R, F e per
  successioni precedenti (art. 25), detrazione dell'imposta estera. Sotto i
  10 euro non si versa.

Le devoluzioni allo Stato e agli enti (gradi 36-39) non entrano nelle
imposte ipotecaria e catastale. Nel quadro EE non contano gli immobili
aziendali e i beni con l'agevolazione A.

Il **credito d'imposta per il riacquisto della prima casa** (art. 7 L.
448/1998, quadro EH sezione III: imposta di registro o IVA pagata
sull'acquisto, con gli estremi di registrazione dell'acquisto e della
vendita) riduce l'imposta ipotecaria e, per il resto, la catastale.

### Eredità giacente, eredità amministrata e trust

Per il curatore dell'eredità giacente (carica 5) nel quadro EA c'è solo la
curatela (tipo soggetto 6), beneficiaria temporanea, che autoliquida le
imposte; il grado di parentela (e quindi aliquota e franchigia) è quello
indicato dal professionista. Per le cariche 5 e 6 il quadro EH non si
compila (le dichiarazioni sostitutive vanno allegate) e le agevolazioni
sono limitate ad A, C, D, E, M, N, L.

Il trust testamentario (carica 9) ha nel quadro EA un rigo di tipo 5,
intestato al trustee, per ogni beneficiario finale, con il suo grado di
parentela con il defunto (35 se non è individuato); il trustee non può
essere beneficiario e tutti i beni devono essere conferiti nel trust
(altrimenti si usa il modello 4 cartaceo). Gli immobili conferiti scontano
le imposte ipotecaria e catastale fisse (righi EF3bis ed EF10bis) e senza
agevolazioni. L'imposta di successione è dovuta all'attribuzione ai
beneficiari finali, salvo il **pagamento anticipato** scelto dal trustee:
allora si autoliquida subito con aliquote e franchigie dei beneficiari
finali, senza riduzioni ed esenzioni, e si versa con F24 del trustee. Il
trust a favore di sole persone con disabilità grave (art. 6 L. 112/2016)
è esente dall'imposta di successione e dal bollo.

Per le cariche 5, 6 e 9 le volture catastali non sono automatiche: la
casella "casi particolari" è barrata, la tassa ipotecaria è di 65 euro per
circoscrizione e la scheda "Volture" non propone variazioni dei possessi.
L'immesso nel possesso dei beni dell'assente (carica 8) presenta la
dichiarazione ordinaria, con la decorrenza del termine dall'immissione.

### Presentazione tardiva

`Services\Ravvedimento` calcola il ravvedimento quando la prima
dichiarazione è presentata dopo il termine (violazioni dal 1° settembre
2024):

- **tardivo pagamento** delle imposte autoliquidate (sezione VI del quadro
  EF, istruzioni del modello): sanzione del 25%, dimezzata entro 90 giorni,
  ridotta allo 0,083% per giorno entro 15 giorni, 1,25% entro 30, 1,39%
  entro 90, 3,12% entro un anno, 3,57% oltre, più gli interessi legali
  giorno per giorno (calcolo di Deleghe). Ipotecaria, catastale, bollo e
  tassa ipotecaria scadono con il termine di presentazione, l'imposta di
  successione 90 giorni dopo;
- **tardiva presentazione** con imposta di successione dovuta (art. 50
  TUS: 45% entro 30 giorni, 120% oltre): 4,5% entro 30 giorni, 13,33%
  entro 90, 15% entro un anno, 17,14% oltre (1/10, 1/9, 1/8, 1/7 del
  minimo, come nella prassi applicativa). Con il pagamento contestuale va
  nella sezione VI con l'imposta di successione; altrimenti con F24 dei
  beneficiari (codice 1549);
- **tardiva presentazione senza imposta dovuta** (art. 50 TUS: da 150 a
  500 euro entro 30 giorni, da 250 a 1.000 oltre): 15 euro entro 30 giorni,
  27,78 entro 90, 31,25 entro un anno, 35,71 oltre, con F24 (codice 1549)
  di chi presenta la dichiarazione;
- l'imposta di successione versata con F24 oltre i 90 giorni dal termine
  si ravvede con i codici 1535 (sanzione) e 1537 (interessi); presentando
  oltre quel termine l'imposta si paga con la dichiarazione (tempistica 2).

Le sanzioni e gli interessi della sezione VI si addebitano con la
dichiarazione insieme al totale del quadro EF. Per le sostitutive e per le
successioni con termine anteriore al 1° settembre 2024 il calcolo resta a
mano (avviso).

### Valore degli immobili

`Services\ValoreCatastale` propone il valore catastale (art. 34, c. 5,
TUS; art. 52, c. 4, TUR): rendita rivalutata del 5% per 120 (gruppi A e
C/2, C/6, C/7; 110 con la prima casa), 140 (B, già rivalutato del 40% dal
D.L. 262/2006, art. 2, c. 45; C/3, C/4, C/5), 60 (A/10, D),
40,8 (C/1, E); terreni: reddito dominicale rivalutato del 25% per 90. Le
aree edificabili vanno al valore venale. Il valore resta modificabile.

## Pagamento e versamenti F24

Le imposte del quadro EF si addebitano sul conto indicato nella
dichiarazione (IBAN del dichiarante, del rappresentato o
dell'intermediario). L'imposta di successione (rigo EF18ter):

- con **pagamento contestuale** si addebita con la dichiarazione (tutta o
  l'acconto);
- con **pagamento non contestuale** si versa con F24 entro 90 giorni dal
  termine di presentazione (art. 37 TUS);
- **rateazione** (art. 38 TUS): acconto di almeno il 20% e rate trimestrali
  di pari importo (da 1.000 euro da rateizzare, fino a 8 rate, fino a 12
  oltre 20.000 euro) che scadono l'ultimo giorno di ciascun trimestre dal
  versamento dell'acconto, con gli interessi dal giorno successivo
  all'acconto al 3% annuo a scalare (art. 5, c. 3, D.M. 21 maggio 2009).

`Services\PianoVersamenti` registra **un F24 per beneficiario** sulla sua
quota d'imposta (gli eredi sono obbligati in solido, i legatari per la
propria quota) con `ArchivioVersamenti::registra` (origine `Successioni`,
riferimento `dichiarazione-<id>`), secondo la risoluzione n. 2/E del 10
gennaio 2025: sezione Erario, codice 1539 con rateazione "NNRR" ("0101"
per l'unica soluzione e l'acconto), anno del decesso, interessi delle rate
con il codice 1635, **codice fiscale del defunto come secondo codice
fiscale con codice identificativo 08**. Per questo Deleghe ha ora il
secondo codice fiscale anche sul versamento: la generazione delle deleghe
mette questi versamenti in deleghe con lo stesso secondo soggetto e non li
mescola con gli altri del contribuente
(`GenerazioneDeleghe::isCompatibile`).

Una nuova registrazione sostituisce i versamenti non ancora in deleghe
confermate; le differenze con quelli confermati sono segnalate.
Eliminando la dichiarazione si rimuovono i versamenti ancora sostituibili.

## File telematico

`Services\Telematico\GeneratoreSuc` produce la fornitura XML SUC13
secondo le specifiche tecniche dell'Agenzia delle Entrate (aggiornamento
del 2 luglio 2025, schema `fornituraSUC13_v1.xsd`): messaggio con il codice
fiscale del fornitore (intermediario o dichiarante), frontespizio, quadri
EA (con la parte riservata al trust), EB, EC (anche immobili graffati e
all'estero), ED, EE, EF (con i righi del trust e la sezione VI), EG
(allegati in base64), EH (con aziende, navi, aeromobili e il credito
d'imposta), EI, EL, EM, EN, EO, EP, EQ, ER. Righi e moduli
seguono il modello (tre beneficiari, due immobili, tre titoli, quattro
rendite, tre passività per modulo; oltre tre beneficiari di un bene la
devoluzione continua nel rigo successivo).

Prima dello scaricamento il file è **validato sullo schema ufficiale**
(XSD del modulo in `Application/Assets/xsd`); va poi controllato con il
software dell'Agenzia, che applica anche i controlli sull'Anagrafe
tributaria, e trasmesso con Entratel o Fisconline.

Allegati: PDF/A-1a o PDF/A-1b (si verifica la dichiarazione di conformità
nei metadati), TIFF in bianco e nero con compressione CCITT Group IV fino a
300 DPI, massimo 5 MB per file (`Services\VerificaAllegato`). La piena
conformità PDF/A si verifica con il servizio di validazione e conversione
dell'Agenzia.

## Volture

Dopo la presentazione, la scheda "Volture" propone
(`Services\PropostaVolture`):

- la chiusura alla data del decesso dei possessi del defunto (compresi
  usufrutto, uso e abitazione, che si estinguono);
- per ogni beneficiario, dal giorno successivo, un possesso con il titolo
  del diritto devoluto e la quota del defunto per la quota devoluta; se il
  beneficiario possiede già l'immobile con lo stesso titolo la quota si
  somma in una nuova versione del suo possesso. Le quote sono arrotondate
  a due decimali con il resto sull'ultima, così la somma coincide.

Le volture si applicano su conferma e la dichiarazione le registra come
applicate.

## Tabelle di riferimento

```bash
# aggiorna XSD e uffici di registrazione dalle specifiche dell'Agenzia
docker exec comm_suite_php sh -c 'cd /var/www/html && sisma specifiche-successioni'

# rigenera lo script SQL versionato nel modulo
docker exec comm_suite_php sh -c 'cd /var/www/html && sisma specifiche-successioni --sql=Successioni/Database/dati-riferimento.sql'

# confronta i codici degli enum con gli XSD del modulo
docker exec comm_suite_php sh -c 'cd /var/www/html && sisma specifiche-successioni --solo-verifica'
```

Oltre agli uffici di registrazione il comando importa i servizi di
pubblicità immobiliare (uffici di trascrizione, quadro EI) e i comuni
catastali di Trento e Bolzano (catasto tavolare).

Il comando trova nella pagina delle specifiche tecniche l'archivio
`SUC13_aaaammgg.zip` più recente (`--zip=FILE|URL` per usarne uno già
scaricato), ne estrae gli XSD, **confronta i codici degli enum del modulo
con le enumerazioni degli schemi** (gradi di parentela, codici diritto,
agevolazioni, tipi, cariche, ...) e si ferma senza modificare nulla se non
coincidono; altrimenti aggiorna gli XSD del modulo e la tabella degli
uffici di registrazione (codici e descrizioni dalla documentazione
dell'enumerazione `UfficiDiRegistrazione`). Lo script SQL è idempotente.

## Multi-studio

Le dichiarazioni appartengono a uno studio; i controller verificano
l'accesso al defunto (`SoggettoPermission` di Anagrafica) e accettano come
beneficiari, dichiaranti e rappresentati solo soggetti dello studio. Gli
uffici di registrazione sono comuni e in sola lettura.

## Fonti

- D.Lgs. 31 ottobre 1990, n. 346 (TUS), testo vigente dopo il D.Lgs. 18
  settembre 2024, n. 139: artt. 7 (aliquote e franchigie), 20-25
  (passività e riduzioni), 31-38 (dichiarazione, autoliquidazione,
  pagamento e dilazione);
- provvedimento del Direttore dell'Agenzia delle Entrate del 13 febbraio
  2025 (modello, istruzioni, specifiche tecniche) e specifiche tecniche
  aggiornate al 2 luglio 2025, con l'allegato "Modalità di calcolo
  dell'imposta di successione";
- istruzioni del modello, fascicoli 1 e 2 (allegato 5 "Devoluzione" per le
  quote di legge);
- risoluzione n. 2/E del 10 gennaio 2025 (codici tributo 1539, 1635, 1549);
- circolare n. 3/E del 16 aprile 2025 (riforma dell'imposta di successione,
  sanzioni dell'art. 50 TUS dopo il D.Lgs. 87/2024);
- art. 13 D.Lgs. 472/1997 (ravvedimento), D.M. 21 maggio 2009, art. 5
  (interessi sulle rate), D.L. 262/2006, art. 2, c. 45 (moltiplicatore del
  gruppo B), art. 7 L. 448/1998 (credito d'imposta), art. 6 L. 112/2016
  (trust per persone con disabilità grave);
- artt. 565-586 c.c. (successione legittima).

## Da completare

- sanzione per la tardiva presentazione tra 31 e 90 giorni: la prassi
  applicativa usa 1/9 del minimo (13,33%), la lettera c) dell'art. 13 D.Lgs.
  472/1997 porterebbe a 1/10 (12%); il modulo applica la misura più alta,
  da confermare;
- ravvedimento delle sostitutive presentate dopo il termine (oggi avviso,
  da calcolare a mano);
- grado di parentela della curatela nell'eredità giacente: scelto dal
  professionista (la norma non lo indica);
- codice dello Stato estero degli immobili all'estero indicato a mano;
- numero delle conservatorie quando più comuni ne condividono una
  (indicato a mano nel pagamento);
- verifica dei file con il software di controllo dell'Agenzia (la
  validazione sullo schema è superata; i controlli di merito del software
  non sono stati provati).
