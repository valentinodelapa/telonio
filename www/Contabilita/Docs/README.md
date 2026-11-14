# Modulo Contabilità

Contabilità in partita doppia delle aziende clienti dello studio: piano dei
conti, clienti e fornitori con il partitario, prima nota con le righe
proposte dalla causale, registri IVA, liquidazioni periodiche e acconto
con i versamenti registrati nell'archivio di Deleghe F24, comunicazione
delle liquidazioni periodiche (LIPE), importazione delle fatture
elettroniche (verificate sullo schema ufficiale) con la proposta della
registrazione, libro giornale, schede contabili, bilancio di verifica e
stampe definitive numerate del giornale e dei registri IVA.

L'azienda è il soggetto dell'Anagrafica: il modulo non ha un'anagrafica
delle aziende propria. Regime fiscale e periodicità IVA si leggono dai
dati fiscali storicizzati dell'Anagrafica (`DatiFiscali`), che restano
l'unica fonte; i clienti e i fornitori delle aziende sono invece entità
del modulo (`Controparte`), separate per azienda, perché non sono soggetti
dello studio.

## Flusso di lavoro

"Contabilità" apre l'elenco dei soggetti (di default i soli clienti) con
lo stato della contabilità. Per un soggetto senza contabilità si apre il
form di apertura: piano dei conti modello e date del primo esercizio. La
scheda dell'azienda, per esercizio, ha le sezioni:

- **Riepilogo**: ultime registrazioni, regime e periodicità IVA, fatture
  elettroniche da registrare, liquidazioni dell'anno; da qui si apre un
  nuovo esercizio e si chiude (o riapre) quello scelto;
- **Prima nota**: registrazioni dell'esercizio con filtri per causale,
  testo e periodo; "Nuova registrazione" per causale;
- **Fatture elettroniche**: caricamento dei file, elenco per stato e
  direzione, registrazione o scarto;
- **Clienti e fornitori**: controparti con i sottoconti del partitario;
- **Registri IVA**: registri e sezionali, stampa per mese o periodo,
  corrispettivi del mese in una sola maschera, attività con contabilità
  separata;
- **Liquidazioni IVA**: periodi dell'anno con elaborazione, prospetto,
  acconto, pro-rata, limiti della ventilazione e comunicazioni delle
  liquidazioni periodiche;
- **IVA di gruppo**: per la controllante opzione, società partecipanti,
  liquidazioni, acconto e comunicazioni del gruppo; per le controllate il
  gruppo a cui partecipano;
- **Giornale** e **Bilancio di verifica** per periodo, con le schede
  contabili dei sottoconti;
- **Stampe definitive** del libro giornale e dei registri IVA;
- **Piano dei conti** dell'azienda.

A livello di studio "Piani dei conti" elenca i modelli (della suite e
dello studio) e "Codici IVA" i codici della suite e quelli aggiunti dallo
studio.

## Installazione

Il modulo è registrato in `MODULE_FOLDERS` e in `ModuloSuite`; richiede
Telonio, Anagrafica (>= 0.6.0, per regime fiscale, periodicità IVA e ventilazione) e
Deleghe. `sisma aggiorna-database` esegue per un'installazione nuova o
esistente:

1. `Contabilita/Database/schema.sql` (per i database della versione 0.1,
   prima `Contabilita/Database/aggiornamento-0.2.sql`: comunicazioni
   delle liquidazioni periodiche, stampe definitive, colonna
   `riga_iva.altri_prodotti`; per quelli delle versioni 0.1 e 0.2
   `Contabilita/Database/aggiornamento-0.3.sql`: colonne
   `riga_iva.natura_inversione`, `riga_iva.qualifica`,
   `riga_iva.aliquota_teorica`, `riga_iva.ripartizione`,
   `registrazione_contabile.importazione`, `registro_iva.unita_locale_id`,
   `registro_iva.attivita_iva_id`, `liquidazione_iva.trimestre_anticipato`,
   `liquidazione_iva.trimestre_separato`, `liquidazione_iva.credito_trimestrale`,
   `liquidazione_iva.gruppo_iva_id`, `acconto_iva.gruppo_iva_id`,
   `comunicazione_lipe.gruppo_iva_id`, `modulo_lipe.liquidazione_gruppo_iva_id`,
   tabelle `prorata_iva`, `attivita_iva`, `gruppo_iva` e
   `liquidazione_gruppo_iva`; `liquidazione_attivita` e
   `partecipante_gruppo_iva` le crea `schema.sql`; per quelli della versione
   0.3 `Contabilita/Database/aggiornamento-0.4.sql`: tabella
   `credito_trimestrale_iva`, con i crediti già annotati sulle liquidazioni,
   colonne `riga_iva.percentuale_compensazione`, `riga_iva.operazione_diversa`,
   `attivita_iva.regime_detrazione_iva`)
2. `Contabilita/Database/dati-riferimento.sql` (piano dei conti standard e
   codici IVA della suite, generato da `sisma tabelle-contabilita --sql`)

Gli schemi XSD ufficiali (comunicazione delle liquidazioni periodiche,
fattura elettronica, firme XML) sono nel modulo, in
`Application/Assets/xsd`, con i riferimenti tra file resi locali: la
validazione non accede alla rete. `sisma specifiche-contabilita` li
scarica di nuovo dalle fonti ufficiali (pagina delle specifiche tecniche
della LIPE dell'Agenzia delle Entrate, schema VFPR12 della fattura,
schema W3C xmldsig) e verifica che si carichino; con
`--fatturapa=URL` si indica lo schema di una nuova versione delle
specifiche della fattura.

Le fixture (`sisma fixtures`) coprono tutte le funzioni del modulo:

- **Alfa Consulting S.r.l.** (contabilità ordinaria, IVA mensile), esercizio
  2026 con il piano standard: cliente Beta, fornitore Gamma (costo
  abituale "Consulenze"), fornitore Delta (lavori edili), Comune di Esempio
  (pubblica amministrazione); fatture di vendita del 15 gennaio (22% e
  10%) e del 22 gennaio al Comune in scissione dei pagamenti, acquisto del
  20 gennaio, nota di credito a Beta, acquisto da Delta con subappalto in
  inversione contabile (RC22), incasso da Beta; sezionale delle vendite
  "B"; fattura elettronica ricevuta da Epsilon (importata, da
  registrare) e da Zeta Pulizie (non conforme allo schema: manca il
  regime fiscale del cedente, importata con l'avviso); fattura a Beta del
  12 febbraio; liquidazioni di gennaio con il giroconto (172 euro, 6001 al
  16 febbraio), febbraio (con versamenti auto UE e un credito d'imposta
  d'esempio, righi VP10 e VP11) e marzo; acconto IVA previsionale (528 euro, 6013
  al 28 dicembre), nell'archivio di Deleghe F24; comunicazione delle
  liquidazioni del primo trimestre (tre moduli, dichiarante
  l'amministratore unico con codice carica 1, intermediario dello studio),
  inviata; stampe definitive di gennaio del libro giornale e del registro
  delle vendite (pagina 2026/1: le registrazioni di gennaio non sono più
  modificabili); partite aperte nello scadenzario: RiBa della fattura n. 4
  a Beta insoluta e incassata di nuovo con un abbuono di 4 euro, acconto di
  1.000 euro di Beta, incasso dal Comune al netto dell'IVA in scissione
  dei pagamenti, pagamento a Gamma al netto della ritenuta d'acconto
  (816 euro di bonifico e 160 di ritenuta da versare, con il versamento
  1040 di febbraio registrato in Deleghe per il 16 marzo) e compensazione
  della fattura n. 5 a Delta con il debito per la fattura D/45;
- **Ferri Lucia, "Alimentari Ferri"** (ditta individuale, contabilità
  semplificata, IVA trimestrale, ventilazione dei corrispettivi nei dati
  fiscali): acquisti di merci per la rivendita al 4%, 10% e 22% da un
  grossista, un acquisto di articoli per la casa da rivendere ("altri
  prodotti") e un acquisto di materiali di consumo escluso dalla
  ventilazione; corrispettivi di gennaio, febbraio e marzo registrati con
  la maschera mensile (da ventilare dal lunedì al sabato, bevande già
  distinte al 22% il sabato); liquidazione del primo trimestre con la
  ventilazione, gli interessi dell'1% e il giroconto (6031 al 18 maggio);
  comunicazione del primo trimestre in bozza (un modulo, senza
  dichiarante); esercizio 2025 chiuso con acquisti e corrispettivi di
  dicembre, base della verifica dei limiti della ventilazione per il
  2026.

## Modello dei dati

| Entità | Contenuto |
|---|---|
| `PianoConti` | piano modello della suite (senza studio né soggetto, id sotto 1000), modello dello studio (con lo studio) o piano dell'azienda (con il soggetto, uno per azienda), con il modello da cui è copiato |
| `Conto` | mastro (`NN`), conto (`NN.NN`) o sottoconto (`NN.NN.NNNN`) con il conto superiore (`parentConto`, entità autoreferenziata del framework), natura, funzione nelle proposte e voce CEE |
| `CodiceIva` | aliquota o natura (N1-N7), detraibilità proposta, integrazione dell'imposta (inversione contabile) o corrispettivi da ventilare; della suite (id sotto 1000) o dello studio |
| `EsercizioContabile` | esercizio dell'azienda per anno di chiusura, con le date e lo stato chiuso |
| `RegistroIva` | registro vendite, acquisti o corrispettivi dell'azienda, principale o sezionale, con l'eventuale unità locale (sede operativa dell'Anagrafica) delle sue operazioni e l'attività separata a cui appartiene |
| `AttivitaIva` | attività con contabilità separata (art. 36): numero (ordine dei moduli della dichiarazione IVA), codice ATECO, periodicità propria, anni |
| `LiquidazioneAttivita` | liquidazione di un'attività separata compresa nella liquidazione dell'azienda: periodo dell'attività, IVA esigibile e detraibile, pro-rata, interessi |
| `GruppoIva` | opzione della controllante per la liquidazione IVA di gruppo (art. 73, c. 3), dal primo anno fino alla revoca, con il soggetto che possiede la maggioranza della controllante (VG1) |
| `PartecipanteGruppoIva` | controllata del gruppo: anno di ingresso e di uscita, cessazione del controllo in corso d'anno, possesso (data, percentuale, detentore), sola catena di controllo |
| `LiquidazioneGruppoIva` | liquidazione periodica del gruppo: debiti, crediti e interessi trasferiti, riporti, credito di gruppo dell'anno precedente, acconto, dovuto o credito |
| `Controparte` | cliente o fornitore dell'azienda con dati fiscali, sede, recapiti per la fatturazione, pubblica amministrazione (scissione dei pagamenti), sottoconti del partitario, costo o ricavo e codice IVA abituali |
| `RegistrazioneContabile` | registrazione di prima nota: numero nell'esercizio, causale, documento, controparte e, per i documenti IVA, registro, protocollo, data di competenza ed esigibilità, imposta di bollo virtuale delle fatture emesse; origine e riferimento per le registrazioni generate |
| `RigaContabile` | riga in partita doppia sul sottoconto, con la controparte delle righe del partitario |
| `Partita` | partita del partitario: controparte e sottoconto, registrazione che la apre, tipo (documento, insoluto con il documento riaperto, acconto, nota di credito da abbinare), documento, scadenza e importo |
| `Abbinamento` | chiusura di una partita da parte di una registrazione: data, importo, modo (incasso o pagamento, ritenuta, compensazione, nota di credito, acconto, abbuono o perdita), partita collegata (acconto o nota usati) e flag "automatico" della migrazione |
| `RigaIva` | riga del registro: codice IVA, imponibile e imposta con il segno del documento, detraibilità, acquisto per rivendita (ventilazione) e di altri prodotti, qualifica, aliquota teorica, ripartizione degli acquisti promiscui tra le attività, contropartita |
| `LiquidazioneIva` | liquidazione del mese o trimestre con il prospetto (IVA esigibile, di cui ventilata, e detraibile, riporti, acconto, interessi, dovuto o credito), la scadenza, il giroconto, il trimestre anticipato delle attività trimestrali e il gruppo IVA a cui il risultato è trasferito |
| `AccontoIva` | acconto dell'anno con metodo, base e importo; con il gruppo IVA l'acconto del gruppo versato dalla controllante |
| `CorrispettivoGiornaliero` | corrispettivi di una giornata per codice IVA (comprensivi dell'imposta) della registrazione mensile dei corrispettivi |
| `FatturaElettronica` | fattura importata (una per documento del lotto): dati principali, XML completo, impronta, stato e registrazione |
| `ComunicazioneLipe` | comunicazione delle liquidazioni periodiche del trimestre: progressivo (identificativo), dichiarante e codice carica, intermediario e impegno, stato, data e protocollo dell'invio; con il gruppo la comunicazione della liquidazione del gruppo |
| `ModuloLipe` | modulo del quadro VP (mese, trimestre o mese con il trimestre anticipato) con i righi da VP2 a VP14 fissati alla preparazione e la liquidazione di origine (dell'azienda o del gruppo) |
| `ProrataIva` | pro-rata di detrazione provvisorio dell'anno, dell'azienda o dell'attività separata |
| `Cespite` | bene ammortizzabile: conti dell'immobilizzazione, del fondo e dell'ammortamento, acquisto ed entrata in funzione, costo, coefficiente, riduzione del primo esercizio, deduzione integrale dei beni minori, coefficiente fiscale, parte deducibile e costo fiscale massimo, fondo pregresso, dismissione con il prezzo, fattura di acquisto |
| `RateoRisconto` | costo o ricavo a cavallo dell'esercizio: conto economico, importo, periodo di competenza, già registrato nell'esercizio (risconto) o nel successivo (rateo) |
| `CreditoTrimestraleIva` | credito del trimestre chiesto con il modello IVA TR, della società o del gruppo, escluso dal riporto delle liquidazioni |
| `StampaDefinitiva` | stampa definitiva di un periodo del libro giornale (esercizio) o di un registro IVA (anno solare) con prima e ultima pagina e righe per pagina |

### Enum o entità

Con il criterio della suite (enum per i vocabolari chiusi e stabili con
comportamento, entità per le tabelle ampie o aggiornabili):

- **enum**: livello e natura del conto, funzioni dei conti, causali
  (fissano il registro IVA, il segno, la controparte richiesta e lo schema
  delle righe proposte: non sono personalizzabili dallo studio, la
  descrizione della registrazione resta libera), tipi di registro,
  esigibilità, nature IVA e tipi di documento della fattura elettronica
  (fissati dalle specifiche tecniche), voci CEE degli schemi di bilancio
  degli artt. 2424 e 2425 c.c. (`VoceCee`, fissate dalla legge), metodi
  dell'acconto, codici di carica del dichiarante e impegno alla
  presentazione (tabelle delle specifiche), stati della comunicazione,
  libri della stampa definitiva;
- **entità**: piani e conti (personalizzabili da studio e azienda), codici
  IVA (lo studio ne aggiunge, ad esempio per detraibilità particolari),
  registri (sezionali dell'azienda).

### Righe con i dati della registrazione

`RigaContabile` e `RigaIva` riportano soggetto e data (di registrazione o
di competenza IVA) e `RigaIva` anche il registro: le schede contabili, il
bilancio di verifica, i registri e le liquidazioni leggono così i
movimenti di un periodo con una sola interrogazione, senza join. I campi
sono scritti solo da `Services\SalvataggioRegistrazione`, che riscrive le
righe a ogni salvataggio della registrazione.

## Piano dei conti

Il piano standard della suite (`Database/sorgenti/conti.csv`) ha 193
conti per imprese in contabilità ordinaria o semplificata, con il
raccordo alle voci CEE sui sottoconti (i fondi rettificativi, come i
fondi ammortamento, hanno la natura e la voce dei beni che rettificano). Il
piano dei conti non è fissato da norme: le sorgenti non hanno una fonte
ufficiale, solo il raccordo è verificato sugli schemi di legge.

Lo studio copia un modello della suite e lo personalizza; all'apertura
l'azienda riceve una copia indipendente del modello scelto
(`Services\CopiaPianoConti`): le modifiche successive ai modelli non
toccano i piani già copiati. I modelli della suite non si modificano (si
aggiornano con `sisma tabelle-contabilita`).

Le **funzioni** indicano alle proposte automatiche i conti da usare:
clienti e fornitori (conti del partitario), IVA sulle vendite e sugli
acquisti, Erario c/IVA, Erario c/acconto IVA, banca, cassa, ricavi,
corrispettivi, costi, arrotondamenti. Ogni funzione è assegnata al più a
un conto del piano, al livello previsto. Un conto si elimina solo senza
sottoconti, movimenti o controparti; altrimenti si rende non attivo.

### Righi della dichiarazione dei redditi

Ogni conto economico può indicare il rigo del quadro RG (impresa in
contabilità semplificata) o RE (arti e professioni) del modello Redditi in
cui confluisce, anche con il codice della voce per i righi RG10 e RG22
("RG22:3" canoni di locazione e noleggio); "NO" lo esclude. Se il rigo non
è indicato vale quello proposto dalla voce di conto economico (servizio
`RighiRedditi`). I costi deducibili solo in parte (auto, telefonia) hanno
sul conto la percentuale deducibile e il rigo del quadro RF in cui va la
parte indeducibile (RF16 imposte, RF18 mezzi di trasporto, RF31 codice 99
altre variazioni, predefinito); i ricavi imponibili solo in parte hanno la
percentuale imponibile e la parte non imponibile va tra le variazioni in
diminuzione (RF55 codice 99). Il servizio `ComponentiReddito` calcola i
componenti per cassa (le fatture concorrono per la parte incassata o
pagata nell'anno secondo gli abbinamenti delle partite aperte, vedi sotto;
dei costi conta la parte deducibile, dei ricavi la parte imponibile) e
l'utile per competenza con le variazioni in aumento e in diminuzione.

## Partite aperte e scadenzario

Le righe di una registrazione sui sottoconti dei clienti e dei fornitori
formano un gruppo per controparte e sottoconto (`Services\PartiteAperte`),
con l'importo nel segno del partitario (dare per i clienti, avere per i
fornitori):

- **in apertura** (fatture, addebiti, insoluti) nasce una `Partita` per
  l'intero importo; con la scissione dei pagamenti la partita è al netto
  dell'IVA stornata dal cliente. La fattura si può abbinare agli acconti e
  alle note di credito ancora aperti della controparte;
- **in chiusura** (incassi, pagamenti, note di credito, compensazioni)
  l'importo si abbina alle partite aperte con un `Abbinamento` per partita
  e modo di chiusura: incasso o pagamento, ritenuta d'acconto (subita dal
  professionista o operata sul fornitore: conti con funzione "Erario
  c/ritenute subite" e "Erario c/ritenute da versare"), abbuono, sconto o
  perdita (righe sui conti economici), compensazione (righe dell'altro
  ruolo della stessa controparte) e nota di credito. L'importo non
  abbinato è una nuova partita: acconto, se indicato, o nota di credito
  da abbinare;
- l'**insoluto** (giroconto che addebita di nuovo il cliente) riapre il
  documento scelto: è una partita di tipo insoluto collegata al documento.

L'abbinamento è **obbligatorio**: il form della prima nota mostra per ogni
gruppo le partite abbinabili (registrate non dopo la registrazione, dalla
scadenza più vecchia) con la proposta, che chiude le partite dalla più
vecchia e ripartisce l'importo tra i modi di chiusura in proporzione (un
incasso di due parcelle con la ritenuta: per ciascuna l'80 per cento di
incasso e il 20 di ritenuta). La registrazione si salva solo dopo che la
proposta è stata mostrata; il salvataggio controlla che ogni partita non
riceva più del suo residuo e che l'importo sia abbinato per intero o
registrato come acconto. Le registrazioni dirette (fixture, test) usano la
proposta. Le partite già abbinate da altre registrazioni non si eliminano
né si riducono sotto quanto abbinato.

Le **scadenze** del documento sono rate della sua partita
(`ScadenzaPartita`: data, importo, modalità di pagamento), indicate nel
form della prima nota sotto gli abbinamenti e proposte
(`Services\ScadenzePagamento`):

- dai dati di pagamento della fattura elettronica (`DettaglioPagamento`):
  la scadenza indicata o la data di riferimento più i giorni dei termini,
  con gli importi riportati in proporzione all'importo della partita (la
  fattura indica di solito il netto da pagare, ad esempio senza la
  ritenuta o l'IVA in scissione dei pagamenti);
- altrimenti dai termini abituali della controparte: giorni dalla data del
  documento ("30,60,90"), a fine mese se indicato (30 giorni fine mese
  di una fattura del 10 gennaio: 28 febbraio), con la modalità abituale e
  importi uguali (il resto dell'arrotondamento sull'ultima rata);
- altrimenti una rata alla data del documento.

Le rate devono sommare l'importo della partita; la rata unica lo prende da
sola. Gli abbinamenti restano sulla partita e il residuo si ripartisce
sulle rate in ordine di scadenza, quindi incassi e pagamenti chiudono
prima le rate più vecchie. La scadenza della partita è quella della prima
rata. Le partite registrate prima delle rate non ne hanno e valgono come
rata unica alla loro scadenza.

Lo **scadenzario** dell'azienda elenca per controparte le partite aperte (o
tutte) con la prossima scadenza, le rate con il residuo, gli abbinamenti e
il residuo, e confronta il totale con il saldo del sottoconto: una
differenza indica righe sul sottoconto senza la controparte o
registrazioni senza partite. La scheda della fattura elettronica mostra i
dati di pagamento (scadenze, modalità, IBAN).

Per **cassa** (quadri RG ed RE del modello Redditi) ogni fattura concorre
in proporzione a quanto abbinato nell'anno come incasso o pagamento,
ritenuta (parte del compenso), compensazione, nota di credito e acconto
(alla data dell'acconto); l'insoluto toglie l'importo nell'anno in cui si
registra e i suoi incassi lo ripristinano. Abbuoni e perdite non contano
come incasso e i conti economici delle loro registrazioni non concorrono
(il ricavo non incassato non è tassato). Le ritenute abbinate nell'anno
agli incassi dei clienti sono proposte nel rigo RE26. `ComponentiReddito`
espone anche i totali dei ricavi e dei costi dell'ultimo calcolo per cassa,
usati per il reddito delle società di persone imputato ai soci (quadro RH).

All'aggiornamento alla versione 0.8.1 `sisma aggiorna-database` (quindi
`aggiorna.sh`) crea le partite delle registrazioni che non le hanno
(`Database/aggiornamento-0.8.1.php`); `sisma partite-aperte [--soggetto=ID]`
lo ripete per le registrazioni che non le hanno: in ordine di giornale le fatture aprono le
partite e incassi, pagamenti e note di credito le chiudono dalla più
vecchia, con l'eccedenza come acconto. Questi abbinamenti sono segnati
"da verificare" nello scadenzario finché non si confermano o si
modificano dal form della registrazione. Le aziende senza partite sono
calcolate per cassa con la chiusura dalla più vecchia, con un avviso.

## Clienti e fornitori

Ogni controparte ha un sottoconto per ruolo sotto i conti con funzione
clienti e fornitori (`Services\Partitario`), aperto al salvataggio o alla
prima registrazione, con il primo codice libero e la denominazione della
controparte. La scheda contabile del sottoconto è il partitario della
controparte. La controparte si elimina, con i suoi sottoconti, solo se non
ha registrazioni. I termini di pagamento abituali (giorni, fine mese,
modalità) propongono le rate dei documenti senza dati di pagamento.

## Prima nota

Il form della registrazione ha la testata (data, controparte, documento,
registro IVA, competenza ed esigibilità per i documenti IVA, totale), le
righe IVA e le righe in partita doppia. Le righe sono form del framework
(`RigaIvaForm`, `RigaContabileForm`), una per riga, gestite dal controller
perché le righe tolte vanno cancellate e i controlli riguardano l'insieme
(`Services\ControlloRegistrazione`):

- esercizio aperto e data compresa;
- controparte e documento richiesti dalla causale; documento non
  successivo alla registrazione;
- registro del tipo della causale; competenza IVA non successiva alla
  registrazione e, per gli acquisti, non anteriore al documento;
- codici IVA disponibili, imposta coerente con l'aliquota (tolleranza di
  un euro per gli arrotondamenti del documento), nessuna imposta sulle
  operazioni con natura, integrazione solo negli acquisti; totale
  coerente;
- almeno due righe su sottoconti attivi del piano dell'azienda, ciascuna
  in dare o in avere, e quadratura.

Gli importi IVA si inseriscono come nel documento (positivi anche nelle
note di credito): il segno è quello della causale. L'imposta lasciata
vuota si calcola dall'aliquota (anche nel browser), la detraibilità dal
codice. "Proponi le righe" (`Services\PropostaRighe`) compone la partita
doppia:

| Causale | Righe proposte |
|---|---|
| fattura di vendita, nota di credito emessa | cliente in dare per il totale; ricavi in avere per l'imponibile; IVA sulle vendite in avere; con la scissione dei pagamenti l'IVA è stornata dal cliente |
| corrispettivi | cassa in dare; corrispettivi (o ricavi) e IVA in avere |
| fattura di acquisto, nota di credito ricevuta | fornitore in avere per il totale del documento; costi in dare per l'imponibile e l'IVA indetraibile; IVA sugli acquisti in dare per la parte detraibile; con l'integrazione (inversione contabile, intracomunitari, esteri) l'imposta va anche in avere sull'IVA delle vendite e non nel debito verso il fornitore |
| incasso, pagamento | banca contro cliente o fornitore |
| versamento IVA | Erario c/IVA contro banca |

La contropartita è quella della riga IVA, altrimenti quella abituale
della controparte, altrimenti il conto con la funzione. Le note di credito
hanno le righe nella sezione opposta.

Il numero della registrazione è progressivo nell'esercizio e non si
riutilizza; il protocollo IVA è progressivo per registro e anno solare
(artt. 23 e 25 D.P.R. 633/1972) e si riassegna se cambiano registro o
anno. Le registrazioni degli esercizi chiusi non si modificano né si
eliminano; quelle generate dalla liquidazione si aggiornano rielaborandola.

## Corrispettivi del mese

"Corrispettivi del mese" (dai registri IVA, dalla prima nota o dal
riepilogo) apre la griglia del mese: una riga per giorno e una colonna per
codice IVA (le aliquote più comuni, quelle già usate nel mese e nel mese
precedente; altre si aggiungono dalla maschera), con gli importi
comprensivi dell'imposta e i totali, l'imponibile e l'imposta in
anteprima. Di default si apre il mese precedente fino al 15 e quello in
corso dopo.

Il salvataggio (`Services\CorrispettiviMensili`) produce un'unica
registrazione per mese e registro, come consente l'art. 6, c. 4, D.P.R.
695/1996 per le operazioni con scontrino, ricevuta o documento commerciale
(annotazione anche con unica registrazione entro il 15 del mese
successivo):

- data di registrazione e di competenza IVA all'ultimo giorno del mese;
- righe IVA con il totale del mese per codice e l'imposta scorporata (art.
  27, c. 4, D.P.R. 633/1972: corrispettivi divisi per 100 più l'aliquota e
  moltiplicati per l'aliquota, al centesimo); nessuna imposta per i codici
  con natura; i codici di integrazione non sono ammessi;
- partita doppia proposta come per i corrispettivi (cassa contro
  corrispettivi e IVA sulle vendite) e controlli della prima nota;
- dettaglio giornaliero salvato in `CorrispettivoGiornaliero` e riportato
  nella stampa del registro dei corrispettivi, che contiene così
  l'ammontare globale giornaliero distinto per aliquota (art. 24 D.P.R.
  633/1972).

La registrazione è identificata da registro e mese (origine
`Corrispettivi`): salvare di nuovo il mese la aggiorna (stessi numero e
protocollo), azzerare tutti gli importi la elimina; dalla prima nota si
modifica solo tramite la maschera. Un totale mensile negativo per codice
(resi superiori ai corrispettivi) non è ammesso.

Dal 1° gennaio 2027 le norme sui registri e sui corrispettivi confluiscono
nel Testo unico IVA (D.Lgs. 19 gennaio 2026, n. 10, art. 87 per la
registrazione dei corrispettivi): i riferimenti del modulo andranno
aggiornati.

## Ventilazione dei corrispettivi

I commercianti al minuto ammessi (D.M. 24 febbraio 1973, art. 1:
alimentari e dietetici, tessili, vestiario e calzature, igiene personale,
farmaceutici) possono registrare i corrispettivi senza distinguerli per
aliquota e ripartirli nella liquidazione in proporzione agli acquisti dei
beni destinati alla rivendita (art. 24, c. 3, D.P.R. 633/1972; confermata
dal 2027 nell'art. 87 del Testo unico IVA). L'opzione è nei dati fiscali
storicizzati dell'Anagrafica (`ventilazioneCorrispettivi`, letta per
l'anno come la periodicità).

- **Corrispettivi**: nella maschera mensile delle aziende in ventilazione
  la prima colonna è il codice `VEN` (corrispettivi da ventilare, al
  lordo e senza imposta); i corrispettivi già distinti si indicano nelle
  colonne delle aliquote. Il codice `VEN` vale solo nel registro dei
  corrispettivi.
- **Acquisti per rivendita**: nelle righe IVA degli acquisti la colonna
  "Riv." (per le aziende in ventilazione) indica i beni destinati alla
  rivendita: "Rivendita" per i prodotti delle categorie ammesse, "Altri
  prodotti" per gli altri beni rivenduti, che concorrono ai rapporti e al
  limite dell'art. 4; dalle fatture elettroniche si propone "Rivendita" se
  la contropartita è quella degli acquisti di merci. Sono esclusi i beni non rivenduti, quelli
  per altre finalità e gli ammortizzabili (art. 3 D.M.). La stampa del
  registro degli acquisti ne riporta i totali per aliquota, IVA compresa.
- **Liquidazione** (`Services\VentilazioneCorrispettivi`): i corrispettivi
  da ventilare del periodo si ripartiscono con i rapporti degli acquisti
  per rivendita, comprensivi dell'imposta, registrati dal 1° gennaio alla
  fine del periodo ("rapporti progressivi", art. 3 D.M.); dalla quota di
  ogni aliquota si scorpora l'imposta, che si aggiunge all'IVA esigibile
  ("di cui dei corrispettivi ventilati" nel prospetto, con la tabella
  della ripartizione). Gli acquisti con natura concorrono senza imposta.
  Senza acquisti per rivendita dall'inizio dell'anno la liquidazione non
  si elabora.
- **Giroconto**: i corrispettivi sono registrati al lordo nei ricavi; il
  giroconto della liquidazione storna dal conto dei corrispettivi (o dei
  ricavi) l'imposta ventilata.
- **Ventilazione annuale**: con l'ultimo periodo dell'anno si ripartiscono
  i corrispettivi dell'intero anno con i rapporti di fine anno e la
  differenza con l'imposta ventilata nelle liquidazioni compare come
  avviso, da riportare nella dichiarazione annuale.

Un avviso segnala i corrispettivi da ventilare di un'azienda senza
l'opzione nei dati fiscali.

**Limiti** (`Services\RequisitiVentilazione`), verificati sui registri
dell'anno precedente e mostrati nella pagina delle liquidazioni delle
aziende con l'opzione; superati, la liquidazione avvisa che nell'anno la
ventilazione non si applica:

- art. 4 D.M.: acquisti di altri prodotti non oltre il 50% del totale
  degli acquisti e delle importazioni registrati;
- art. 5 D.M.: cessioni fatturate non oltre il 20% del volume d'affari
  (vendite fatturate e corrispettivi al netto dell'imposta, con
  l'imponibile della ventilazione annuale per quelli da ventilare);
- in entrambi sono escluse le cessioni di immobili e di beni strumentali,
  riconosciute dalla contropartita patrimoniale della riga IVA.

Se l'anno precedente non è in contabilità i limiti si verificano a parte.
La ventilazione non è stata abolita: lo schema di decreto del 22 luglio
2025 che la sopprimeva dal 2026 non è passato nel testo definitivo
(D.Lgs. 186/2025) e il Testo unico IVA la riprende nell'art. 87.

## Liquidazioni IVA

`Services\CalcoloLiquidazioneIva` somma le righe IVA con data di
competenza nel periodo:

- vendite e corrispettivi: imposta a debito, salvo la scissione dei
  pagamenti (dovuta dalla pubblica amministrazione);
- acquisti: imposta detraibile e, per i codici di integrazione, la stessa
  imposta anche a debito;
- fatture a esigibilità differita e per cassa: imponibile e imposta nel
  riepilogo del periodo del documento, l'imposta esigibile o detraibile
  nel periodo in cui matura (vedi sotto).

### Esigibilità differita e IVA per cassa

L'esigibilità della registrazione (`EsigibilitaIva`) può essere
**differita** (art. 6, c. 5, D.P.R. 633/1972; dal 2027 art. 24, c. 5,
D.Lgs. 10/2026: cessioni allo Stato e agli enti pubblici non in scissione
dei pagamenti, farmaci dei farmacisti, cessioni ai soci delle cooperative)
o **per cassa** (art. 32-bis D.L. 83/2012; dal 2027 art. 132 D.Lgs.
10/2026; D.M. 11 ottobre 2012), per le aziende con l'opzione nei dati
fiscali dell'Anagrafica. Nel file della fattura elettronica entrambe sono
"D" (`EsigibilitaIva::getValoreFattura`).

Per le aziende con l'IVA per cassa le nuove fatture emesse e ricevute sono
proposte "per cassa" (anche dalla fattura elettronica: le emesse con
esigibilità differita, le ricevute salvo le integrazioni); si indicano
immediate a mano le operazioni escluse verso privati (D.M., art. 2). I
controlli rifiutano l'IVA per cassa sulle note di credito e i
corrispettivi, sulle importazioni e sulle operazioni in inversione
contabile (D.M., artt. 2 e 3). L'esigibilità differita del fornitore non
sposta la detrazione del cessionario (art. 32-bis, c. 1): le fatture
ricevute "D" si propongono immediate.

`Services\IvaSospesa` calcola la quota del documento divenuta esigibile
(vendite) o detraibile (acquisti per cassa) fino a una data: importo
pagato sul totale della partita, dagli abbinamenti dello scadenzario di
ogni modo salvo abbuoni e perdite (D.M., art. 4, c. 4, e art. 5, c. 2:
incasso parziale in proporzione); per l'IVA per cassa l'imposta matura
comunque decorso un anno dalla data del documento (art. 32-bis, c. 1; la
procedura concorsuale del cliente, che toglie il limite, non è
verificata). La quota non scende con gli insoluti e non matura prima della
data di competenza; le note di credito restano immediate. La liquidazione
somma l'imposta maturata nel periodo e la elenca nel prospetto ("Fatture a
esigibilità differita e per cassa"), con un avviso per le fatture sospese
senza partite. La comunicazione delle liquidazioni riporta nel VP4 e nel
VP5 l'imposta della liquidazione. Il giroconto della liquidazione sposta
l'imposta maturata: la parte sospesa resta sui conti dell'IVA su vendite e
acquisti.

### Plafond degli esportatori abituali

`Services\PlafondEsportatori` verifica mese per mese il plafond degli
acquisti e delle importazioni senza IVA con la dichiarazione d'intento
(art. 8, c. 1, lett. c, e c. 2, D.P.R. 633/1972, art. 1 D.L. 746/1983 e
art. 2, c. 2, L. 28/1997; dal 2027 artt. 45, c. 1, lett. d, 46 e 47
D.Lgs. 10/2026). Gli utilizzi sono le righe degli acquisti con natura N3.5
per data del documento (momento di effettuazione), distinte le
importazioni; il plafond si forma con le operazioni N3.1-N3.4 registrate
nell'anno precedente (metodo solare) o nei dodici mesi precedenti al netto
degli utilizzi degli stessi mesi (metodo mensile), scelto per anno
nell'esercizio contabile dalla pagina delle liquidazioni
(`esercizio_contabile.plafond_mensile`). La pagina mostra il plafond
disponibile al 1° gennaio, il disponibile, l'utilizzato e il residuo di
ogni mese, e segnala lo status di esportatore abituale (operazioni
dell'anno precedente oltre il 10% del volume d'affari); la liquidazione
avvisa del primo superamento fino alla fine del periodo. Gli stessi dati
compongono il quadro VC della dichiarazione IVA, che propone la casella 3
del VC14 per il metodo mensile. Le dichiarazioni d'intento (protocolli,
fornitori e importi) non sono archiviate.

### Regime forfetario

Per le aziende nel regime forfetario (art. 1, cc. 54-89, L. 190/2014; dal
2027 artt. 232-239 D.Lgs. 117/2026), che non liquidano l'IVA, la pagina
delle liquidazioni mostra (`Services\RegimeForfetario`):

- i ricavi e compensi percepiti nell'anno, per cassa come nel quadro LM
  (`ComponentiReddito::perCassa`), con l'avviso oltre 85.000 euro (il
  regime cessa dall'anno successivo: c. 54, lett. a, e c. 71; dal 2027
  artt. 232 e 238) e oltre 100.000 euro (cessa nell'anno stesso e l'IVA è
  dovuta dalle operazioni che superano il limite); gli altri requisiti
  (spese per lavoro, redditi di lavoro dipendente, cause di esclusione)
  non sono verificati;
- l'IVA degli acquisti di cui sono debitori, cioè delle righe con i codici
  di integrazione (inversione contabile interna, acquisti intracomunitari,
  servizi dall'estero), con il versamento da registrare in Deleghe F24:
  entro il 16 del secondo mese successivo al trimestre per le operazioni
  dal 1° ottobre 2025 (c. 58, lett. e-bis, inserita dall'art. 6 del
  D.Lgs. 81/2025; dal 2027 art. 234, c. 1, lett. f; la decorrenza è quella
  della relazione illustrativa, alcuni commenti indicano il 13 giugno
  2025), prima entro il 16 del mese successivo (c. 60; dal 2027 art. 234,
  c. 3). Per il versamento trimestrale non risultano codici tributo
  dedicati: si propone quello dell'ultimo mese del trimestre (6003, 6006,
  6009, 6012), segnalato da verificare.

Le fatture emesse senza IVA hanno la natura N2.2; il bollo virtuale si
indica come per le altre aziende (pagina "Ritenute e bollo").

Con il **pro-rata di detrazione provvisorio** dell'anno (`ProrataIva`, art.
19, c. 5, e art. 19-bis D.P.R. 633/1972), per chi effettua anche
operazioni esenti, l'IVA detraibile del periodo è ridotta alla percentuale
indicata, con un avviso nel prospetto: è la percentuale definitiva
dell'anno precedente, conguagliata nella dichiarazione annuale. Si indica
nella pagina delle liquidazioni o la registra la dichiarazione IVA del
modulo Dichiarativi (origine `Dichiarativi`, che non sostituisce quella
inserita a mano); le liquidazioni già elaborate si rielaborano.

Le righe IVA degli acquisti in inversione contabile interna portano la
**tipologia** N6.1-N6.9 (`riga_iva.natura_inversione`: rottami, oro,
subappalto edile, fabbricati, cellulari, prodotti elettronici, comparto
edile, energia), proposta dall'importazione della fattura elettronica del
fornitore (natura del riepilogo) e indicata nella prima nota (colonna
"Tipologia RC"): determina il rigo del quadro VJ della dichiarazione IVA.

Altri dati delle registrazioni IVA usati dalla dichiarazione annuale:

- **qualifica** delle righe delle operazioni attive
  (`QualificaOperazione`, colonna "Qualifica"): oro da investimento,
  esenti occasionali o accessorie (art. 10, nn. 1-9), esenti del n.
  27-quinquies, esenti equiparate alle imponibili e non soggette senza
  diritto alla detrazione (campi del VF34 per il pro-rata), cessioni di
  beni ammortizzabili e passaggi interni (VE40, proposta anche dal tipo
  documento TD26 della fattura elettronica); esenti con la natura N4, non
  soggette con la N2.1;
- **aliquota teorica** delle righe degli acquisti con dichiarazione
  d'intento (N3.5, colonna "Aliq. teor."): l'IVA non assolta del VF35 per
  il pro-rata (22% se non indicata);
- **importazione**: casella della testata degli acquisti con documento
  doganale (VF28 campi 3 e 4, colonna 2 del VC per quelle con
  dichiarazione d'intento);
- **unità locale** del registro o sezionale IVA (sede operativa
  dell'Anagrafica): regione delle operazioni verso consumatori finali nel
  quadro VT.

## Attività con contabilità separata

Le attività gestite con contabilità separata per obbligo o per opzione
(art. 36 D.P.R. 633/1972; `AttivitaIva`, nella pagina dei registri) hanno
ciascuna i propri registri e sezionali: ogni registro appartiene a
un'attività e un registro degli acquisti senza attività raccoglie gli
acquisti promiscui, ripartiti riga per riga con le percentuali delle
attività (criteri oggettivi, art. 36, c. 3; colonne "% n" della prima
nota). Le vendite di registri senza attività vanno alla prima, con
l'avviso. L'opzione per i modi ordinari di una singola attività si indica
sull'attività con il primo e l'ultimo anno.

Le liquidazioni periodiche sono distinte per attività e compensate nel
periodo (`Services\AttivitaSeparate`, `LiquidazioneAttivita`): ciascuna
con le sue righe, le quote dei promiscui e il proprio pro-rata. La
periodicità è quella dell'azienda o, quando la separazione è obbligatoria
(o, per opzione, con il volume d'affari complessivo entro i limiti), quella
propria dell'attività: l'azienda liquida mensilmente se almeno
un'attività è mensile e le attività trimestrali sono liquidate nel mese
di fine trimestre (liquidazione anticipata ai fini della compensazione,
appendice "Contabilità separate" delle istruzioni IVA), con gli interessi
dell'1% sulla loro IVA dovuta; il quarto trimestre delle trimestrali si
chiude con la dichiarazione annuale (VL36). La comunicazione delle
liquidazioni riepiloga tutte le attività, con il mese e il trimestre
anticipato nello stesso modulo (rigo VP1).

In alternativa il trimestre si liquida **separatamente** dal mese: la scelta
si fa per ogni trimestre all'elaborazione del mese di fine trimestre
(casella "anticipa il trimestre"). La liquidazione del trimestre
(`trimestre_separato`) ha la propria scadenza e i codici 6031-6033, un modulo
distinto nella comunicazione e sta nella sequenza dei riporti mese, mese,
mese, trimestre, mese successivo. Lo stesso vale per il gruppo IVA con
società trimestrali e controllante mensile.

I passaggi interni di beni tra attività (art. 36, c. 5) si registrano con
un'autofattura nel registro delle vendite dell'attività cedente (qualifica
"bene ammortizzabile o passaggio interno", che li esclude dal volume
d'affari con il VE40) e negli acquisti dell'attività che li riceve.

## Regimi speciali agricoli

Con un regime speciale nei dati fiscali o sull'attività separata (più regimi
nella stessa impresa richiedono la contabilità separata) gli acquisti non si
detraggono e `Services\CalcoloLiquidazioneIva` determina la detrazione:

- **art. 34**: sulle cessioni di prodotti agricoli la percentuale di
  compensazione indicata sulla riga IVA (dalle percentuali dei decreti), anche
  per le non imponibili (IVA teorica, art. 34, c. 9); i conferimenti con la
  qualifica dedicata e i codici IVA `C2`-`C12.3` hanno la percentuale come
  aliquota. Nelle imprese miste (c. 5) le vendite senza percentuale sono
  operazioni diverse e si detrae l'IVA degli acquisti segnati nella prima nota,
  esclusi i beni ammortizzabili (conto di immobilizzazione). Di un acquisto
  promiscuo si segna su una riga a parte solo la quota specificamente imputabile
  (circolare 328/E/1997, par. 6.4). Gli acquisti per le
  connesse occasionali si segnano allo stesso modo e vanno nel rigo VF18.
  Le attività connesse occasionali (qualifica) danno il 50% dell'imposta, anche
  per chi applica l'IVA nei modi ordinari per opzione.
  Con il regime dell'art. 34, in prima nota il **prodotto** propone la
  percentuale dell'anno (`ProdottoAgricolo`), sempre modificabile;
- **art. 34-bis, agriturismo, enoturismo e oleoturismo**: 50% dell'imposta sulle
  operazioni imponibili; **associazioni** in agricoltura: un terzo.

Gli **agricoltori esonerati** (art. 34, c. 6) non hanno liquidazioni; le righe
IVA registrate servono a `Services\EsoneroAgricolo` per verificare il limite
di un terzo e il volume di 7.000 euro. Comunicazione delle liquidazioni: VP4 e VP5
con l'imposta del regime.

Chi acquista da un agricoltore esonerato (controparte con la spunta) emette
l'**autofattura** (`Services\FatturaPa\GeneratoreAutofattura`): dalla
registrazione si scarica il file FatturaPA, validato sullo schema, con soggetto
emittente CC e la percentuale di compensazione come aliquota, da caricare sul
portale con numero e data indicati nella registrazione. La suite non trasmette al
Sistema di Interscambio. TD01 e CC seguono la FAQ 62 e la prassi di settore, ma
le specifiche tecniche non trattano il caso; il regime del cedente è RF04, il
codice dello schema per gli artt. 34 e 34-bis.

Il conto abituale della controparte resta sulla riga IVA: è quello che
riconosce i beni ammortizzabili anche quando la riga non indica un conto.

## IVA di gruppo

Con la liquidazione IVA di gruppo (art. 73, c. 3; D.M. 13 dicembre 1979,
modificato dal D.M. 13 febbraio 2017) la controllante esercita l'opzione
nel quadro VG della dichiarazione IVA presentata nel primo anno e la
mantiene fino a revoca (`GruppoIva`); le controllate (`PartecipanteGruppoIva`)
entrano o escono dal 1° gennaio, o lasciano il gruppo in corso d'anno
quando il controllo cessa: l'ultimo mese (o trimestre) di controllo è
quello precedente la cessazione. Il requisito del controllo (più del 50%
del capitale dal 1° luglio dell'anno precedente) si verifica sui legami
"socio" con quota dell'Anagrafica, con un avviso se manca o non basta.

Le liquidazioni delle società partecipanti, compresa la controllante, nel
periodo di controllo trasferiscono il risultato al gruppo: senza riporto
del credito o del debito, credito annuale, acconto, rinvio sotto i 100
euro e versamento in Deleghe; gli interessi dei trimestrali si calcolano
e si trasferiscono; il giroconto va al conto con funzione "IVA di
gruppo" (06.04.0004 nel piano standard). La controllante elabora la
liquidazione del gruppo (`Services\LiquidazioneGruppo`,
`LiquidazioneGruppoIva`) sommando debiti e crediti trasferiti: mensile se
almeno una società è mensile, con i trimestri delle società trimestrali
nel mese di fine trimestre (il quarto con le dichiarazioni annuali),
riporto del credito e del debito del gruppo, credito di gruppo dell'anno
precedente indicato, acconto del gruppo (calcolato dalla pagina del
gruppo, metodo storico sull'ultima liquidazione di gruppo o analitico
sulle operazioni di tutte le società) e versamento della controllante in
Deleghe (riferimento `iva-gruppo-<anno>-<periodo>`).

Nelle comunicazioni delle liquidazioni periodiche le società partecipanti
indicano la partita IVA della controllante (e l'ultimo mese di controllo
nel trimestre della cessazione) e omettono i righi VP7, VP8, VP9 e VP13;
la controllante presenta anche la comunicazione del gruppo ("Liquidazione
del gruppo", VP4 e VP5 con debiti e crediti trasferiti, senza VP2, VP3,
VP10, VP11 e VP12), preparata dalla pagina del gruppo.

`Services\ElaborazioneLiquidazione` riporta il credito e il debito
rinviato del periodo precedente, compensa il credito annuale indicato
(compensazione "verticale" del credito della dichiarazione dell'anno
precedente: non si riporta da solo), scomputa i versamenti auto UE e i
crediti d'imposta indicati, l'acconto nell'ultimo periodo e applica le
regole di `Services\PeriodiIva`:

- mensili (art. 1, c. 1 e 4, D.P.R. 100/1998): versamento entro il 16 del
  mese successivo, codici 6001-6012;
- trimestrali per opzione (art. 7, c. 1, lett. a, e c. 3, D.P.R.
  542/1999): entro il 16 del secondo mese successivo, codici 6031-6033,
  con gli interessi dell'1%; il quarto trimestre si versa con il saldo
  della dichiarazione annuale entro il 16 marzo (6099, rateazione 0101),
  con l'1%;
- trimestrali per natura dell'attività (art. 74, c. 4, D.P.R. 633/1972;
  dal 2027 art. 139, c. 4, D.Lgs. 10/2026; casella dei dati fiscali
  dell'Anagrafica): senza gli interessi dell'1%, codici 6031-6033 e quarto
  trimestre liquidato e versato entro il 16 febbraio dell'anno successivo
  con il codice 6034; la comunicazione delle liquidazioni del quarto
  trimestre si compila con il trimestre "4" e il rigo VP14, e il quarto
  trimestre è la base dell'acconto storico. La liquidazione registra la
  qualifica (`liquidazione_iva.trimestrale_speciale`) e
  `LiquidazioneIva::isQuartoTrimestreConDichiarazione()` distingue il
  quarto trimestre dei trimestrali per opzione. Non si applica alle
  attività separate né alla liquidazione di gruppo, che restano con le
  regole dei trimestrali per opzione;
- debito non superiore a 100 euro (dalle liquidazioni del 2024, D.Lgs.
  1/2024; prima 25,82 euro): rinviato al periodo successivo, al più fino a
  novembre per i mensili (16 dicembre) e al terzo trimestre per i
  trimestrali (16 novembre), che si versano comunque;
- termini al primo giorno lavorativo e, dal 1° al 20 agosto, al 20 agosto
  (art. 3-quater D.L. 16/2012).

Il versamento è registrato con `ArchivioVersamenti::registra` (origine
`Contabilita`, riferimento `iva-<anno>-<periodo>`): la rielaborazione
sostituisce quello da versare o in delega in bozza, mentre per quelli in
deleghe confermate riporta le differenze. Il giroconto facoltativo, all'ultimo
giorno del periodo, chiude l'IVA delle vendite e degli acquisti (e
l'acconto) su Erario c/IVA. Se i registri cambiano dopo l'elaborazione il
prospetto lo segnala.

L'**acconto** (art. 6, c. 2, L. 405/1990, entro il 27 dicembre, 6013 o
6035) si calcola con il metodo storico (88% del dovuto per dicembre o per
il quarto trimestre dell'anno precedente, al lordo dell'acconto: per i
trimestrali la base vera è il saldo della dichiarazione annuale, qui
approssimato), previsionale (88% del dovuto presunto) o analitico
(liquidazione delle operazioni dal 1° dicembre, o dal 1° ottobre per i
trimestrali, al 20 dicembre); non è dovuto sotto 103,29 euro. Per i
trimestrali per natura dell'attività la base storica è il quarto trimestre
liquidato a febbraio (art. 6, c. 2, L. 405/1990; dal 2027 art. 111 D.Lgs.
10/2026).

Il credito chiesto a rimborso o in compensazione con il modello IVA TR è
registrato dal modulo Dichiarativi (`CreditoTrimestraleIva`, anche di gruppo)
e `Services\CreditoTrimestrale` lo annota sulla liquidazione che chiude il
trimestre (`credito_trimestrale`), anche se è elaborata dopo o rielaborata:
resta nel prospetto ma non si riporta al periodo successivo (istruzioni della
comunicazione, rigo VP8), e il riporto dell'acconto storico ne tiene conto.
Le liquidazioni successive sono rielaborate con i loro dati; ci si ferma alla
prima con la comunicazione inviata, un versamento in una delega trasmessa o
il giroconto stampato in definitiva, con un messaggio: da lì il riporto va
corretto a mano.

## Comunicazione delle liquidazioni periodiche

La comunicazione del trimestre (art. 21-bis D.L. 78/2010) si prepara
dalle liquidazioni elaborate (`Services\Lipe\PreparazioneLipe`): un
modulo per ogni mese dei mensili, uno per il trimestre dei trimestrali,
con i righi:

- VP2 e VP3: imponibili delle operazioni attive e passive del periodo,
  comprese non imponibili, esenti, scissione dei pagamenti, esigibilità
  differita e inversione contabile; per i corrispettivi ventilati
  l'imponibile della ventilazione;
- VP4-VP6: IVA esigibile e detratta della liquidazione;
- VP7 (debito non superiore a 100 euro) e VP8 (credito del periodo
  precedente) dove le specifiche li ammettono (VP7 non a gennaio,
  dicembre, primo e quarto trimestre; VP8 non nel primo periodo, il cui
  credito dell'anno precedente va nel VP9), con un avviso se la
  liquidazione ne riporta uno non ammesso;
- VP9 credito annuale compensato, VP10 IVA già versata con F24 elementi
  identificativi sulle prime cessioni interne di auto di provenienza UE
  (D.L. 262/2006), VP11 crediti d'imposta particolari scomputati dal
  versamento (non quelli compensati nel modello F24), VP12 interessi dei
  trimestrali, VP13 acconto con il metodo (1 storico, 2 previsionale, 3
  analitico);
- VP14: risultato dei righi, compreso il debito non versato perché non
  superiore a 100 euro; un avviso segnala la differenza dal risultato
  della liquidazione (da rielaborare);
- quarto trimestre dei trimestrali: periodo "5", senza VP11, VP12 e VP14
  (l'imposta si versa con la dichiarazione annuale).

Versamenti auto UE e crediti d'imposta non risultano dai registri: si
indicano all'elaborazione della liquidazione, come il credito annuale, e
riducono l'importo da versare (e il versamento in Deleghe); i crediti
d'imposta non superano l'IVA da versare prima del loro scomputo e non si
usano nel quarto trimestre dei trimestrali. Alla prima preparazione si propongono il dichiarante
(rappresentante legale, amministratore unico, presidente del CdA o
liquidatore in carica dai legami dell'Anagrafica, obbligatorio per i
soggetti diversi dalle persone fisiche) e l'intermediario predefinito di
Deleghe con l'impegno "predisposta da chi effettua l'invio"; si
modificano nel frontespizio.

`Services\Lipe\GeneratoreLipe` produce la fornitura IVP18 (specifiche
tecniche del provvedimento del 14 marzo 2024) e la valida sullo schema
ufficiale prima del download: il file va firmato e trasmesso con Entratel
o dal portale Fatture e corrispettivi; il nome proposto è
`IT<codice fiscale>_LI_<progressivo>.xml` (convenzione diffusa, non
verificata su un documento ufficiale). Registrati data e protocollo
dell'invio la comunicazione non si modifica: per una sostitutiva entro il
termine la si riporta in bozza e la si riprepara. Termine: ultimo giorno
del secondo mese successivo al trimestre (30 settembre per il secondo,
fine di febbraio per il quarto), al primo giorno lavorativo se festivo.
Le operazioni straordinarie (casella del rigo VP1 e partita IVA del
soggetto trasformato) non sono gestite.

## Stampe definitive

Il libro giornale (per esercizio, art. 2215 c.c.) e i registri IVA (per
anno solare, art. 39 D.P.R. 633/1972) si stampano in definitiva con le
pagine numerate per anno ("2026/1", "2026/2", ...), senza bollatura né
vidimazione (art. 8 L. 383/2001). Tenuti con sistemi elettronici, i
registri vanno stampati entro tre mesi dal termine della dichiarazione
dei redditi (art. 7, c. 4-ter, D.L. 357/1994) o, aggiornati sul sistema
elettronico, anche solo su richiesta degli organi di controllo (c.
4-quater, esteso a tutti i registri contabili dal D.L. 34/2019): la
stampa definitiva serve a chi stampa o conserva i libri per periodi.

- **Continuità** (`Services\StampaDefinitiva\StampeDefinitive`): ogni
  stampa parte dal giorno dopo la precedente (o dall'inizio dell'esercizio
  o dell'anno) e dalla pagina successiva, fino a un giorno già trascorso
  entro la fine dell'esercizio o dell'anno; si annulla solo l'ultima.
- **Impaginazione** (`Services\StampaDefinitiva\Impaginazione`): righe
  fisse per pagina, ciascuna su una sola linea (testi lunghi troncati),
  così il numero delle pagine non dipende dal browser; il giornale porta
  i riporti di dare e avere da una pagina all'altra e il totale
  progressivo dell'esercizio. Il numero di righe per pagina è registrato
  con la stampa: la ristampa rigenera le stesse pagine dai dati.
- **Blocco**: le registrazioni con data (giornale) o competenza IVA
  (registro) in un periodo stampato non si creano, modificano o eliminano
  più (`SalvataggioRegistrazione`), comprese le integrazioni degli
  acquisti in inversione contabile riportate nel registro delle vendite;
  la rielaborazione di una liquidazione lascia il giroconto stampato con
  un avviso. Annullando l'ultima stampa il periodo torna modificabile.
- L'anteprima mostra le pagine con la filigrana senza registrare nulla.

## Fatture elettroniche

`Services\FatturaPa\ImportazioneFatture` accetta file XML, firmati
CAdES (`.p7m`, in binario o base64) e archivi ZIP che li contengono (si
ignorano i file dei metadati del portale Fatture e corrispettivi). La
busta firmata si apre con un lettore BER minimale
(`Services\FatturaPa\EstrazioneP7m`, RFC 5652), anche con il contenuto in
più parti e a lunghezza indefinita, senza file temporanei né verifica della
firma (la fattura è già passata dal Sistema di Interscambio). Il
documento si legge con `LetturaFatturaPa` (schema 1.2.x, prefissi dello
spazio dei nomi qualsiasi, DOCTYPE rifiutato).

Per ogni documento del lotto la direzione si ricava dalla partita IVA o
dal codice fiscale dell'azienda (cedente: emessa; cessionario: ricevuta;
altrimenti scartata), la controparte si cerca per partita IVA o codice
fiscale ed è creata se manca, l'impronta (SHA-256 dell'XML e posizione
nel lotto) impedisce i doppi caricamenti. Il file è verificato sullo
schema XSD ufficiale (`Services\FatturaPa\VerificaSchemaFattura`,
schema VFPR12 delle specifiche 1.9.1): il Sistema di Interscambio scarta
i file non conformi, quindi un errore indica un file alterato, prodotto
fuori dal SdI o di una versione dello schema più recente; la fattura si
importa comunque, con l'avviso del primo errore. Gli altri avvisi segnalano divisa
estera, esigibilità differita, differenze tra totale e riepiloghi
(ritenute, bolli), integrazioni TD16 che possono duplicare la fattura del
fornitore.

`PropostaDaFattura` prepara la registrazione da confermare nel form della
prima nota: causale dal tipo di documento e dalla direzione, registro
principale, competenza (emesse: data del documento; ricevute: data di
registrazione), esigibilità e righe IVA dai riepiloghi con il codice IVA
per aliquota e natura. Per gli acquisti in inversione contabile (fatture
ricevute con natura N6 e integrazioni TD16-TD19) si propone il codice di
integrazione (`RC`, `UE`, `ES`) con l'aliquota del documento o, se manca,
il 22% da verificare. Per le fatture emesse propone anche l'imposta di
bollo virtuale dei `DatiBollo` (2 euro se l'importo non è indicato).
Salvata la registrazione la fattura risulta registrata; eliminando la
registrazione torna da registrare.

## Ritenute e imposta di bollo

La pagina "Ritenute e bollo" dell'azienda (`RitenuteBolloController`)
riepiloga per anno i due tributi che l'azienda versa con l'F24 oltre
all'IVA e li registra nell'archivio di Deleghe F24 (origine `Contabilita`),
dove si generano le deleghe; i versamenti già in una delega confermata non
si toccano e le differenze sono segnalate.

**Ritenute d'acconto operate** (`Services\RitenuteOperate`): sui compensi
di lavoro autonomo e sulle provvigioni pagati (artt. 25 e 25-bis D.P.R.
600/1973). Sono le righe sui conti con la funzione "Erario c/ritenute da
versare" delle registrazioni con il cliente o fornitore, di norma il
pagamento al netto della ritenuta (vedi "Partite aperte e scadenzario"):
avere meno dare, nel mese della registrazione, cioè del pagamento. Le righe
senza controparte (il versamento in F24, un giroconto) non contano. Il
versamento del mese (riferimento `ritenute-<anno>-<mese>`) è dovuto entro il
16 del mese successivo (art. 18 D.Lgs. 241/1997 e art. 8 D.P.R. 602/1973;
dal 2027 artt. 8 e 32 D.Lgs. 33/2025), con il codice tributo 1040, che dal
2017 comprende anche le provvigioni (risoluzione 13/E/2016), il mese nel
campo della rateazione (`00MM`) e l'anno; i termini festivi e del 16 agosto
slittano come gli altri versamenti. Le ritenute sui redditi di lavoro
dipendente (paghe) e le certificazioni (CU, 770) non sono gestite.

**Imposta di bollo sulle fatture elettroniche** (`Services\ImpostaBolloFatture`):
2 euro sulle fatture con operazioni senza IVA oltre 77,47 euro, assolta in
modo virtuale e versata per trimestre (art. 6, c. 2, D.M. 17 giugno 2014).
L'imposta è quella indicata nelle registrazioni delle vendite (campo
"Imposta di bollo" della prima nota, proposto dalla fattura importata),
raggruppata per trimestre della data del documento. Termini: 31 maggio,
30 settembre (secondo trimestre), 30 novembre e fine febbraio dell'anno
dopo (quarto), con lo slittamento al primo giorno lavorativo; codici
2521-2524 (risoluzione 42/E/2019). Senza interessi e sanzioni l'imposta del
primo trimestre inferiore a 5.000 euro si versa nel termine del secondo, e
quella del primo e del secondo, se insieme inferiore a 5.000 euro, nel
termine del terzo, ciascuna con il codice del suo trimestre (art. 17, c.
1-bis, D.L. 124/2019; dal 2027 art. 152, c. 2, D.Lgs. 123/2025, testo unico
registro e altri tributi indiretti). Si registrano i versamenti dell'anno
intero (riferimento `bollo-fatture-<anno>`): si aggiornano alla fine di
ogni trimestre.

L'importo dovuto è quello che l'Agenzia delle entrate calcola sulle fatture
inviate al Sistema di interscambio e mette a disposizione nel portale
Fatture e corrispettivi, integrando le fatture senza l'annotazione del
bollo (art. 12-novies D.L. 34/2019; dal 2027 art. 152, c. 1, D.Lgs.
123/2025): la pagina elenca da verificare le fatture emesse senza bollo con
operazioni senza IVA soggette (nature N1, N2, N3.4, N3.5, N3.6 e N4) oltre
77,47 euro. Non sono soggette le esportazioni di merci (N3.1) e le cessioni
intracomunitarie (N3.2) (art. 15 della tabella B del D.P.R. 642/1972 e art.
66, c. 5, D.L. 331/1993; dal 2027 art. 19 della tabella dell'allegato 3 del
D.Lgs. 123/2025), San Marino, il regime del margine e l'inversione
contabile. La rilevazione contabile del bollo (costo e addebito al cliente)
resta a cura dell'utente.

## Tabelle di riferimento

`sisma tabelle-contabilita` (o `--sql=FILE` per lo script) aggiorna dalle
sorgenti in `Database/sorgenti`:

- `piani-conti.csv` e `conti.csv`: piani modello della suite; livello e
  conto superiore si ricavano dal codice, le funzioni e le voci CEE si
  controllano (unicità, livello, coerenza con la natura);
- `codici-iva.csv`: codici della suite con la norma per riga; quelli tolti
  restano non attivi;
- `prodotti-agricoli.csv`: prodotti con la percentuale di compensazione e gli
  anni di validità. I decreti fissano le percentuali per numero della tabella A,
  parte I (D.M. 12 maggio 1992, D.M. 23 dicembre 2005, D.M. 26 gennaio 2016): i
  prodotti vengono dalla tabella della CIA Toscana (2021), controllata su quei
  testi. Il pollame escluso dal D.M. 2005 è quello dell'art. 2 del D.P.R. 587/1993,
  compresi quaglie, piccioni, fagiani e pernici; i ratiti sono nella definizione del
  D.Lgs. 199/2014, che lo ha sostituito. Il testo del D.M. 30 dicembre 1997, che fissa
  bovini, pollame e carni di pollame, non è stato trovato: 7%, 7,5% e 8,5% sono
  confermati dal modello 2026 e da Fisco Oggi. Bovini e suini sono al 7% e al 7,3% dal 2023; n. 43 e 45 (legna, legno
  squadrato) al 2% nel 2023, al 6,4% nel 2024 e 2025 (D.M. 6 agosto 2025) e al 2%
  dal 2026, la misura ordinaria, finché un nuovo decreto non la innalza.

I piani delle aziende e i modelli e codici degli studi non sono toccati.

## Cespiti e fine dell'esercizio

**Cespiti** (registro dei beni ammortizzabili, art. 16 D.P.R. 600/1973):
la pagina elenca i beni con il piano dell'esercizio (fondo iniziale,
quota, fondo finale, residuo), segnala le fatture di acquisto
dell'esercizio con righe sulle immobilizzazioni (voci B.I e B.II
dell'attivo) senza cespite e ne propone la creazione, con il fondo e il
conto dell'ammortamento ricavati dal conto dell'immobilizzazione; ha la
stampa del registro. `Services\Ammortamenti` calcola le quote
dall'esercizio dell'entrata in funzione (o dell'acquisto) con il
coefficiente annuo del D.M. 31 dicembre 1988 indicato sul bene, ridotto
alla metà nel primo esercizio (art. 102, c. 2, TUIR; dal 2027 art. 111, c.
8, D.Lgs. 117/2026), i beni fino a 516,46 euro per intero nel primo
esercizio (art. 102, c. 5; art. 111, c. 10), fino al costo dopo il fondo
pregresso indicato alla fine di un esercizio (beni già in uso prima della
contabilità), senza quota nell'esercizio della dismissione e con la
plusvalenza o minusvalenza sul costo residuo. Il bene resta nel registro
anche ammortizzato per intero. La scrittura degli ammortamenti
(origine `Ammortamenti`, ultimo giorno dell'esercizio) addebita le quote
ai conti di ammortamento e le accredita ai fondi.

**Dismissione**: rielaborando l'esercizio della dismissione si registra
anche l'eliminazione del bene alla sua data (`dismissione-<id>`): il fondo
stornato dal conto del bene e il saldo residuo alla plusvalenza o alla
minusvalenza (conti con le funzioni "Plusvalenze" e "Minusvalenze").
La fattura di vendita si registra in prima nota con il conto del bene
come contropartita del prezzo (qualifica "bene ammortizzabile" della riga
IVA, VE40).

**Parte fiscale**: la quota deducibile non supera il coefficiente fiscale
del bene, se diverso da quello civilistico, né gli ammortamenti stanziati;
le quote eccedenti si recuperano negli esercizi successivi, entro il
coefficiente. Sul deducibile si applicano il costo fiscale massimo e la
parte deducibile: per le autovetture 18.075,99 euro e 20% (art. 164 TUIR;
dal 2027 art. 168 D.Lgs. 117/2026), proposti dal conto "Autovetture"; per
la telefonia 80% (art. 102, c. 9; dal 2027 art. 111, c. 14). Le differenze
sono variazioni del quadro RF (RF18 per i beni con il costo massimo, RF31
codice 99 in aumento, RF55 codice 99 per il recupero) e riducono i costi
per cassa dei quadri RG ed RE (`ComponentiReddito`); non vanno indicate
anche sul conto dell'ammortamento.

**Stampa definitiva** del registro dei beni ammortizzabili tra le stampe
definitive dell'esercizio (art. 16 D.P.R. 600/1973, compilato entro il
termine della dichiarazione; dal 2027 art. 22 D.Lgs. 141/2026), per
l'intero esercizio e con le pagine numerate: dopo la stampa gli
ammortamenti dell'esercizio non si rielaborano.

**Chiusura dell'esercizio** (`ChiusuraController`), nell'ordine:

1. ammortamenti (pagina dei cespiti);
2. ratei e risconti (`Services\RateiRisconti`, art. 2424-bis, c. 6, c.c.):
   per ogni costo o ricavo a cavallo della fine dell'esercizio si indica
   l'importo dell'intero periodo; se è già registrato nell'esercizio la
   parte successiva a giorni è un risconto (attivo per i costi, passivo
   per i ricavi), se si registra nell'esercizio successivo la parte
   maturata è un rateo (passivo per i costi, attivo per i ricavi). La
   scrittura di assestamento è all'ultimo giorno, lo storno al primo
   giorno dell'esercizio successivo (origine `Assestamento`);
3. epilogo, chiusura e riapertura (`Services\ChiusuraEsercizio`): i saldi
   dei conti economici al "Conto economico" e il risultato all'utile o
   alla perdita dell'esercizio, i saldi dei patrimoniali allo "Stato
   patrimoniale di chiusura" (origine `Chiusura`) e la riapertura dallo
   "Stato patrimoniale di apertura" al primo giorno dell'esercizio
   successivo (origine `Riapertura`), con il risultato da destinare;
4. destinazione del risultato (`Services\DestinazioneRisultato`), nell'esercizio
   successivo alla data della delibera (proposta a quattro mesi dalla
   chiusura, art. 2364, c. 2, c.c.): per le società di capitali il 5%
   dell'utile alla riserva legale finché non raggiunge un quinto del
   capitale sociale (art. 2430 c.c.; senza capitale in contabilità il 5%) e
   il resto alla riserva straordinaria, conti e importi modificabili (es.
   utili a nuovo o ai soci su un conto di debito); per le imprese
   individuali e le società di persone al capitale netto; la perdita a
   nuovo o al capitale netto (origine `Destinazione`).

I conti si trovano per funzione (ratei e risconti attivi e passivi, utile
e perdita, stato patrimoniale di apertura e di chiusura, conto economico),
assegnata nel piano standard e, per le aziende esistenti, da
`aggiornamento-0.11.sql`. Le scritture si rigenerano finché l'esercizio è
aperto e il giornale non è stampato in definitiva alle loro date: i
controlli precedono la scrittura, così non si registra solo una parte.
Bilancio di verifica e componenti del reddito (quadri RF, RG, RE e LM)
escludono epilogo e chiusura; il saldo dei clienti e fornitori dello
scadenzario esclude anche la riapertura. Le righe sui sottoconti dei
clienti e fornitori non hanno la controparte: le partite aperte restano
quelle dello scadenzario. L'IVA sospesa per cassa resta sui conti
dell'IVA su vendite e acquisti e si riapre con loro. L'esercizio si chiude
poi dal riepilogo dell'azienda.

## Scadenzario dello studio

Il modulo non espone scadenze proprie: i versamenti delle liquidazioni,
dell'acconto, delle ritenute e del bollo compaiono nello scadenzario da
Deleghe F24, le liquidazioni
come adempimenti del calendario fiscale di GestioneStudio. La scrivania
mostra le aziende in contabilità, le fatture da registrare e le
liquidazioni dell'anno.

## Multi-studio

Ogni dato è legato allo studio direttamente o tramite l'azienda: i
controller verificano l'azienda con `SoggettoPermission`, i modelli e i
codici dello studio con lo studio dell'utente; i piani e i codici della
suite sono in sola lettura per tutti.

## Testo unico IVA dal 2027

Dal 1° gennaio 2027 le disposizioni del D.P.R. 633/1972 e di molte leggi
speciali sull'IVA sono sostituite dal Testo unico IVA (D.Lgs. 19 gennaio
2026, n. 10, art. 171), e la comunicazione delle liquidazioni periodiche
dal testo unico adempimenti e accertamento (D.Lgs. 5 agosto 2026, n.
141, art. 51, che si applica dalla stessa data per l'art. 368). Il testo
unico IVA è compilativo: le regole che il modulo applica non cambiano,
cambia la numerazione. I regolamenti sulle liquidazioni periodiche
(D.P.R. 100/1998) e sui trimestrali (D.P.R. 542/1999) restano in vigore e
sono richiamati dai testi unici.

Le norme si citano secondo la data dell'operazione o del periodo
(`HelperClasses\RiferimentiIva`): registri e stampe di un periodo del
2026 riportano il D.P.R. 633/1972, quelli del 2027 il testo unico; gli
aiuti dell'interfaccia citano entrambe. In particolare:

| Contenuto | Fino al 2026 | Dal 2027 |
|---|---|---|
| registri delle fatture, dei corrispettivi e degli acquisti | artt. 23, 24 e 25 D.P.R. 633/1972 | artt. 86, 87 e 88 D.Lgs. 10/2026 |
| tenuta e numerazione dei registri | art. 39 | art. 95 |
| esigibilità differita | art. 6, c. 5 | art. 24, c. 5 |
| scissione dei pagamenti | art. 17-ter | art. 65 |
| ventilazione dei corrispettivi | art. 24, c. 3 | art. 87, c. 3 |
| scorporo dei corrispettivi | art. 27, c. 4 | art. 109, c. 2 |
| attività separate | art. 36 | art. 63 |
| IVA di gruppo (liquidazione) | art. 73, c. 3 | art. 91, c. 3 |
| pro-rata di detrazione | art. 19, c. 5, e art. 19-bis | art. 56, c. 5, e art. 57 |
| regime agricolo, esonerati | art. 34, c. 6 | art. 133, c. 5 |
| trimestrali speciali | art. 74, c. 4 | art. 139, c. 4 |
| acconto IVA | art. 6, c. 2, L. 405/1990 | art. 111 D.Lgs. 10/2026 |
| IVA per cassa | art. 32-bis D.L. 83/2012 | art. 132 D.Lgs. 10/2026 |
| comunicazione delle liquidazioni periodiche | art. 21-bis D.L. 78/2010 | art. 51 D.Lgs. 141/2026 |

Le corrispondenze sono quelle indicate sotto la rubrica di ciascun
articolo dei testi unici (G.U. n. 24 del 30 gennaio 2026, S.O. n. 4;
G.U. n. 181 del 6 agosto 2026, S.O. n. 28). Nel Testo unico le lettere
dell'inversione contabile interna sono rinumerate (art. 64, c. 6:
subappalto edile a, fabbricati b, pulizia e completamento c, logistica d,
cellulari e, prodotti elettronici f, quote di emissione g e h, gas ed
energia i) e le parti della tabella A scalano di una (4% parte II, 5%
parte III, 10% parte IV).

I **codici IVA** hanno la norma fino al 2026 e quella del testo unico
(colonna `norma_testo_unico`); l'elenco mostra quella in vigore. Un
codice può valere solo per le operazioni fino a una data (`valido_fino`):
l'inversione contabile per cellulari (N6.5), prodotti elettronici (N6.6),
quote di emissione, gas ed energia (N6.8) si applica alle operazioni
effettuate fino al 31 dicembre 2026 (art. 17, c. 8, D.P.R. 633/1972;
art. 64, c. 8, D.Lgs. 10/2026), e le registrazioni con documento (o
competenza) successivo la rifiutano. Una proroga si recepisce cambiando
la data in `Database/sorgenti/codici-iva.csv` ed eseguendo `sisma
tabelle-contabilita`. La causale dell'autofattura per gli acquisti dagli
agricoltori esonerati cita la norma della data del documento.

I commenti del codice e le descrizioni delle nature e dei tipi di
documento della fattura elettronica (testo delle specifiche tecniche)
riportano ancora il D.P.R. 633/1972.

## Fonti

- D.M. 24 febbraio 1973 (ventilazione dei corrispettivi, artt. 1, 3-5);
- D.P.R. 633/1972 (artt. 17, 17-ter, 19-bis1, 21, 23-27), D.P.R. 695/1996
  (art. 6, c. 4), D.Lgs. 10/2026 (Testo unico IVA, dal 2027: testo e
  rubriche con le fonti previgenti nella G.U. n. 24 del 30 gennaio 2026,
  S.O. n. 4), D.Lgs. 141/2026 (testo unico adempimenti e accertamento,
  art. 51, dal 2027), D.L. 331/1993
  (artt. 38, 41, 46, 47), D.P.R. 100/1998 (art. 1), D.P.R. 542/1999 (art.
  7), L. 405/1990 (art. 6), D.Lgs. 1/2024 (art. 9), D.L. 16/2012 (art.
  3-quater): testi su Normattiva;
- specifiche tecniche della fattura elettronica (allegato A al
  provvedimento AdE 30 aprile 2018 e successive versioni, 1.9.1): nature,
  tipi di documento, esigibilità, schema VFPR12 v1.2.3;
- art. 36 (attività separate) e art. 73, c. 3 (IVA di gruppo) D.P.R.
  633/1972, D.M. 13 dicembre 1979 modificato dal D.M. 13 febbraio 2017;
  istruzioni del modello IVA 2026 (paragrafi 3.2 e 3.4, appendice
  "Contabilità separate");
- art. 21-bis D.L. 78/2010; specifiche tecniche e istruzioni della
  comunicazione delle liquidazioni periodiche IVA (provvedimento AdE 14
  marzo 2024, fornitura IVP18, schemi XSD);
- artt. 2214-2220 c.c., art. 39 D.P.R. 633/1972, art. 8 L. 383/2001,
  art. 7 D.L. 357/1994 (stampe dei libri);
- artt. 2424 e 2425 c.c. nel testo modificato dal D.Lgs. 139/2015;
- RFC 5652 (Cryptographic Message Syntax) per la busta CAdES;
- artt. 25 e 25-bis D.P.R. 600/1973, art. 18 D.Lgs. 241/1997, art. 8
  D.P.R. 602/1973, D.Lgs. 33/2025 (artt. 8 e 32, dal 2027), risoluzione
  AdE 13/E/2016 (ritenute);
- art. 6, c. 2, D.M. 17 giugno 2014, art. 17, c. 1-bis, D.L. 124/2019,
  art. 12-novies D.L. 34/2019, D.P.R. 642/1972 (tabella B, art. 15), art.
  66, c. 5, D.L. 331/1993, D.Lgs. 123/2025 (artt. 152 e allegato 3, dal
  2027: testo nella G.U. n. 186 del 12 agosto 2025, S.O. n. 29),
  risoluzione AdE 42/E/2019 (imposta di bollo sulle fatture elettroniche).

## Da completare

- conti dell'IVA in sospensione per le fatture a esigibilità differita e
  per cassa (oggi l'imposta sospesa resta sui conti dell'IVA su vendite e
  acquisti fino al giroconto della liquidazione); verifica del volume
  d'affari per l'IVA per cassa;
- archivio delle dichiarazioni d'intento emesse (protocollo, fornitore,
  importo) e verifica degli acquisti per fornitore;
- forfetari: individuazione dell'operazione che supera i 100.000 euro e
  verifica degli altri requisiti di accesso;
- modulo Bilancio (schemi degli artt. 2424 e 2425 c.c. dai saldi con le
  voci CEE dei conti, nota integrativa, deposito);
- trimestrali per natura dell'attività nelle attività separate e nella
  liquidazione IVA di gruppo;
- libro degli inventari (con il bilancio);
- importazione nella maschera mensile dei corrispettivi telematici
  trasmessi (portale Fatture e corrispettivi) e scarico automatico delle
  fatture;
- commenti del codice con le norme del Testo unico IVA (le citazioni
  visibili sono già adeguate, vedi sopra);
- proposta della ritenuta d'acconto nel pagamento dai `DatiRitenuta` della
  fattura ricevuta; ritenute su lavoro dipendente, certificazione unica e
  modello 770 dei sostituti d'imposta;
- importazione del bollo calcolato dall'Agenzia delle entrate e scritture
  contabili del bollo.
