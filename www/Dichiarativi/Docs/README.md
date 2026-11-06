# Modulo Dichiarativi

Dichiarazioni fiscali dei contribuenti dello studio. La versione 0.1 gestisce
la **dichiarazione IVA annuale** (modello IVA 2026 per l'anno 2025),
proposta dai registri e dalle liquidazioni della Contabilità, con i
controlli delle specifiche tecniche, il file telematico, il saldo da
versare e il credito da compensare registrati in Deleghe F24, e il **modello
IVA TR** per il credito trimestrale. Il catalogo
dei campi copre già anche il modello **Redditi Persone fisiche 2026**, la
cui proposta e il cui file telematico sono il prossimo passo (il modello
risulta "non ancora gestito" nella scheda del contribuente).

Il contribuente è il soggetto dell'Anagrafica; partita IVA, natura
giuridica, codice ATECO, regime e periodicità IVA si leggono dai dati
fiscali storicizzati, il firmatario dai legami (rappresentante legale) e
l'intermediario da Deleghe.

## Flusso di lavoro

"Dichiarazioni fiscali" apre l'elenco dei contribuenti (di default i soli
clienti) con le dichiarazioni dell'anno d'imposta scelto (di default
l'anno precedente). La scheda del contribuente elenca i modelli con il
motivo per cui un modello non è dovuto (ad esempio un regime senza
liquidazioni IVA) e i versamenti registrati dalle dichiarazioni.

"Proponi la dichiarazione" crea la dichiarazione dell'anno con il
frontespizio proposto (natura giuridica, rappresentante legale con il
codice di carica, intermediario predefinito dello studio con l'impegno a
trasmettere) e i quadri compilati dai dati della suite. Nella
dichiarazione:

- i **quadri** si consultano per scheda (solo i campi compilati, con la
  descrizione delle specifiche tecniche) e si modificano con "Modifica il
  quadro", che elenca tutti i campi del quadro: i valori cambiati
  diventano **manuali** e la proposta successiva non li sovrascrive (li
  segnala tra gli avvisi se diversi dal proposto); "Ripristina" li riporta
  al valore proposto; dopo il salvataggio i totali sono ricalcolati;
- **Riproponi i quadri** rilegge i dati della suite mantenendo i valori
  manuali;
- **attività separate**: se l'azienda ha attività con contabilità
  separata in Contabilità, la proposta crea un modulo per attività (la
  prima è il modulo 1) e li rinumera con i valori; questi moduli seguono
  le attività e non si eliminano dalla dichiarazione;
- **più moduli**: "Aggiungi modulo" crea il modulo di un'attività con
  contabilità separata non tenuta nella suite (compilato a mano), di un soggetto partecipante a
  un'operazione straordinaria (proposto dalla contabilità del soggetto
  scelto nell'Anagrafica, con la partita IVA nel VA1 e, se indicata, la
  data dell'operazione che limita operazioni e liquidazioni proposte a
  quelle precedenti) o della prima
  frazione d'anno della procedura concorsuale (VA3); i moduli si scelgono
  sopra le schede dei quadri e si eliminano con i loro valori (i
  successivi sono rinumerati);
- **altri quadri** (VC, VD, VO, VQ, ...) si aprono dal menu "Altro quadro"
  delle schede e si compilano a mano;
- l'**organo di controllo** che sottoscrive in alternativa al visto si
  propone dai legami dell'Anagrafica (revisore, sindaci in carica) o si
  indica componente per componente; il **visto di conformità** (del
  professionista o del CAF imprese con il responsabile dell'assistenza
  fiscale) e l'esonero (premialità ISA) sono nel frontespizio;
- **errori** (dati obbligatori mancanti) e **segnalazioni** (controlli di
  rispondenza delle specifiche non superati, di solito per correzioni a
  mano) sono mostrati in testa: con segnalazioni il file si genera solo
  con il flag di conferma nel frontespizio;
- **Registra saldo e credito in Deleghe** porta il risultato nell'archivio
  dei versamenti e dei crediti, con la modalità del saldo scelta (unica
  soluzione, rate, differimento con la maggiorazione) e, con il pro-rata,
  la percentuale provvisoria dell'anno successivo in Contabilità;
- **File telematico** scarica la fornitura (`IVA26_<codice fiscale>.txt`)
  da controllare e autenticare con il software dell'Agenzia delle Entrate;
- **Segna presentata** annota data e protocollo e registra il credito
  oltre 5.000 euro; "Riporta in bozza" lo annulla se non ancora usato. La
  correttiva nei termini e l'integrativa si predispongono riportando in
  bozza la dichiarazione e cambiandone il tipo nel frontespizio;
- **Stampa** è la stampa di controllo dei campi compilati, da far firmare
  al contribuente.

## Installazione

Il modulo è registrato in `MODULE_FOLDERS` e in `ModuloSuite`; richiede
Telonio (>= 0.6.2), Anagrafica (>= 0.6.0), Deleghe (>= 0.5.0) e
Contabilita (>= 0.3.0, per la tipologia dell'inversione contabile delle
righe IVA, il pro-rata provvisorio, le qualifiche delle operazioni,
l'aliquota teorica, le importazioni e le unità locali dei registri). `sisma
aggiorna-database` esegue
`Dichiarativi/Database/schema.sql` e
`Dichiarativi/Database/dati-riferimento.sql` (catalogo dei campi e dei
quadri, generato da `sisma tabelle-dichiarativi --sql`).

## Modello dei dati

| Tabella | Contenuto |
|---|---|
| `campo_modello` | Catalogo dei campi non posizionali per modello (`IVA`, `RPF`) e anno del modello: codice di otto caratteri (quadro, rigo, colonna), descrizione e formato |
| `quadro_modello` | Denominazioni dei quadri per modello e anno |
| `dichiarazione` | Una per contribuente, modello e anno d'imposta: tipo (ordinaria, correttiva, integrativa), stato, frontespizio, flag di conferma, presentazione, avvisi dell'ultima proposta |
| `valore_dichiarazione` | Valore di un campo per modulo e codice, in forma canonica, con il segno di valore manuale |
| `modulo_dichiarazione` | Moduli oltre al primo: tipo (attività separata, soggetto trasformato, prima frazione d'anno), soggetto da cui proporli, data dell'operazione straordinaria e attività separata della Contabilità |
| `sottoscrizione_controllo` | Componenti dell'organo di controllo che sottoscrivono la dichiarazione (codici 1-4) |

La dichiarazione conserva anche chi appone il visto di conformità (il
professionista, o il CAF e il suo responsabile), l'esonero dal visto e la modalità del saldo (unica soluzione, rate con il
numero, differimento con la data e l'eventuale ulteriore maggiorazione).

### Valori per codice del campo

I dati dei quadri non sono colonne di tabelle per quadro, ma valori per
codice del campo del modello (es. `VE023001`, imponibile al 22%), come nel
record a struttura variabile delle forniture telematiche (record "D"
dell'IVA, "C" dei Redditi, 75 coppie campo-codice e campo-valore per
record). La scelta (decisa all'avvio del modulo) permette un solo motore
per i modelli (IVA, Redditi, poi IRAP e 770, che usano la stessa
struttura) e rende un nuovo anno un aggiornamento del catalogo, non dello
schema; calcoli e controlli restano in servizi PHP per quadro.

I valori sono in forma canonica (`FormatoCampo`): importi interi in unità
di euro (arrotondati per eccesso da 50 centesimi), decimali con il punto,
date `AAAA-MM-GG`, caselle `1`; la formattazione del campo-valore di 16
caratteri (allineamenti, zeri, segno, continuazione dei testi lunghi con
"+") segue il prospetto delle configurazioni delle specifiche. I campi
senza formato nelle specifiche (il quadro VP della dichiarazione IVA)
non si possono compilare.

### Enum o entità

`ModelloDichiarativo`, `FormatoCampo`, `TipologiaDichiarazione` e
`StatoDichiarazione` sono enum (vocabolari chiusi con comportamento); il
catalogo dei campi è un'entità perché è ampio, versionato per anno e
ricavato da una fonte esterna. I codici di carica e l'impegno alla
presentazione sono quelli di Contabilità (`CodiceCaricaIva`,
`ImpegnoPresentazione`), comuni ai modelli dell'Agenzia; la tabella della
natura giuridica è in `Frontespizio`, con il raccordo dalla natura
giuridica dell'Anagrafica (proposta modificabile dove la tabella distingue
casi che l'Anagrafica non distingue).

## Catalogo dei campi

Il catalogo si ricava dalle specifiche tecniche ufficiali, elencate in
`Database/sorgenti/specifiche.csv` con l'indirizzo del PDF e il
provvedimento. `sisma specifiche-dichiarativi` scarica i PDF, ne estrae
le parole con le coordinate (`pdftotext -tsv`, poppler-utils) e legge le
tabelle dei campi non posizionali (`LettoreSpecifiche`): le colonne si
ricavano dalle intestazioni "Descrizione", "Formato" e "Valori" di ogni
pagina, la descrizione su più righe è attribuita al codice più vicino in
verticale (nel PDF è centrata sul codice), i titoli "Quadro XX - ..." danno
le denominazioni dei quadri e le note a tutta pagina sono escluse. Il
comando riscrive `campi-<modello>-<anno>.csv` e
`quadri-<modello>-<anno>.csv`; `sisma tabelle-dichiarativi` li importa
(con `--sql` riscrive `dati-riferimento.sql`).

| Modello | Campi | Quadri | Fonte |
|---|---|---|---|
| IVA 2026 | 1.144 | 22 | Allegato A al provvedimento n. 50925 dell'11 febbraio 2026 |
| Redditi PF 2026 | 4.605 | 30 | Provvedimento n. 72078 del 27 febbraio 2026, specifiche aggiornate al 10 giugno 2026 |

Le descrizioni sono un ausilio per l'interfaccia; codici e formati sono
i dati vincolanti. Il PDF dell'IVA 2026 non indica il formato dei 14
campi del quadro VP e quello dei Redditi PF dei campi `FC000002` e
`FC000004`: il comando li segnala.

## Dichiarazione IVA

### Proposta dei quadri

`Services\Iva\PropostaIva` compila i quadri per modulo: il modulo 1 dalla
contabilità del contribuente, i moduli dei soggetti partecipanti a
operazioni straordinarie dalla loro contabilità, quelli delle attività
separate e della prima frazione d'anno dai valori inseriti a mano (con i
totali ricalcolati). Dalle righe IVA dei registri con data di competenza
nell'anno:

- **VA**: codice dell'attività dai dati fiscali vigenti a fine anno
  (codici ATECO 2025 dal 1° aprile 2025); VA1 con la partita IVA del
  soggetto trasformato, VA3 per la prima frazione d'anno;
- **VE**: imponibili per aliquota nella sezione 2 (VE20-VE23), con
  l'imposta calcolata sull'imponibile arrotondato e la differenza con
  l'imposta dei registri nel VE25; corrispettivi da ventilare ripartiti
  con la ventilazione annuale; operazioni non imponibili, esenti, non
  soggette e in inversione contabile per natura (VE30-VE35, con il VE30
  campo 1 come totale che concorre al plafond); scissione dei pagamenti nel
  VE38 ed esigibilità differita nel VE37, fuori dalle sezioni 1 e 2;
  cessioni di beni ammortizzabili (qualifica della riga, o conto delle
  immobilizzazioni) nel VE40; volume d'affari VE50;
- **VF**: acquisti per aliquota (VF1-VF13) con la sola quota detraibile;
  per la detrazione parziale la quota di imponibile corrispondente
  all'imposta indetraibile va nel VF21, al netto dell'IVA (istruzioni del
  rigo VF21); acquisti senza imposta per natura (VF17 con dichiarazione
  d'intento, VF18, VF19 per i forfetari); acquisti intracomunitari e
  importazioni (VF28 campi 1-2 e 3-4, dalla casella "importazione" della
  registrazione di acquisto);
  ripartizione del VF29 con i beni ammortizzabili dalle righe contabili in
  dare sui conti delle immobilizzazioni (voci CEE B.I e B.II) e i beni per
  la rivendita dalle righe IVA;
- **pro-rata** (art. 19, c. 5, e 19-bis D.P.R. 633/1972), con operazioni
  esenti nel VE33: casella 3 del VF30, percentuale del VF34 campo 9 con la
  formula delle istruzioni (arrotondata sulle prime tre cifre decimali,
  tra 0 e 100), VF37 = [(VF27 + VF35 - VF36) x percentuale] - VF35 + VF36,
  VF71 = VF37 + VF70; i campi 1-4, 7 e 8 del VF34 dalla qualifica delle
  righe IVA delle vendite (oro da investimento, esenti occasionali nn. 1-9,
  n. 27-quinquies, beni ammortizzabili esenti, esenti equiparate, non
  soggette senza detrazione), i campi 5 e 6 (operazioni fuori dai registri)
  a mano; VF35 con l'IVA teorica degli acquisti con dichiarazione d'intento
  all'aliquota teorica della riga (22% se non indicata, con l'avviso); con
  sole operazioni esenti la casella del VF32 e nessuna detrazione;
- **VJ**: imposta delle integrazioni degli acquisti in inversione
  contabile: intracomunitari (codici `UE`) nel VJ9, da non residenti
  (`ES`) nel VJ3, interni (`RC`) nel rigo della tipologia N6 della riga IVA
  (N6.1 VJ6, N6.2 VJ7, N6.3 VJ12, N6.4 VJ13, N6.5 VJ14, N6.6 VJ15, N6.7
  VJ16, N6.8 VJ17); senza tipologia (o N6.9) nel VJ12 con l'avviso;
- **VH**: solo per i periodi la cui comunicazione delle liquidazioni
  periodiche non risulta inviata in Contabilità, con gli importi del rigo
  VP14 ricalcolati dalla liquidazione (e l'acconto nel VH17); il quarto
  trimestre dei trimestrali si liquida nella dichiarazione;
- **VL**: IVA dovuta o a credito; credito dell'anno precedente (VL8) e sua
  parte compensata in F24 (VL9) dall'archivio dei crediti di Deleghe
  (codice 6099 dell'anno precedente), VL25 = VL8 - VL9; interessi dei
  trimestrali (VL23), crediti d'imposta (VL27) e versamenti auto UE (VL29)
  dalle liquidazioni; IVA periodica dovuta (VL30 campo 2: importi del VP14
  versati e acconto dovuto) e versata (campo 3: importi a debito dei
  codici 6001-6012, 6031-6033, 6013 e 6035 dell'anno nelle deleghe
  trasmesse o quietanzate, anche se compensati); VL32 e VL33 con le
  formule del paragrafo 2.6 delle specifiche per gruppi di moduli: un solo
  gruppo per il modulo unico e le contabilità separate (somma dei VL3 e
  VL4, sezioni 2 e 3 nel modulo 1), un gruppo per soggetto nelle
  operazioni straordinarie (VA1), due gruppi con la prima frazione d'anno
  (VA3), con il risultato nel primo modulo del gruppo; interessi annuali
  dell'1% dei trimestrali (VL36); VL38, VL39 e VL41;
- **VT** (modulo 1): operazioni imponibili di tutti i moduli (VE24 e VE26)
  ripartite tra consumatori finali (corrispettivi e fatture a clienti senza
  partita IVA) e soggetti IVA in proporzione alle righe dei registri, e
  quelle verso consumatori finali nella regione dell'unità locale
  collegata al registro o sezionale IVA in Contabilità, per gli altri
  registri nel luogo di esercizio dell'attività (sede operativa non
  collegata a un registro, sede legale, domicilio fiscale o residenza
  dell'Anagrafica; raccordo provincia-regione in `RegioniVt`);
- **VC** (modulo 1, esportatori abituali con acquisti nel VF17): plafond
  utilizzato per mese dalla data dei documenti di acquisto con
  dichiarazione d'intento (momento di effettuazione, non registrazione),
  volume d'affari (senza le operazioni dell'art. 21, c. 6-bis) ed
  esportazioni e operazioni assimilate per mese dell'anno e del
  precedente, totali, importazioni con dichiarazione d'intento in colonna
  2; metodo solare (casella 2 del VC14) di default, con il plafond
  disponibile al 1° gennaio dalle operazioni dell'anno precedente; metodo
  mensile barrando la casella 3 del VC14 e riproponendo: colonne 5 e 6
  dell'anno precedente e plafond di gennaio dai dodici mesi precedenti al
  netto degli acquisti con plafond; in entrambi i casi la verifica mese per
  mese avvisa del primo superamento del plafond disponibile e dello status
  di esportatore abituale (operazioni dell'anno precedente oltre il 10% del
  volume d'affari);
- **VX** (modulo 1): IVA da versare (VX1) o a credito (VX2) con la somma
  dei VL38, VL39 e VL40 dei moduli (paragrafo 2.9; nelle procedure
  concorsuali senza il VL38 del primo modulo); credito interamente da
  riportare (VX5), salvo la richiesta di rimborso inserita a mano nel VX4;
- **visto**: per il credito da compensare oltre 5.000 euro senza
  sottoscrizione dell'organo di controllo, visto né esonero, proposto il
  visto del professionista dell'intermediario se persona fisica.

### Attività con contabilità separata

Con le attività separate della Contabilità (paragrafo 3.2 delle
istruzioni) ogni modulo riepiloga una attività (`ModuliAttivita`,
`PropostaIva`): VA2 con il codice ATECO dell'attività, VE, VF, VJ e la
sezione 1 del VL dalle righe dei suoi registri e dalle quote degli
acquisti promiscui, pro-rata dell'attività (registrato come provvisorio
dell'anno successivo per attività); VH, sezioni 2 e 3 del VL, VT, VC e VX
nel solo modulo 1, sul complesso delle attività. Con attività trimestrali
liquidate nel mese di fine trimestre il VH ha la casella "Liquidazione
anticipata" del trimestre, il VL23 gli interessi dei trimestri anticipati
e il VL36 l'1% sull'IVA del quarto trimestre delle attività trimestrali.

### IVA di gruppo

Per le società che partecipano alla liquidazione IVA di gruppo della
Contabilità (`PropostaGruppoIva`, paragrafi 3.4, 4.2.9, 4.2.14, 4.2.16 e
4.3 delle istruzioni):

- **VL21 e VL31**: crediti e debiti trasferiti nelle liquidazioni
  periodiche (senza il quarto trimestre dei trimestrali); il VL30 campo 2
  solo per i periodi dopo l'uscita dal gruppo;
- **VX7 e VX8**: conguaglio annuale trasferito al gruppo, con IMPOSTA e
  IMPOSTA GRUPPO del paragrafo 2.9.1 delle specifiche (restano della
  società i crediti non trasferibili VL10, VL11 e VL12 campo 2, VL40 e i
  moduli dei soggetti esterni al gruppo); con la cessazione del controllo
  in corso d'anno VX1 e VX2;
- **VK** (modulo 1): controllante, ultimo mese di controllo, crediti e
  debiti trasferiti (più VX8 e VX7 con il controllo per tutto l'anno),
  eccedenza di credito o di debito, eccedenza compensata con i debiti
  delle altre società (ripartita in proporzione tra le società a credito,
  da confrontare con l'attestazione della controllante), crediti
  d'imposta, interessi trasferiti, acconto, sezione 3 con la cessazione;
- **VG** (controllante): nella dichiarazione dell'anno precedente il
  primo dell'opzione (società partecipanti e della sola catena di
  controllo, possesso e detentore), degli ingressi e delle uscite dal 1°
  gennaio successivo (caselle 3 e 4) o della revoca (VG8);
- **prospetto IVA 26 PR** (controllante): VS con le società, l'ultimo mese,
  le eccedenze trasferite e compensate; VV per i periodi senza
  comunicazione del gruppo inviata; VW dalle dichiarazioni delle società
  partecipanti presenti nella suite (VL1, VL2, VL11, VL27, VL29, VL36 o i
  righi della sezione 3 del VK), dagli interessi e dai versamenti del
  gruppo (VW30 campo 3 con i versamenti di riferimento `iva-gruppo`) e
  dal credito di gruppo dell'anno precedente (VY5 della dichiarazione
  precedente), con le formule delle istruzioni; VY da versare o a credito.
  I crediti trimestrali vengono dai
  modelli IVA TR presentati (VS campo 5, VW20) e dalle deleghe (VW22); la
  quota di rimborso per società (VS campo 10) si indica a mano, causale,
  erogazione prioritaria ed esonero (campi 8, 9, 11, 12), VS20, VS22, VY4 e il
  VK25 delle controllate sono proposti, con VW24 da Deleghe, VS30 dalle
  eccedenze di debito delle società (VK22) e il quadro VZ dai prospetti dei
  due anni precedenti se il VY4 supera le quote; le
  dichiarazioni delle controllate vanno predisposte prima di quella della
  controllante.

Il saldo e il credito registrati in Deleghe dalla controllante comprendono
quelli del gruppo (VY1, VY5); le controllate non versano il conguaglio
trasferito. Nel file telematico i quadri del prospetto sono nel modulo 1
con il campo V1000002 dei quadri compilati.

### Controlli

`Services\Iva\ControlliIva` distingue gli errori (partita IVA, dati di
nascita delle persone fisiche, natura giuridica e firmatario dei soggetti
diversi, impegno dell'intermediario, professionista del visto o CAF con
il codice fiscale numerico e il responsabile persona fisica, non entrambi,
codice
attività di ogni modulo, causale del rimborso nel VX4, quadri vuoti) dalle
segnalazioni dei controlli di rispondenza, per modulo (imposte per
aliquota, totali dei quadri VE, VF e VJ, VF29, pro-rata e VF37, VF71, righi
del VL), per gruppo (VL32, VL33, VL38, VL39) e nel modulo 1 (VX, anche
VX7 e VX8 con il quadro VK, VK22-VK24, VW, VG8, VT con la ripartizione
regionale, totali del VC), con la tolleranza di 1 euro.
Avverte del visto di conformità o dell'organo di controllo per il credito
da compensare oltre 5.000 euro e per il rimborso oltre 30.000 euro senza
garanzia.

### File telematico

`Services\Telematico\GeneratoreIva` scrive la fornitura "IVA26": record
A (fornitore: l'intermediario, altrimenti il contribuente o il
sottoscrittore), B (frontespizio: dati del contribuente, natura
giuridica, numero dei moduli, firma, esonero dal visto, sottoscrizione
dell'organo di controllo fino a cinque componenti, sottoscrittore con
codice di carica e dati di nascita, incaricato con impegno e data, visto
di conformità del professionista o del CAF con il codice fiscale del
responsabile), D (un record o più per modulo, con il campo `V1000001` dei
quadri compilati nell'ordine delle specifiche) e Z. I record a struttura
variabile sono in `RecordQuadri`, comune ai modelli.

### Versamenti e crediti

`Services\Iva\VersamentiIva`:

- **saldo** (VX1) con il codice 6099, non dovuto fino a 10,33 euro,
  registrato con `ArchivioVersamenti` (origine `Dichiarativi`, riferimento
  `iva-<anno>`) nella modalità scelta: entro il 16 marzo (art. 6, c. 1,
  D.P.R. 542/1999); a rate mensili dal 16 marzo con il piano di
  rateazione di Deleghe (art. 20 D.Lgs. 241/1997, interessi 1668); differito
  al termine del saldo delle imposte sui redditi (di default il 30 giugno,
  o la data indicata, ad esempio con una proroga) con la maggiorazione
  dello 0,40% per mese o frazione dal 16 marzo, non oltre il 30 giugno, e
  nei 30 giorni successivi un ulteriore 0,40% sull'importo maggiorato (art.
  17, c. 1 e 2, D.P.R. 435/2001), anche a rate. Per i trimestrali comprende
  l'IVA del quarto trimestre: il versamento 6099 registrato dalla
  liquidazione del quarto trimestre in Contabilità è sostituito o, se già
  in una delega confermata, scalato dal saldo. Un piano di rateazione
  precedente si ricalcola finché nessuna rata è in una delega confermata;
- **credito** (VX5) nell'archivio dei crediti di Deleghe con il codice
  6099 e l'anno d'imposta, compensabile dal 1° gennaio fino a 5.000 euro,
  oltre dal decimo giorno successivo alla presentazione (art. 17, c. 1,
  D.Lgs. 241/1997): fino a 5.000 euro si registra subito, oltre alla
  presentazione. Un credito 6099 dell'anno inserito a mano in Deleghe è
  adottato invece di essere duplicato; il credito già utilizzato non si
  modifica né si elimina;
- **pro-rata**: la percentuale di detrazione dell'anno diventa quella
  provvisoria dell'anno successivo in Contabilità (`ProrataIva`, origine
  `Dichiarativi`), senza sostituire quella inserita a mano.

### Rimborso annuale

`Services\Iva\RimborsoIva` lavora quando nel rigo VX4 è indicato l'importo
da chiedere (la scelta tra rimborso e riporto resta del contribuente;
senza importo un avviso segnala che il credito sarebbe rimborsabile):

- **causale** (campo 3) calcolata dalle righe IVA dell'intero anno con le
  stesse regole dei presupposti del modello IVA TR, oltre 2.582,28 euro di
  credito: 2 aliquota media, 4 beni ammortizzabili nei limiti della relativa
  imposta, 3 operazioni non imponibili oltre il 25% (VE30 e VE31 sulla somma
  in valore assoluto dei righi VE40 e VE50), 5 operazioni non soggette
  prevalenti (VE34 più VE30 e VE31, come dicono le istruzioni), 6 non residenti
  dai dati fiscali, 7 produttori agricoli entro l'IVA teorica (VF54), che
  prevale sulla 3. Senza soglia: 1 cessazione nell'anno, 11 persona fisica
  che passa al regime forfetario dall'anno successivo, 13 ingresso in un Gruppo
  IVA dall'anno successivo (il resto del credito nel VX2 campo 2, da trasferire
  al gruppo), 8 minore eccedenza del triennio dalle dichiarazioni dei due anni
  precedenti, 10 se è il credito non trasferibile di chi partecipa all'IVA di
  gruppo (per l'ex controllante dal VY5 del prospetto), 9 se ricorrono la 8 e
  la 4;
- **erogazione prioritaria** (campi 4 e 5): 6 per le operazioni in
  scissione dei pagamenti con la relativa imposta, 1 e 8 per il reverse
  charge del subappalto e dei servizi agli edifici, 2, 3, 4, 5 e 7 dal codice
  ATECO con il raccordo ufficiale ISTAT tra ATECOFIN 2004 e ATECO 2007 (dove il
  raccordo è parziale, come per le demolizioni 38.31.10, solo un avviso);
- oltre 30.000 euro **esonero dalle garanzie** (campo 7, codici 1 e 4) e
  **attestazione patrimoniale** (campo 10) dai flag del frontespizio:
  attestazione patrimoniale, adempimento collaborativo, concordato
  biennale. Nel quadro VS il campo 8 ha codici propri (1 visto e
  attestazione, 2 adempimento collaborativo, 3 ISA, 4 concordato). Il visto
  non esonera nelle **situazioni di rischio** dell'art. 38-bis, c. 4:
  impresa attiva da meno di due anni alla richiesta (dalla data di inizio
  attività dei dati fiscali, salvo le start-up innovative), cessazione e
  atti dell'**archivio degli accertamenti** della scheda del contribuente
  notificati nei due anni precedenti con la differenza oltre il 10%, il 5% o
  l'1% del dichiarato (`AttoAccertamento`). La casella del frontespizio
  attesta la verifica degli atti: con l'archivio vuoto e senza la casella resta
  un avviso, perché un archivio vuoto può essere solo non aggiornato;
- **procedura semplificata** (campo 2, a scelta del contribuente):
  un'avvertenza se con le compensazioni dell'anno in Deleghe supera i
  2.000.000 di euro.

I controlli bloccano una causale dell'art. 30, c. 2 con il credito sotto
soglia, un rimborso oltre il credito, il campo 5 senza il codice 6, un VY4
diverso dal VS20 senza il quadro VZ e un VK25 oltre l'eccedenza non
compensata.

### Regimi speciali agricoli

Il regime è nei dati fiscali o sull'attività separata (`RegimeDetrazioneIva`):
ogni regime ha il suo modulo, con la casella del rigo VF30, perché le istruzioni
non ammettono più di una casella per modulo.

- **Art. 34** (casella 8): sezione 3-B del quadro VF dalle righe IVA.
  Cessioni con la percentuale di compensazione scelta sulla riga nei righi
  VF39-VF49; conferimenti a cooperative e consorzi (qualifica dedicata, codici
  IVA `C2`-`C12.3`) anche nella sezione 1 del VE; senza percentuale sono
  operazioni diverse delle imprese miste (VF38), con la detrazione degli
  acquisti segnati come destinati (VF53); non imponibili con la percentuale
  nell'IVA teorica (VF54); connesse occasionali nel VF62, con il 50% nel VF71.
- **Art. 34-bis, agriturismo, enoturismo, oleoturismo** (caselle 7, 4, 9, 10):
  VF71 pari al 50% del VE26; **associazioni** in agricoltura (casella 5): un
  terzo.
- **Esonerati** (art. 34, c. 6): niente liquidazioni né modello TR; la
  dichiarazione è dovuta solo se dalle righe IVA le operazioni diverse superano
  un terzo o il volume dell'anno precedente supera 7.000 euro: agricole nella
  sezione 1 del VE, diverse nella sezione 2, sezione 3-B, senza il quadro VH.

Il modello IVA TR riporta l'IVA teorica nel TC5. Le opzioni del quadro VO e gli
spostamenti nel VF18 per il VF62 restano a mano.

## Modello IVA TR

Modello trimestrale per chiedere a rimborso o usare in compensazione il
credito dei primi tre trimestri (art. 38-bis, c. 2; specifiche IVT25, una
riga per trimestre nella scheda del contribuente). Il catalogo dei campi
usa i formati VP e VN: importi in unità e centesimi separati dalla virgola.

- `Services\Iva\PropostaTr` propone i quadri TA, TB, TC e TD dalle righe IVA
  del trimestre: presupposti TD1 (aliquota media), TD2 (non imponibili oltre
  il 25%, percentuale arrotondata all'unità superiore), TD3 (beni
  ammortizzabili oltre i 2/3) e TD5 (prestazioni dell'art. 30, c. 2, lett.
  d) verso soggetti non stabiliti oltre il 50%, dalla qualifica delle righe
  IVA) e TD4 (non residenti, dai dati fiscali). Il rigo TA30 esclude le altre
  non imponibili (N3.6), che vanno nel TA31. In mancanza di indicazioni il credito è
  proposto in compensazione (TD7);
- per la controllante del gruppo IVA il modello ha il prospetto TE con il
  saldo trasferito da ogni società e un modulo per ogni società con i
  requisiti (TD9); le controllate non presentano il modello;
- `Services\Iva\ControlliTr`: trimestre, credito oltre 2.582,28 euro,
  presupposto, importi entro il credito (con il solo TD3 entro l'imposta del
  TB33), visto o organo di controllo oltre 5.000 euro annui di compensazioni;
- `Services\Telematico\GeneratoreTr`: file `IVT25_<cf>_<anno>_T<n>.txt`,
  record A, B, C, Z;
- `Services\Iva\VersamentiTr`: alla presentazione la compensazione entra
  nell'archivio dei crediti di Deleghe con il codice 6036, 6037 o 6038,
  usabile da quel giorno o, oltre 5.000 euro, dal decimo giorno successivo;
  tutto il credito chiesto **non si riporta** al periodo successivo (LIPE,
  rigo VP8): Contabilità lo registra e rielabora le liquidazioni successive
  (`Services\CreditoTrimestrale`).

Nella dichiarazione annuale `Services\Iva\CreditiTrimestrali` propone VL20
dai modelli presentati e VL22 dagli utilizzi dei crediti 6036-6038 nelle
deleghe trasmesse o quietanzate fino alla presentazione della dichiarazione
(alla sua scadenza finché non è presentata), con un avviso per quelli esclusi;
per il gruppo VW20, VW22 e VS campo 5. Con lo stesso criterio si propongono
VL9 e VW24.

## Fixture

- **Alfa Consulting S.r.l.** (mensile): esercizio 2025 con prestazioni al
  22% e alla PA in scissione dei pagamenti, acquisto di macchine d'ufficio
  da un nuovo fornitore (VF29, campo 1), manutenzione, spese dell'auto al
  40%, subappalto in inversione contabile con la tipologia N6.3 (VJ12),
  libri al 4% e assicurazione esente; liquidazioni mensili 2025 (tutte a
  credito) e comunicazioni dei primi tre trimestri inviate (VH solo per il
  quarto trimestre); dichiarazione IVA 2026 presentata con il credito di
  8.000 euro e il visto di conformità del commercialista Paolo Marini, che
  adotta il credito IVA 2025 già presente in Deleghe;
- **Alimentari Ferri** (trimestrale, ventilazione, sede operativa a Roma):
  liquidazioni trimestrali 2025 e dichiarazione IVA 2026 in bozza con la
  ventilazione annuale, il quadro VH dei primi tre trimestri, il VT dei
  corrispettivi nel Lazio e il saldo 6099 registrato in Deleghe;
- **Poliambulatorio Sole S.r.l.** (trimestrale, Milano, collegio
  sindacale): prestazioni sanitarie esenti e certificati imponibili a
  privati e imprese, interessi attivi esenti occasionali (VF34 campo 2),
  cessione di un ecografo usato (VE40), ambulatorio di Piacenza con il suo
  sezionale, pro-rata provvisorio del 15% nelle liquidazioni, dichiarazione
  con il pro-rata definitivo del 15% (registrato come provvisorio del
  2026), VT con i privati in Lombardia ed Emilia-Romagna, saldo con gli
  interessi dei trimestrali in cinque rate e visto di conformità del CAF
  Imprese Lombardia (responsabile Anna Colombo);
- **Omega Export S.r.l.** (mensile, Torino, collegio sindacale):
  esportazioni del 2024 e del 2025, cessioni intracomunitarie, acquisti con
  dichiarazione d'intento e importazioni (una in dogana con la
  dichiarazione d'intento, una con l'IVA: VF28 e VC colonna 2), quadro VC
  con il metodo mensile, comunicazioni periodiche non inviate (VH di tutti
  i mesi); incorporazione dal 1° luglio 2025 di **Omega Logistica S.r.l.**
  (trimestrale, Genova) nel modulo 2 proposto dalla sua contabilità fino al
  30 giugno (VA1); credito complessivo oltre 5.000
  euro con la sottoscrizione del collegio sindacale;
- **Biomedica Nord S.r.l.** (mensile, Bologna): produttore di apparecchi
  elettromedicali esportatore abituale (plafond solare di 150.000 euro
  dalle esportazioni del 2024) con un laboratorio di analisi esente;
  componenti acquistati con dichiarazione d'intento all'aliquota teorica
  del 22%, pro-rata provvisorio del 75% nelle liquidazioni e definitivo
  del 76% nella dichiarazione, con il VF35 (17.600 euro di IVA non
  assolta) che riduce la detrazione del VF37 a 2.464 euro;
- **Gialli Edilforniture S.r.l.** (Bergamo): contabilità separate per
  opzione tra l'ingrosso di macchine per l'edilizia (mensile) e il negozio
  di ferramenta (trimestrale, liquidato con il mese di fine trimestre e
  gli interessi dell'1%), affitto del capannone promiscuo ripartito 70/30;
  dichiarazione con un modulo per attività, VH con la liquidazione
  anticipata, VL36 sul quarto trimestre del negozio;
- **gruppo IVA Verde** dal 2025: Verde Holding S.p.A. (controllante,
  mensile, a credito), Verde Distribuzione S.r.l. (mensile, a debito) e
  Verde Servizi S.r.l. (trimestrale), possedute al 100% dal 2020, più
  Verde Logistica S.r.l. che entra dal 2026; liquidazioni trasferite,
  liquidazioni, acconto e comunicazioni del gruppo (primi tre trimestri
  inviate), dichiarazioni delle controllate con VK e VX7 e della
  controllante con VG (ingresso dal 2026) e prospetto IVA 26 PR. Modello
  IVA TR di gruppo del primo trimestre con il visto (TE51 di 6.600 euro,
  compensato in F24: VW22) e credito annuale di gruppo con la quota di
  rimborso di Verde Servizi, esonerata dalla garanzia per il concordato
  biennale (VS campi 8-10, VK25, VY4 di 10.000 euro).
- **Omega Export**: modello IVA TR del terzo trimestre (TD2, 1.300 euro a
  rimborso e 2.000 in compensazione, utilizzati in F24: VL20 e VL22),
  presentato con le liquidazioni dell'anno già elaborate: i 3.300 euro escono
  dal riporto di ottobre con la rielaborazione automatica. Rimborso annuale di
  4.000 euro con la causale 3 proposta.
- **Adria Logistica S.r.l.**: prestazioni verso committenti non stabiliti con
  la qualifica della riga IVA (TD5, modello TR del secondo trimestre
  compensato in F24) e causale 5.
- **Cinema Odeon S.r.l.**: proiettore nel primo trimestre (TD3, rimborso con
  il modello TR, VL20), causale 4 ed erogazione prioritaria 7 dal codice
  ATECO.
- **Alberto Conti**: professionista che passa al regime forfetario dal 2026,
  causale 11.
- **Nordic Trade S.r.l.**: non residente con il rappresentante fiscale (TD4,
  causale 6), attività iniziata nel settembre 2024: rimborso di 35.000 euro
  con la garanzia dovuta nonostante visto e attestazione.
- **Beta Partecipata S.r.l.**: entra in un Gruppo IVA dal 2026 (causale 13,
  resto del credito nel VX2 campo 2).
- **Gruppo Delta**, dal 2023. Delta Servizi S.r.l., entrata nel 2024, ha il
  credito precedente non trasferibile compensato in parte in F24 (VL9, VL10,
  VX2) e la causale 10; Delta Commerciale S.r.l. è a debito; Delta Holding S.r.l.
  ha il credito di gruppo del 2024 (VW25), in parte compensato in F24 (VW24) e
  in parte con i debiti delle società (VS30), e chiede la minore eccedenza del
  triennio (VY4, quadro VZ). Le dichiarazioni 2023 e 2024 hanno solo gli importi
  che servono al calcolo (VX5, VY5), senza catalogo di quegli anni: non generano
  il file.
- **Tenuta Colli Berici S.r.l.**: cinque moduli per regime (art. 34 con
  compensazione, conferimento, esportazione, operazioni diverse e connessa
  occasionale; agriturismo, connesse, enoturismo, oleoturismo); il VF71 dei
  moduli coincide con la detrazione delle liquidazioni, comunicazione del terzo
  trimestre in bozza con l'imposta dei regimi nei righi VP4 e VP5.
- **Cantina Export S.r.l.**: IVA teorica delle esportazioni (TC5, VF54), modello
  TR con il rimborso e causale 7.
- **Rosa Fiore** ed **Gino Orti**: esonerati, nei limiti (dichiarazione non
  dovuta) e oltre il limite di un terzo (dichiarazione senza il VH).
- Accertamenti: su **Nordic Trade** un atto oltre le soglie e uno sotto.



## Scadenzario dello studio

`Services\ScadenzeModulo` porta nello scadenzario le dichiarazioni
predisposte con il termine ordinario di presentazione (30 aprile per
l'IVA, 31 ottobre per i Redditi), completate quando presentate; saldi e
crediti compaiono da Deleghe.

## Multi-studio

Le dichiarazioni sono legate allo studio e si raggiungono solo dai
soggetti autorizzati (`SoggettoPermission`); firmatario, intermediario,
professionista o CAF del visto, componenti dell'organo di controllo e soggetti
dei moduli devono essere dello stesso studio.

## Fonti

- D.P.R. 633/1972 (artt. 17, 17-ter, 19, 19-bis, 19-bis1, 24, 36),
  D.P.R. 322/1998 (artt. 3 e 8), D.P.R. 542/1999 (art. 6), D.P.R. 435/2001
  (art. 17), D.Lgs. 241/1997 (artt. 17 e 20), D.L. 78/2009 (art. 10), D.L.
  50/2017 (art. 9-bis), L. 28/1997 (art. 2, c. 2): testi su Normattiva;
- modello IVA 2026 e istruzioni (provvedimento dell'Agenzia delle Entrate
  n. 51732 del 15 gennaio 2026) e specifiche tecniche (allegato A al
  provvedimento n. 50925 dell'11 febbraio 2026):
  <https://www.agenziaentrate.gov.it/portale/it/iva_2026specifichetecniche>;
- specifiche tecniche del modello Redditi PF 2026 (provvedimento n. 72078
  del 27 febbraio 2026, aggiornate al 10 giugno 2026):
  <https://www.agenziaentrate.gov.it/portale/modello-redditi-persone-fisiche-2026/software-di-compilazione>;
- modello IVA TR (provvedimento n. 144055 del 26 marzo 2020) e specifiche
  tecniche IVT25 aggiornate al 13 marzo 2026:
  <https://www.agenziaentrate.gov.it/portale/documents/d/guest/specifiche_ivt25_2026>;
- istruzioni alla compilazione della comunicazione delle liquidazioni
  periodiche IVA (rigo VP8, crediti chiesti con il modello IVA TR);
- tabella di raccordo ISTAT tra ATECOFIN 2004 e ATECO 2007:
  <https://www.istat.it/wp-content/uploads/2022/03/Tavola-di-raccordo-ATECOFIN-2004-ATECO-2007.pdf>;
- elenco delle province e delle città metropolitane (ISTAT) per il
  raccordo con i righi regionali del quadro VT.

## Da completare

- **Redditi PF** (ambito della 0.1): frontespizio e familiari a carico,
  quadri RA e RB dagli immobili e dai possessi dell'Anagrafica, RC, RN
  (IRPEF con le detrazioni), RV (addizionali regionale e comunale), RX,
  acconti e versamenti in Deleghe, record B e file telematico "RPF26";
- tabella dei prodotti con la percentuale di compensazione: oggi la
  percentuale si sceglie sulla riga;
- rettifica della detrazione nel passaggio tra regimi (art. 19-bis2) e
  autofatture degli acquirenti dagli agricoltori esonerati;
- gestione delle garanzie (polizza, importo, scadenza);

- verifica dei file con il software di controllo dell'Agenzia delle
  Entrate;
- modelli 2027: nuovo catalogo dalle specifiche quando pubblicate e
  riferimenti al Testo unico IVA.
