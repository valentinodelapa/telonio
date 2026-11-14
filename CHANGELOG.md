# Changelog

All notable changes to this project will be documented in this file.

## [0.27.0-alpha] - 2026-11-14 - IVA per Cassa, Plafond, Forfetari e Chiusura dell'Esercizio

Versioni dei moduli: `Anagrafica` 0.14.0, `Contabilita` 0.11.0 (richiede `Anagrafica` 0.14.0), `Dichiarativi` 0.8.0 (richiede `Anagrafica` 0.14.0 e `Contabilita` 0.11.0); gli altri invariati.

**Aggiornamento**: `aggiorna.sh` (cioè `sisma aggiorna-database`) esegue `Anagrafica/Database/aggiornamento-0.14.sql` (colonna `dati_fiscali.iva_per_cassa`) e `Contabilita/Database/aggiornamento-0.11.sql` (colonna `esercizio_contabile.plafond_mensile`, tabelle `cespite` e `rateo_risconto`, funzioni dei conti di ratei, risconti, risultato d'esercizio, stato patrimoniale di apertura e chiusura, conto economico, plusvalenze e minusvalenze, capitale e riserve nei piani delle aziende copiati dal piano standard; `Contabilita/Database/dati-riferimento.sql` le assegna nel piano standard). Le registrazioni esistenti a esigibilità differita, finora escluse dalle liquidazioni, entrano nelle liquidazioni elaborate da qui in avanti con l'imposta maturata negli incassi abbinati: rielaborare le liquidazioni dei periodi interessati. Nessun passo manuale.

### ✨ Nuove Funzionalità

#### `Anagrafica` 0.14.0

- **opzione per l'IVA per cassa** (art. 32-bis D.L. 83/2012; dal 2027 art. 132 D.Lgs. 10/2026) nei dati fiscali: solo con un regime che prevede le liquidazioni IVA e senza regimi speciali; è mostrata nella scheda del soggetto.

#### `Contabilita` 0.11.0 — esigibilità differita, IVA per cassa, plafond, forfetari, cespiti e chiusura dell'esercizio

- **IVA a esigibilità differita nelle liquidazioni**: l'imposta delle fatture emesse "differite" diventa esigibile nel periodo dell'incasso, in proporzione a quanto incassato, leggendo gli abbinamenti dello scadenzario. Prima queste fatture erano escluse dalla liquidazione con un avviso.
- **IVA per cassa**: nuova esigibilità "per cassa" delle registrazioni, proposta per le fatture emesse e ricevute delle aziende che hanno optato (anche da quelle elettroniche importate). L'imposta delle vendite diventa esigibile e quella degli acquisti detraibile al pagamento e comunque decorso un anno dall'operazione (D.M. 11 ottobre 2012). I controlli escludono note di credito, corrispettivi, importazioni e operazioni in inversione contabile. Il prospetto della liquidazione elenca le fatture con l'imposta maturata nel periodo.
- **plafond degli esportatori abituali**: nella pagina delle liquidazioni c'è il prospetto mese per mese del plafond (disponibile, utilizzato, residuo), con il metodo solare o mensile scelto per anno sull'esercizio contabile e l'avviso sullo status di esportatore abituale. Le liquidazioni avvisano del primo superamento del plafond.
- **regime forfetario**: per i forfetari la pagina delle liquidazioni mostra i ricavi e compensi percepiti nell'anno, con l'avviso oltre 85.000 e 100.000 euro. Mostra anche l'IVA degli acquisti in inversione contabile e intracomunitari, da registrare in Deleghe F24: trimestrale per le operazioni dal 1° ottobre 2025 (art. 1, c. 58, lett. e-bis, L. 190/2014), mensile prima. Il codice tributo trimestrale proposto, quello dell'ultimo mese del trimestre, è segnalato da verificare.
- **cespiti e ammortamenti**: nuova pagina "Cespiti" con il registro dei beni ammortizzabili e la sua stampa, anche definitiva con le pagine numerate tra le stampe definitive dell'esercizio. Gli acquisti di immobilizzazioni senza cespite sono segnalati e si trasformano in cespiti con i conti proposti. Il piano di ammortamento usa il coefficiente del bene, la metà nel primo esercizio, la deduzione integrale dei beni fino a 516,46 euro, il fondo pregresso e la dismissione. La scrittura degli ammortamenti è registrata a fine esercizio, con l'eliminazione dei beni dismessi (storno del fondo e plusvalenza o minusvalenza).
- **parte fiscale degli ammortamenti**: coefficiente fiscale diverso da quello civilistico (eccedenze riprese e poi recuperate), parte deducibile e costo fiscale massimo (autovetture al 20% fino a 18.075,99 euro, telefonia all'80%). Le differenze vanno nelle variazioni del quadro RF (RF18, RF31 e RF55 codice 99) e riducono i costi per cassa dei quadri RG ed RE.
- **chiusura dell'esercizio**: nuova pagina "Chiusura" con ratei e risconti (quote a giorni, scrittura di assestamento e storno nell'esercizio successivo), epilogo dei conti economici con l'utile o la perdita, chiusura dei conti patrimoniali e riapertura nell'esercizio successivo. Bilancio di verifica e componenti del reddito escludono le scritture di chiusura. Le scritture si rigenerano finché l'esercizio è aperto e il giornale non è stampato in definitiva.
- **destinazione del risultato**: scrittura nell'esercizio successivo alla data della delibera, con la proposta del 5% alla riserva legale fino a un quinto del capitale e del resto alla riserva straordinaria per le società di capitali, del capitale netto per le altre imprese; conti e importi modificabili.

#### `Dichiarativi` 0.8.0

- dati demo: fine dell'esercizio 2025 dell'azienda con l'IVA per cassa (computer e autovettura tra i cespiti, risconto di una polizza, chiusura e riapertura dei conti, destinazione dell'utile).
- **metodo mensile del plafond** proposto nella casella 3 del VC14 dalla scelta fatta sull'esercizio contabile; il calcolo del quadro VC usa il servizio della Contabilità.
- **dichiarazione IVA con esigibilità differita e IVA per cassa**: nelle sezioni del VE e nei righi VF1-VF13 la quota divenuta esigibile o detraibile nell'anno, il resto nei righi VE37 e VF23 (campo 2 per l'IVA per cassa), i documenti degli anni precedenti nei righi VE39 e VF24; opzione e revoca dell'IVA per cassa nel rigo VO15. Nuova azienda demo con l'IVA per cassa.

### 🐛 Correzioni

#### `Dichiarativi` 0.8.0

- **esigibilità differita nel VE37**: l'imponibile delle fatture a esigibilità differita finiva per intero nel rigo VE37, anche per la parte incassata nell'anno, che va nella sezione 2 del quadro VE.

## [0.26.0-alpha] - 2026-11-13 - Ritenute d'Acconto e Imposta di Bollo

Versioni dei moduli: `Contabilita` 0.10.0, `Dichiarativi` 0.7.1 (richiede `Contabilita` 0.10.0), `GestioneStudio` 0.2.1; gli altri invariati.

**Aggiornamento**: `aggiorna.sh` (cioè `sisma aggiorna-database`) esegue `Contabilita/Database/aggiornamento-0.10.sql` (colonna `registrazione_contabile.imposta_bollo`) e il `dati-riferimento.sql` di `GestioneStudio` (nuova fonte dell'adempimento del bollo sulle fatture elettroniche). Nessun passo manuale.

### ✨ Nuove Funzionalità

#### `Contabilita` 0.10.0 — ritenute d'acconto e imposta di bollo

- **ritenute d'acconto operate**: nuova pagina "Ritenute e bollo" dell'azienda. Riepiloga per mese le ritenute dei pagamenti a professionisti e agenti (righe su "Erario c/ritenute da versare" delle registrazioni con il fornitore) e registra in Deleghe F24 il versamento con il codice 1040 entro il 16 del mese successivo (mese di riferimento e anno nell'F24).
- **imposta di bollo sulle fatture elettroniche**: nuovo campo "Imposta di bollo" nelle registrazioni delle vendite, proposto dai dati del bollo della fattura elettronica emessa importata. La pagina riepiloga il bollo per trimestre e registra in Deleghe F24 i versamenti con i codici 2521-2524 nei termini del D.M. 17 giugno 2014, con il differimento senza interessi sotto i 5.000 euro (art. 17, c. 1-bis, D.L. 124/2019; dal 2027 art. 152, c. 2, D.Lgs. 123/2025). Segnala le fatture emesse senza bollo con operazioni senza IVA soggette oltre 77,47 euro.

#### `Dichiarativi` 0.7.1

- dati demo: bollo virtuale sulle fatture esenti del poliambulatorio, con i versamenti 2025 differiti al terzo trimestre.

#### `GestioneStudio` 0.2.1

- **fonte dell'imposta di bollo sulle fatture elettroniche**: l'adempimento cita anche il differimento sotto i 5.000 euro (art. 17, c. 1-bis, D.L. 124/2019) e, dal 2027, l'art. 152, c. 2, del testo unico registro e altri tributi indiretti (D.Lgs. 123/2025).

## [0.25.0-alpha] - 2026-11-13 - Testo Unico IVA, Rate delle Partite e Trimestrali per Natura

Versioni dei moduli: `Anagrafica` 0.13.0, `Contabilita` 0.9.0, `Dichiarativi` 0.7.0, `GestioneStudio` 0.2.0, `Deleghe` 0.7.2; gli altri invariati.

**Aggiornamento**: `aggiorna.sh` (cioè `sisma aggiorna-database`) esegue `Contabilita/Database/aggiornamento-0.9.sql` (colonne `codice_iva.norma_testo_unico`, `codice_iva.valido_fino` e dei termini di pagamento delle controparti, tabella `scadenza_partita`, colonna `liquidazione_iva.trimestrale_speciale`), `Anagrafica/Database/aggiornamento-0.13.sql` (colonna `dati_fiscali.trimestrale_speciale`) e `GestioneStudio/Database/aggiornamento-0.2.sql` (colonne `adempimento.nuova_fonte` e `adempimento.nuova_fonte_dal`). I rispettivi `dati-riferimento.sql` le valorizzano per i codici IVA della suite e per il calendario fiscale comune. Nessun passo manuale.

### ✨ Nuove Funzionalità

#### `Anagrafica` 0.13.0

- **trimestrali per natura dell'attività** (art. 74, c. 4, D.P.R. 633/1972; dal 2027 art. 139, c. 4, D.Lgs. 10/2026): nuova casella dei dati fiscali per autotrasportatori, distributori di carburante e gli altri soggetti ammessi alle liquidazioni trimestrali senza interessi. Richiede la periodicità trimestrale e la partita IVA; è mostrata nella scheda del soggetto.

#### `Contabilita` 0.9.0 — Testo unico IVA dal 2027, scadenze e rate delle partite, trimestrali per natura

- **norme citate secondo la data**: dal 1° gennaio 2027 il D.P.R. 633/1972 e diverse leggi speciali sono sostituiti dal Testo unico IVA (D.Lgs. 10/2026), e la comunicazione delle liquidazioni periodiche passa all'art. 51 del testo unico adempimenti e accertamento (D.Lgs. 141/2026). Registri IVA, stampe e intestazioni delle stampe definitive, esigibilità della registrazione e causale dell'autofattura per gli agricoltori esonerati citano la norma in vigore alla data del periodo o del documento. Gli aiuti dell'interfaccia citano entrambe. Le corrispondenze sono prese dalle rubriche degli articoli dei testi unici in Gazzetta Ufficiale, e la documentazione del modulo ne riporta la tabella;
- **scadenze e rate delle partite**: i documenti dei clienti e dei fornitori hanno un piano di rate (data, importo, modalità di pagamento) modificabile nella prima nota. Le rate sono proposte dai dati di pagamento della fattura elettronica, se ci sono (riportati all'importo della partita), altrimenti dai nuovi termini di pagamento abituali della controparte (giorni dalla data del documento, a fine mese, modalità), altrimenti alla data del documento. Gli incassi e i pagamenti chiudono le rate in ordine di scadenza. Lo scadenzario mostra la prossima scadenza e il residuo di ogni rata, la scheda della fattura elettronica i dati di pagamento. Le partite già registrate valgono come rata unica e non vanno migrate;
- **codici IVA con la norma del Testo unico** (nuova colonna, mostrata dal 2027 nell'elenco e modificabile nei codici dello studio) **e data di fine validità**: l'inversione contabile per cellulari (N6.5), prodotti elettronici (N6.6), quote di emissione, gas ed energia (N6.8) vale per le operazioni fino al 31 dicembre 2026 (art. 17, c. 8, D.P.R. 633/1972; art. 64, c. 8, D.Lgs. 10/2026). Le registrazioni con data successiva rifiutano quei codici. Un'eventuale proroga si recepisce aggiornando la data nella sorgente dei codici ed eseguendo `sisma tabelle-contabilita`.

- **liquidazioni dei trimestrali per natura dell'attività**: senza gli interessi dell'1%; il quarto trimestre si liquida e si versa entro il 16 febbraio dell'anno successivo con il codice 6034 (non con il saldo annuale), si comunica nella LIPE con il trimestre "4" e il rigo VP14 ed è la base dell'acconto storico. Non riguarda le attività separate e la liquidazione IVA di gruppo, che seguono le regole dei trimestrali per opzione. Richiede `Anagrafica` 0.13.0.

#### `Dichiarativi` 0.7.0

- **dichiarazione IVA dei trimestrali per natura dell'attività**: il quarto trimestre è una liquidazione periodica (rigo VL30 e, se la comunicazione non è stata inviata, rigo VH16) e non ci sono gli interessi annuali del rigo VL36. Nuova azienda demo, un autotrasportatore con il 2025 completo. Richiede `Anagrafica` 0.13.0 e `Contabilita` 0.9.0.

#### `GestioneStudio` 0.2.0

- **fonte normativa degli adempimenti che cambia da una data**: nuova fonte e data di decorrenza nel calendario fiscale comune e negli adempimenti dello studio. Le scadenze da quella data citano la nuova fonte. Dal 1° gennaio 2027 le nuove fonti sono queste, compresi i termini 2027 di periodi del 2026 (es. la LIPE del quarto trimestre):
  - acconto IVA: art. 111 del Testo unico IVA;
  - comunicazione delle liquidazioni periodiche: art. 51 del testo unico adempimenti e accertamento;
  - versamento delle ritenute e dei contributi IVS: artt. 8 e 32 del testo unico versamenti e riscossione (D.Lgs. 33/2025);
  - tassa sui libri sociali: art. 16 della tariffa del testo unico tributi erariali minori (D.Lgs. 174/2024).

  La decorrenza di questi due ultimi testi unici è stata rinviata al 2027 dal D.L. 200/2025. Restano in vigore i regolamenti su liquidazioni, dichiarazioni, saldo e acconti (D.P.R. 100/1998, 542/1999, 322/1998 e 435/2001).
- **IVA trimestrale per natura dell'attività**: nuovo adempimento del calendario fiscale (16 maggio, 16 agosto, 16 novembre e 16 febbraio dell'anno successivo), proposto ai clienti con la nuova casella dei dati fiscali; quello dei trimestrali per opzione non è più proposto a loro. Richiede `Anagrafica` 0.13.0.

#### `Deleghe` 0.7.2

- **norme dei testi unici dal 2027**: rateazione e ravvedimento non cambiano. Il messaggio di rateazione non ammessa cita la norma della scadenza del versamento: art. 20 D.Lgs. 241/1997, oppure dal 2027 art. 10 D.Lgs. 33/2025. Gli aiuti su rateazione e ravvedimento citano anche le norme dal 2027: art. 10 D.Lgs. 33/2025 e art. 14 D.Lgs. 173/2024, con le lettere del comma 1 rinumerate.

### 🐛 Correzioni

- **norme dei codici IVA troncate**: nella sorgente `codici-iva.csv` le norme con un punto e virgola non erano racchiuse tra virgolette e il resto della norma andava perso (es. N2.2, N3.3, N3.5, N7, VEN). Ora sono tra virgolette e vengono lette per intero.

## [0.24.0-alpha] - 2026-11-11 - Migrazioni dei Dati nell'Aggiornamento

Versioni dei moduli: `Telonio` 0.8.0, `Contabilita` 0.8.1 (richiede `Telonio` 0.8.0); gli altri invariati.

**Aggiornamento**: `aggiorna.sh` (cioè `sisma aggiorna-database`) esegue `Contabilita/Database/aggiornamento-0.8.1.php`, che crea le partite aperte delle registrazioni che non le hanno (abbinamenti dalla più vecchia, da verificare nello scadenzario): chi ha già eseguito a mano `sisma partite-aperte` dopo la 0.23.0-alpha non ha effetti, le registrazioni con partite non sono toccate. Nessun passo manuale.

### ✨ Nuove Funzionalità

#### `Telonio` 0.8.0

- **migrazioni dei dati in PHP** nell'aggiornamento del database: `sisma aggiorna-database` (e quindi `aggiorna.sh`) esegue gli script `aggiornamento-<versione>.php` dei moduli installati dopo gli script SQL di tutti i moduli e li registra come gli altri; sulle installazioni nuove si registrano soltanto.

### 🐛 Correzioni

#### `Contabilita` 0.8.1

- **partite aperte delle registrazioni già presenti**: con la 0.23.0-alpha andavano create a mano con `sisma partite-aperte` dopo l'aggiornamento; ora le crea l'aggiornamento del database (`aggiornamento-0.8.1.php`), e il comando resta per ripeterlo.

## [0.23.0-alpha] - 2026-11-11 - Partite Aperte e Altri Quadri del Modello Redditi

Versioni dei moduli: `Anagrafica` 0.12.0, `Contabilita` 0.8.0, `Dichiarativi` 0.6.0 (richiede `Anagrafica` 0.12.0 e `Contabilita` 0.8.0), `Deleghe` 0.7.1; gli altri invariati.

**Aggiornamento**: `sisma aggiorna-database` esegue `Anagrafica/Database/aggiornamento-0.12.sql` (tabelle `intervento_edilizio` e `canone_non_percepito`) e `Contabilita/Database/aggiornamento-0.8.sql` (tabelle `partita` e `abbinamento`, funzioni dei conti delle ritenute d'acconto) e `Dichiarativi/Database/aggiornamento-0.6.sql` (tabelle `attivita_estera`, `cessione_finanziaria` e `certificazione_autonomo`), idempotenti; poi `sisma partite-aperte` crea le partite delle registrazioni già presenti (abbinamenti dalla più vecchia, da verificare nello scadenzario) e `sisma tabelle-contabilita` aggiorna le funzioni del piano dei conti modello. `Deleghe/Database/dati-riferimento.sql` (eseguito anch'esso da `sisma aggiorna-database`) corregge le descrizioni dei codici tributo e aggiunge i codici dell'archivio dell'Agenzia del 03/11/2026.

### ✨ Nuove Funzionalità

#### `Anagrafica` 0.12.0 — archivio degli interventi edilizi

- **interventi edilizi** nella scheda del fabbricato: per il contribuente che fruisce della detrazione, i dati del rigo del quadro RP nell'anno della spesa (recupero edilizio, sismabonus, bonus verde, facciate e superbonus; colonnine di ricarica; bonus mobili; risparmio energetico), la spesa, la percentuale e il numero delle rate;
- **canoni non percepiti** sul contratto di locazione, con la data della convalida di sfratto, per il credito del rigo CR8 del modello Redditi PF;
- **tabelle delle specifiche tecniche** del modello Redditi PF 2026 (`TabellaDetrazioniEdilizie`): codici ammessi per anno, percentuale di detrazione, rateazione (anche le 10 rate dal 2023 del superbonus 2022 e l'opzione 2023), numero della rata nell'anno e limiti di spesa; percentuale e rate proposte se non indicate, combinazioni non ammesse segnalate dal form.

#### `Dichiarativi` 0.6.0 — bonus edilizi, perdite e variazioni nel modello Redditi PF

- **quadro RP, sezioni III e IV** proposte dall'archivio degli interventi: righi da RP41 a RP47 con il numero della rata e il numero d'ordine dell'immobile, RP51 e RP52 con i dati catastali o la casella "Condominio", RP53 con gli estremi del contratto per il conduttore, RP56, RP57 e da RP61 a RP64, nei righi non compilati a mano e nei moduli successivi;
- **totali RP48, RP49, RP60, RP65 e RP66** per percentuale dai righi di tutti i moduli, anche inseriti a mano, e **righi RN14, RN15 e RN16**;
- **riordino delle detrazioni** oltre 75.000 euro esteso a tutte le entità della tabella di supporto delle specifiche: le rate delle spese dell'anno dei bonus edilizi, delle colonnine, dei mobili e del risparmio energetico sono imputate al limite prima degli oneri del rigo RN13, nell'ordine delle percentuali; le spese dei righi RP73 e RP83 (codice 2) consumano il limite con un avviso per i righi RN12 e RN17.
- **perdite**: perdite di lavoro autonomo del 2006 e 2007 dal rigo RS10 dell'anno prima nel rigo RE24 (con il residuo nel rigo RS10), reddito complessivo non negativo con le perdite di lavoro autonomo (avviso per l'eccedenza); nel regime forfetario le perdite d'impresa dei periodi prima dell'ingresso dai righi RS12 e RS13 dell'anno prima nel rigo LM37 (misura limitata entro l'80 per cento del reddito), con un avviso per le perdite del regime di vantaggio e dei minimi da indicare a mano;
- **rateazione dei versamenti Redditi**: saldo e primo acconto in unica soluzione al 30 giugno, a rate mensili fino a dicembre o differiti al 30 luglio con la maggiorazione dello 0,40 per cento (anche a rate), con il piano di rateazione e gli interessi in Deleghe;
- **impresa familiare nel regime forfetario**: quote di reddito dei collaboratori (righi RS6 e RS7, col. 3) sul reddito del rigo LM36, senza ridurre l'imposta sostitutiva dovuta dal titolare (art. 1, comma 64, L. 190/2014; circolare 10/E del 2016);
- **quadro RW** dall'archivio delle attività estere della scheda del contribuente: righi da RW1 a RW5 su più moduli con IVAFE (anche in misura fissa sui conti correnti con la soglia di giacenza per intermediario), IVIE (anche sull'abitazione principale di lusso con la detrazione), imposta sulle cripto-attività, credito per l'imposta patrimoniale estera e solo monitoraggio; righi RW6, RW7 e RW8 con eccedenze dell'anno prima e acconti versati, righi RX25, RX26 e RX27; saldi e acconti in Deleghe (4041, 4044, 4045, 4043, 4047, 4048, 1727, 1728, 1729); elenco ufficiale degli Stati esteri del modello con i Paesi a fiscalità privilegiata;
- **quadro RH** dalle partecipazioni in società di persone della suite (legami di socio): reddito dalla contabilità della società secondo il regime e ritenute dagli abbinamenti delle partite, per la quota; totali dei righi da RH7 a RH22 anche con i righi a mano; redditi e ritenute nel quadro RN;
- **quadro RM, rigo RM31**, dai proventi delle attività estere incassati senza intermediari: tipo di reddito, imposta sostitutiva del 26 per cento, rigo RX7 e versamento 1242;
- **quadro RL** dall'archivio delle Certificazioni Uniche del lavoro autonomo e dei redditi diversi: righi RL14, RL15 e RL16 con le spese inerenti, compensi dei dilettanti (RL21-RL24), lavoro autonomo non abituale (RL25-RL27) con le deduzioni forfetarie, totali con i righi a mano, redditi nel quadro RN con le ritenute e la detrazione per gli altri redditi;
- **quadro RT**, sezioni II e V, dall'archivio delle cessioni di titoli, partecipazioni non qualificate e cripto-attività: totali, righi del costo rideterminato, franchigia di 2.000 euro sulle cripto-attività detenute prima del 2025, minusvalenze e eccedenze d'imposta dalla dichiarazione dell'anno prima, imposta del 26 per cento, sezione VII, righi RX20 e RX21 e versamenti 1100 e 1715;
- **quadro CE** (credito per le imposte estere, sezione I) sui righi proposti dalle attività estere con l'opzione per la tassazione ordinaria dei proventi o inseriti a mano: quote di imposta lorda, credito per anno entro l'imposta netta, righi CE23 e CE26 e rigo RN29;
- **quadro CR**: residui della dichiarazione dell'anno prima dal rigo RN47, credito per i canoni non percepiti (rigo CR8) dai contratti dell'Anagrafica, righi RN24, RN25 e residui del rigo RN47 nell'ordine delle specifiche; scaglioni IRPEF per anno per la riliquidazione;
- **formato del codice del rigo CR31** corretto a N2 (le specifiche lo indicano N1 con valori fino a 21);
- **reddito complessivo**: comprende i righi dei quadri RH e RL, i redditi esteri del rigo RM31 con l'opzione per la tassazione ordinaria e i righi RM22, RT82 e RT86; imposta netta RN26 al netto del solo rigo RN25 (che comprende RN23); ritenute del rigo RN33 anche dai quadri RH, RL e RT;
- **ritenute d'acconto del rigo RE26** dagli abbinamenti di tipo ritenuta degli incassi dell'anno (avviso per le imprese e i forfetari);
- **variazioni in diminuzione del quadro RF**: parte non imponibile dei ricavi dei conti con la percentuale nel rigo RF55 (codice 99), totali RF55 col. 77 e RF56 dai righi da RF34 a RF55 anche inseriti a mano, somma algebrica RF57 con le variazioni in diminuzione.

#### `Contabilita` 0.8.0

- **partite aperte obbligatorie** del partitario clienti e fornitori: ogni fattura, addebito o insoluto apre una partita, incassi, pagamenti, note di credito e compensazioni si abbinano alle partite dal form della prima nota con la proposta dalla più vecchia; modi di chiusura incasso o pagamento, ritenuta d'acconto, compensazione tra cliente e fornitore, nota di credito, acconto e abbuono o perdita su crediti; acconti e note non abbinati come partite da abbinare ai documenti successivi; insoluti che riaprono il documento; scissione dei pagamenti al netto dell'IVA;
- **scadenzario** dell'azienda per controparte con residui, abbinamenti e quadratura con il saldo del sottoconto; comando `sisma partite-aperte` per le registrazioni già presenti, con la conferma degli abbinamenti automatici;
- **totali di ricavi e costi per cassa** (`ComponentiReddito::getTotaliCassa`) per il reddito delle società di persone imputato ai soci;
- **componenti per cassa** dei quadri RG ed RE dagli abbinamenti: ritenute come parte del compenso, acconti alla data dell'incasso, insoluti nell'anno in cui si registrano, abbuoni e perdite esclusi;
- **ricavi imponibili in parte**: la percentuale del conto vale anche per i ricavi (parte imponibile): nei quadri RE e RG per cassa conta la parte imponibile, nel quadro RF la parte non imponibile è una variazione in diminuzione (RF55 codice 99); il form controlla che la variazione sia in aumento per i costi e in diminuzione per i ricavi.

### 🐛 Correzioni

#### `Deleghe` 0.7.1

- **descrizioni dei codici tributo con l'accento rovinato** ("ATTIVITÎ", "NONCHŸ", "IN CUI þ EFFETTUATO", "CREDITO DÆIMP."): nell'archivio dell'Agenzia le descrizioni in maiuscolo hanno lettere accentate e punteggiatura già alterate da una conversione precedente, che `LettoreFoglio` ricostruiva alla lettera; ora le sequenze corrispondono a À, É, È, apostrofo, trattino, barra ed euro (142 codici). `dati-riferimento.sql` rigenerato con `sisma tabelle-f24 --sql` dall'archivio del 03/11/2026, che aggiunge 32 codici, tra cui quelli della sanzione unica da ravvedimento (da 8960 a 8986, art. 13, comma 2-bis, D.Lgs. 472/1997), e le causali INPS `BISE` e `SALU`.

### 🔧 Modifiche

#### Dati demo

- **Alfa Consulting**: partite del 2026 con RiBa insoluta e nuovo incasso con abbuono, acconto, incasso dal Comune in scissione dei pagamenti, pagamento al netto della ritenuta d'acconto e compensazione tra credito e debito verso Delta;
- **Sara Gallo**: saldo e primo acconto dell'imposta sostitutiva in tre rate;
- **Mario Rossi**: interessi svizzeri con la tassazione ordinaria e credito del quadro CE, canoni non percepiti del 2024 (rigo CR8), socio del Bar Centrale s.n.c. (contabilità 2025, quadro RH), interessi e dividendi esteri nel rigo RM31, lavoro autonomo occasionale nel quadro RL, attività estere 2025 (conto in Svizzera, azioni statunitensi, appartamento a Nizza, bitcoin) nel quadro RW, cessioni di azioni e bitcoin nel quadro RT; interventi sulla casa di Roma (ristrutturazione 2021, facciata condominiale 2024, schermature solari 2023, bagno e mobili 2025) e relative rate nel modello Redditi PF 2026.

### ⚠️ Limiti Noti

- **quadro CE**: sezione II (imprese, art. 165, comma 6) a mano; i righi proposti vengono solo dalle attività estere con l'opzione per la tassazione ordinaria;
- **quadro CR**: i crediti dell'anno diversi dai residui (sisma Abruzzo, cultura, scuola e altri) a mano; il credito per i canoni non percepiti riliquida solo l'IRPEF lorda (senza la rendita catastale come minimo, le detrazioni e le addizionali: avviso) e richiede la dichiarazione dell'anno dei canoni nella suite;
- **quadri parziali**: RM tranne il rigo RM31, RT sezioni I, III e IV, RL righi da RL1 a RL13 e RL17, concordato preventivo biennale nel quadro RH, addizionali dovute dei dilettanti (RL24) a mano;
- **partite aperte**: la scadenza è la data del documento (i dati di pagamento della fattura elettronica non sono ancora letti);
- **file RPF26** non ancora verificato con il software di controllo dell'Agenzia.

## [0.22.0-alpha] - 2026-11-09 - Modello Redditi Persone Fisiche

Versioni dei moduli: `Anagrafica` 0.11.0, `Contabilita` 0.7.0, `Dichiarativi` 0.5.0 (richiede `Anagrafica` 0.11.0 e `Contabilita` 0.7.0), `Imu` 0.4.1 (richiede `Anagrafica` 0.11.0); gli altri invariati.

**Aggiornamento**: `sisma aggiorna-database` esegue `Anagrafica/Database/aggiornamento-0.11.sql` (tabelle `contratto_locazione`, `pertinenza_locata`, `variazione_canone`, colonne `dati_fiscali.coltivatore_iap` e `legame.disabile`, `convivente`, `percentuale_detrazione`, `affidamento`), `Contabilita/Database/aggiornamento-0.7.sql` (colonne `conto.rigo_redditi`, `percentuale_deducibile`, `variazione_redditi`) e `Dichiarativi/Database/aggiornamento-0.5.sql` (tabelle `certificazione_unica`, `valore_certificazione_unica`, `addizionale_regionale`, `addizionale_comunale`, colonne della dichiarazione per le scelte dell'otto, cinque e due per mille), idempotenti, e `Dichiarativi/Database/dati-riferimento.sql` con le aliquote delle addizionali 2025.

### ✨ Nuove Funzionalità

#### `Dichiarativi` 0.5.0 — modello Redditi Persone fisiche 2026

- **proposta del modello** dai dati della suite, con i valori corretti a mano conservati alle riproposte e i calcoli delle specifiche tecniche:
  - **RA e RB** dai possessi, dai dati catastali, dai contratti di locazione e, con il modulo IMU, dalle caratteristiche IMU: un rigo per ogni situazione dell'anno, rivalutazioni e regime dei coltivatori diretti e IAP, utilizzi e casi particolari IMU, canone per i giorni e ripartito tra le pertinenze, cedolare secca al 21, 10 o 26 per cento, sezioni del contratto in Abruzzo e del CIN;
  - **LC** con l'imposta della cedolare e gli acconti;
  - **RC** dall'archivio delle **Certificazioni Uniche** (nuovo, nella scheda del contribuente);
  - **prospetto dei familiari a carico** dai legami familiari dell'Anagrafica: mesi a carico dalle date del legame, limite di reddito (2.840,51 euro, 4.000 per i figli fino a 24 anni) dalla dichiarazione o dalle Certificazioni Uniche del familiare, figli dal più anziano con la percentuale (100 se il coniuge è a carico, altrimenti 50, o quella del legame) e i mesi da 21 a 29 anni, ascendenti e altri familiari conviventi;
  - **RE, RG, RF ed LM** dalla Contabilità secondo il regime: professionisti e semplificata per cassa, ordinaria con l'utile del conto economico e le variazioni in aumento per la parte indeducibile dei costi (righi RF16, RF18 e RF31, totale RF32), forfetari con il coefficiente di redditività e i contributi INPS dei versamenti F24;
  - **impresa familiare**: prospetto di imputazione del quadro RS e quote dei collaboratori (righi RG33 o RF98) dai legami "Collaboratore familiare", solo sul reddito positivo, con l'avviso oltre il 49 per cento;
  - **perdite d'impresa** degli anni precedenti (righi RG35 e RF100) dal prospetto RS12-RS13 della dichiarazione dell'anno prima, in misura limitata fino all'80 per cento del reddito e poi in misura piena, con i residui e la perdita dell'anno nel prospetto;
  - **quadro RP**: totali del rigo RP15 dai righi da RP1 a RP14 (franchigia delle spese sanitarie e veterinarie, rateizzazione, spese commisurate al reddito), rigo RN23, deducibili dei righi RP32-RP36 e totale RP39, con gli avvisi per gli importi oltre i limiti;
  - **RN**: imposta lorda, detrazioni per familiari (con il primo figlio senza l'altro genitore), lavoro, pensione e altri redditi, ulteriore detrazione, trattamento integrativo e somma esente, oneri detraibili del rigo RN13 con il **riordino delle detrazioni** oltre 75.000 euro (art. 16-ter Tuir: limite di spesa per i figli imputato dalla percentuale più alta, coefficiente oltre 120.000 euro), ritenute, acconti versati, saldo e acconti 2026;
  - **RV** con le **tabelle ufficiali delle addizionali** (nuove: regionali dalle istruzioni, comunali dall'elenco dell'Agenzia con il comando `sisma addizionali-comunali`) e il domicilio fiscale al 1° gennaio;
  - **RX** con i crediti proposti in compensazione;
- **scelte dell'otto, del cinque e del due per mille** nel frontespizio (istituzioni, finalità dello Stato, beneficiario del cinque per mille, partiti della tabella del modello) e nel file;
- **controlli** dei quadri RA e RB, **versamenti** di saldi e acconti (IRPEF, addizionali, cedolare, imposta dei forfetari) e **crediti** in Deleghe, **file telematico RPF26**.

#### `Anagrafica` 0.11.0

- **contratti di locazione e di affitto** nella scheda dell'immobile: tipo, canone e variazioni, cedolare secca con revoca, pertinenze locate insieme, registrazione e CIN;
- **legami familiari** tra i soggetti collegati: coniuge, figlio, genitore o altro ascendente, altro familiare, con disabilità, convivenza, percentuale della detrazione del figlio e affidamento;
- utilizzo **comodato a un familiare** nei possessi e casella **coltivatore diretto o IAP** nei dati fiscali delle persone fisiche.

#### `Contabilita` 0.7.0

- **rigo della dichiarazione dei redditi** sul conto (quadri RG ed RE), proposto dalla voce di conto economico;
- **percentuale deducibile** del costo sul conto (es. auto, telefonia) con il rigo della variazione del quadro RF: nei quadri RG ed RE conta la parte deducibile;
- **componenti del reddito per cassa** (incassi e pagamenti assegnati alle fatture della controparte dalla più vecchia) e per competenza.

### 🔧 Modifiche

#### `Imu` 0.4.1

- il comodato a un familiare vale come comodato nel calcolo e nelle aliquote differenziate.

#### Dati demo

- modelli Redditi PF 2026 di Mario Rossi (dipendente, casa locata con la cedolare e il box pertinenza, coniuge e due figli nei legami familiari, scelte dell'otto e del cinque per mille), Lucia Ferri (semplificata, impresa familiare con il figlio collaboratore, spese telefoniche deducibili all'80 per cento) e della nuova cliente Sara Gallo (forfetaria, con contabilità e contributi in Deleghe).

### ⚠️ Limiti Noti

- **quadro RP**: le spese si indicano a mano (la suite non ha un archivio degli oneri); le rate dei bonus edilizi e del risparmio energetico (sezioni III e IV, righi da RN14 a RN16) non sono calcolate e, oltre 75.000 euro di reddito, il riordino delle detrazioni con queste rate va ripartito a mano (avviso);
- **prospetto dei familiari a carico**: senza dichiarazione o Certificazioni Uniche del familiare nella suite il reddito non è verificato (avviso); i figli si registrano nella scheda di ciascun genitore;
- **quadri non proposti**: RH, RL, RM, RT, RW, CR e CE (si compilano a mano e vanno nel file);
- **redditi d'impresa**: le perdite pregresse si propongono solo dalla dichiarazione dell'anno prima nella suite (per il 2025 a mano); le variazioni in diminuzione del quadro RF e quelle in aumento diverse dai costi deducibili in parte a mano; le quote di reddito dei collaboratori nel regime forfetario a mano; gli incassi si assegnano alle fatture dalla più vecchia, senza abbinamento manuale;
- **addizionali**: le agevolazioni regionali per figli e disabilità e i casi particolari comunali sono avvisi da verificare;
- **file RPF26** non ancora verificato con il software di controllo dell'Agenzia.

## [0.21.0-alpha] - 2026-11-07 - Posta in Uscita, Invio delle Deleghe e Controlli dell'Agenzia

Ventunesima versione alpha: in `Telonio` la posta in uscita dello studio, con le caselle e-mail e PEC; in `Deleghe` il PDF della delega sul modello ufficiale e l'invio al cliente per e-mail o PEC; in `Dichiarativi` le correzioni emerse dal primo controllo dei file IVA 2026 e IVA TR con le procedure dell'Agenzia, che ora accettano tutte le dichiarazioni delle demo, e l'avviso sul visto di conformità apposto da chi non trasmette.

Versioni dei moduli: `Telonio` 0.7.0, `Deleghe` 0.7.0 (richiede `Telonio` 0.7.0), `Anagrafica` 0.10.1, `Contabilita` 0.6.1, `Dichiarativi` 0.4.1 (requisiti invariati); `Imu` 0.4.0, `Successioni` 0.3.2 e `GestioneStudio` 0.1.1 invariati.

**Aggiornamento**: `sisma aggiorna-database` crea la tabella `casella_posta` (`Telonio/Database/schema.sql`) ed esegue `Deleghe/Database/aggiornamento-0.7.sql` (colonna `delega.destinatario_consegna`, nuovo canale PEC), idempotente. L'immagine PHP comprende `qpdf` (`Dockerfile`) e l'archivio della release le nuove librerie PHP (PHPMailer, FPDF, FPDI): `sh aggiorna.sh` (con `sudo` se la cartella dell'installazione appartiene a root) ricostruisce i container e aggiorna il database. Per inviare le deleghe gli amministratori indicano le caselle in Studio > Posta in uscita; senza la casella e-mail dello studio valgono i parametri `MAIL_*` del `.env`, che in produzione vanno impostati con il server SMTP reale.

Dopo l'aggiornamento conviene riproporre le dichiarazioni IVA con l'ingresso in un Gruppo IVA (rigo VA11) e con rimborsi oltre 30.000 euro per cui la garanzia è dovuta (attestazione del VX4), e i modelli IVA TR dei non residenti.

### ✨ Nuove Funzionalità

#### `Telonio` 0.7.0 — posta in uscita dello studio

- **Studio > Posta in uscita** (solo amministratori): due caselle per studio, **e-mail ordinaria** e **PEC**, ognuna con il suo server SMTP (SSL/TLS, STARTTLS o nessuna protezione), utente, password cifrata nel database con `ENCRYPTION_PASSPHRASE` e mittente. "Invia una prova" manda un messaggio all'utente collegato;
- l'e-mail dello studio non configurata ricade sui parametri `MAIL_*` del `.env`, con il nome dello studio come mittente; la PEC va sempre configurata, perché ha valore di PEC solo il messaggio che parte dalla casella PEC dello studio;
- servizio `InvioPosta` (PHPMailer) riusabile dagli altri moduli per inviare documenti ai clienti.

#### `Deleghe` 0.7.0 — PDF e invio della delega al cliente

- **Scarica PDF**: la delega in PDF sul modello ufficiale dell'Agenzia (sfondo vettoriale), con i campi negli stessi punti della stampa, anche dalla pagina di stampa. Dalla delega si scaricano tutte le copie (una pagina per il semplificato);
- **Invia al cliente** (delega confermata o già consegnata): a ogni invio si sceglie la casella, e-mail o PEC. Il destinatario proposto è l'indirizzo del cliente in anagrafica dello stesso tipo, il principale per primo; oggetto e testo sono proposti e modificabili, e il PDF va in allegato, con tutte le copie o solo quella per il contribuente;
- riuscito l'invio, la delega diventa **Consegnata** con la data di oggi, il canale (e-mail o PEC) e l'indirizzo, mostrati nella delega. Se il server rifiuta il messaggio la delega resta confermata e si vede l'errore. Inviando di nuovo una delega consegnata si registra l'ultimo invio; annullando la consegna i dati dell'invio si tolgono;
- il canale della consegna distingue la **PEC** dall'e-mail: le consegne registrate prima con "Email / PEC" restano "E-mail";
- `sisma modelli-f24` scrive anche gli sfondi PDF (`Assets/pdf`), riscritti con `qpdf` senza object stream perché la libreria FPDI possa leggerli.

### 🔧 Modifiche

#### `Dichiarativi` 0.4.1

- **visto di conformità e incaricato della trasmissione**: un avviso nella dichiarazione IVA e nel modello TR quando il professionista del visto non è l'intermediario che trasmette, come segnalano le procedure di controllo dell'Agenzia (anomalia che non impedisce l'invio);
- fixture: il dott. Marini è intermediario dello studio e trasmette le dichiarazioni con il suo visto (Alfa, Nordic, Verde Holding).

### 🐛 Correzioni

Emerse dal controllo dei file IVA26 e IVT25 delle demo con le procedure dell'Agenzia (Mod. IVA 2026 versione 1.0.1, Mod. IVA TR versione 1.1.0).

#### `Dichiarativi` 0.4.1

- **modello IVA TR, codice fiscale della società dichiarante**: il campo del record B (posizione 1078) è numerico e da vuoto va a zeri, non a spazi. Tutti i file IVT25 erano scartati;
- **rigo VA11**: all'ultima dichiarazione prima dell'ingresso in un Gruppo IVA la suite proponeva la causale 13 e il credito da trasferire (VX2, campo 2) senza barrare la casella del VA11, richiesta dalle specifiche. Ora la casella è proposta e un controllo segnala la mancanza;
- **attestazione dell'art. 38-bis, c. 3**: era proposta anche quando la garanzia è dovuta. Senza il quadro VK la firma richiede l'esonero con il codice 1 (VX4, campo 7): ora è proposta solo in quel caso, con un controllo;
- **casella dei non residenti del modello TR** (TD4): un nuovo controllo richiede l'identificazione diretta (cifre 8-10 della partita IVA uguali a 999) o il rappresentante fiscale con il codice carica 6, come le specifiche.

#### Dati demo (`Anagrafica` 0.10.1, `Contabilita` 0.6.1, `Dichiarativi` 0.4.1)

- **partite IVA delle demo**: il controllo dell'Agenzia rifiuta i codici ufficio (cifre 8-10) come 200-900 e 000, e parte delle controparti aveva il carattere di controllo errato. Le partite IVA hanno ora un codice ufficio accettato e il carattere di controllo ricalcolato. Nordic Trade sottoscrive con il rappresentante fiscale.

### ⚠️ Limiti Noti

- **Codice ufficio della partita IVA**: la suite verifica solo il carattere di controllo. L'elenco dei codici ufficio ammessi non è nelle specifiche lette, quindi non c'è un controllo: il software dell'Agenzia li segnala.

## [0.20.0-alpha] - 2026-11-07 - Rettifiche della Detrazione e Autofatture Agricole

Ventesima versione alpha: in `Dichiarativi` il prospetto D delle rettifiche della detrazione con la proposta dei beni ammortizzabili al cambio di regime agricolo, le connesse occasionali nei modi ordinari, il quadro VO proposto dalla rinuncia all'esonero e dalle opzioni delle attività separate, le garanzie delle compensazioni di gruppo e il differimento della decadenza per il ritardo nei documenti; in `Contabilita` la tabella dei prodotti agricoli con la percentuale di compensazione e l'autofattura degli acquisti dagli agricoltori esonerati; in `Anagrafica` la rinuncia all'esonero.

Versioni dei moduli: `Anagrafica` 0.10.0, `Contabilita` 0.6.0 (richiede `Anagrafica` 0.9.0), `Dichiarativi` 0.4.0 (richiede `Anagrafica` 0.10.0 e `Contabilita` 0.6.0); `Telonio` 0.6.2, `Deleghe` 0.6.0, `Imu` 0.4.0, `Successioni` 0.3.2 e `GestioneStudio` 0.1.1 invariati.

**Aggiornamento del database**: `sisma aggiorna-database` esegue `Anagrafica/Database/aggiornamento-0.10.sql` (colonna `dati_fiscali.rinuncia_esonero`), `Contabilita/Database/aggiornamento-0.6.sql` (tabella `prodotto_agricolo`, colonne dell'opzione su `attivita_iva`, `controparte.agricoltore_esonerato`, `riga_iva.coefficiente_elevato`, conto abituale riportato sulle righe IVA senza conto) e `Dichiarativi/Database/aggiornamento-0.4.sql` (tabella `rettifica_detrazione`, colonne `garanzia_rimborso.compensazione_gruppo`, `garanzia_rimborso.data_richiesta_documenti` e `garanzia_rimborso.data_consegna_documenti`), tutti idempotenti, e aggiorna i dati di riferimento con i prodotti. Con `sh aggiorna.sh` (con `sudo` se la cartella dell'installazione appartiene a root) sono compresi.

Dopo l'aggiornamento conviene riproporre le dichiarazioni IVA e rielaborare le liquidazioni delle imprese agricole miste con conti abituali di immobilizzazione, e di chi ha connesse occasionali nei modi ordinari. Nei dati fiscali e sulle attività vanno indicate le rinunce e le opzioni già in corso.

### ✨ Nuove Funzionalità

#### `Dichiarativi` 0.4.0

- **prospetto D** delle rettifiche della detrazione per modulo, con il totale nel rigo VF70. Al passaggio tra il regime ordinario e un regime speciale agricolo del 2025 e del 2026 propone i beni ammortizzabili dei registri, per i quinti mancanti (decimi per terreni e fabbricati). I beni con coefficiente di ammortamento oltre il 25% (art. 19-bis2, c. 5) si segnano sulla proposta ed escono dal totale: la spunta resta sulla riga IVA. Rimanenze e servizi non utilizzati restano a mano;
- **connesse occasionali nei modi ordinari**: per chi applica l'IVA nei modi ordinari per opzione il rigo VF62, gli acquisti nel VF18 e il 50% nel VF71 non sono più a mano;
- **quadro VO**: la rinuncia all'esonero indicata nei dati fiscali compila le caselle 1 e 2 del VO3, le attività separate hanno opzioni e revoche proprie;
- **garanzie delle compensazioni di gruppo**: spunta dedicata, con la durata dalla presentazione della dichiarazione;
- **differimento della decadenza nelle garanzie**: con la notifica della richiesta di documenti e la consegna oltre i quindici giorni, la decadenza si sposta dal sedicesimo giorno alla consegna (art. 57, c. 3) e la scadenza proposta ne tiene conto;
- fixture: Le Querce Agricola con l'opzione su un'attività separata, Mario Campi con connessa occasionale, prospetto D e un bene con coefficiente oltre il 25%, Tenuta Colli Berici con un'autofattura, Delta Holding con la garanzia di gruppo, Nordic con i documenti consegnati in ritardo.

#### `Contabilita` 0.6.0

- **tabella dei prodotti agricoli** con la percentuale di compensazione e gli anni di validità (`sorgenti/prodotti-agricoli.csv`): in prima nota il prodotto propone la percentuale, sempre modificabile;
- **autofatture degli acquisti dagli agricoltori esonerati** (art. 34, c. 6): dalla registrazione si scarica il file FatturaPA validato sullo schema, con soggetto emittente CC, regime del cedente RF04 e la percentuale come aliquota, da caricare sul portale;
- opzione per i modi ordinari sulle attività separate, con il primo e l'ultimo anno; connesse occasionali anche fuori dal regime speciale.

#### `Anagrafica` 0.10.0

- nei dati fiscali la **rinuncia all'esonero** dell'art. 34, c. 6.

### 🐛 Correzioni

#### `Contabilita` 0.6.0

- **beni ammortizzabili non riconosciuti**: con il conto abituale della controparte e senza un conto sulla riga IVA, un bene ammortizzabile destinato alle operazioni diverse dava detrazione. Il conto abituale ora resta sulla riga.

#### `Dichiarativi` 0.4.0

- **avviso superato sulle connesse occasionali**: dalla 0.3.0 gli acquisti segnati per le connesse vanno da soli nel rigo VF18, ma la proposta chiedeva ancora di spostarli a mano. Ora avvisa solo se ci sono operazioni nel VF62 senza acquisti destinati.

### 🔧 Modifiche

#### `Dichiarativi` 0.4.0

- **limiti della 0.16.0-alpha chiusi dopo la verifica sulle istruzioni**: VS30 (comprende il conguaglio annuale e la precedenza dei crediti di gruppo è quella delle istruzioni), rigo TD5 e causale 5 seguono il testo delle istruzioni, senza modifiche al calcolo;
- **acquisti promiscui delle imprese agricole miste**: verificato sui testi della circolare 328/E/1997, par. 6.4, e della 44/E/2002, par. 3.1, che la detrazione analitica in uso è corretta: i costi indivisibili non si detraggono e di un acquisto promiscuo si segna, su una riga a parte, solo la quota specificamente imputabile. Il calcolo non cambia; sono aggiornati l'avviso del VF38, la documentazione e i test, e il limite della 0.19.0-alpha è chiuso.

### ⚠️ Limiti Noti

- **Tabella dei prodotti**: i prodotti vengono da una tabella di categoria, controllata sui D.M. 1992, 2005 e 2016. Il testo del D.M. 30 dicembre 1997 non è stato trovato: le misure del 7%, 7,5% e 8,5% sono confermate dal modello 2026 e da Fisco Oggi. Legna e legno squadrato sono al 2% dal 2026 finché un decreto non innalza la misura;
- **Autofatture**: le specifiche tecniche non trattano il caso e si attende un chiarimento dell'Agenzia; il file si carica sul portale, la suite non trasmette al Sistema di Interscambio;
- **Rettifica per il cambio di regime**: il modello 2026 non indica il caso nel rigo 3, che è quello delle istruzioni precedenti; rimanenze e servizi restano a mano. La valutazione delle rimanenze viene da fonti di categoria: le circolari fissano l'obbligo, non il metodo;
- **Garanzie di gruppo**: la decorrenza dei tre anni dalla presentazione della dichiarazione è una lettura, la norma non la indica in modo espresso. La riduzione di due anni della decadenza per chi garantisce la tracciabilità dei pagamenti (art. 293, c. 7, D.Lgs. 141/2026) non è gestita: la scadenza si corregge a mano;
- **Testi unici dal 2027**: dal 1° gennaio 2027 si applicano il Testo unico IVA (D.Lgs. 10/2026) e il Testo unico adempimenti e accertamento (D.Lgs. 141/2026). I termini non cambiano (art. 293 per l'art. 57), la suite cita ancora il D.P.R. 633/1972 e un decreto correttivo è in corso di approvazione.

## [0.19.0-alpha] - 2026-11-06 - Garanzie dei Rimborsi e Opzioni Agricole

Diciannovesima versione alpha: in `Dichiarativi` l'archivio delle garanzie dei rimborsi IVA con le scadenze nello scadenzario, le opzioni e revoche agricole del quadro VO proposte dai dati fiscali e gli acquisti delle connesse occasionali nel rigo VF18; in `Contabilita` la correzione della detrazione dei beni ammortizzabili nelle imprese agricole miste; in `Deleghe` la scelta del modello nella generazione della delega. Valgono le avvertenze della 0.1.0-alpha: la versione non è indicata per l'uso in produzione su dati reali.

Versioni dei moduli: `Anagrafica` 0.9.0, `Contabilita` 0.5.0, `Deleghe` 0.6.0, `Dichiarativi` 0.3.0 (richiede `Anagrafica` 0.9.0 e `Contabilita` 0.5.0); `Telonio` 0.6.2, `Imu` 0.4.0, `Successioni` 0.3.2 e `GestioneStudio` 0.1.1 invariati.

**Aggiornamento del database**: `sisma aggiorna-database` esegue `Anagrafica/Database/aggiornamento-0.9.sql` (colonna `dati_fiscali.opzioni_ordinarie`) e `Dichiarativi/Database/aggiornamento-0.3.sql` (tabella `garanzia_rimborso`), entrambi idempotenti. Con `sh aggiorna.sh` (con `sudo` se la cartella dell'installazione appartiene a root) sono compresi.

Dopo l'aggiornamento conviene controllare le liquidazioni e riproporre le dichiarazioni IVA delle imprese agricole miste con acquisti di beni ammortizzabili segnati per le operazioni diverse: VF53, VF71 e l'IVA detraibile possono cambiare. Nei dati fiscali vanno indicate le opzioni per i modi ordinari già in corso, altrimenti al primo anno dopo l'indicazione risulterebbero come nuove opzioni.

### ✨ Nuove Funzionalità

#### `Deleghe` 0.6.0 — scelta del modello nella generazione della delega

- **"Genera la delega"** nella scheda del contribuente chiede il modello quando i versamenti della data ne ammettono più di uno: F24 ordinario o semplificato per Erario, Regioni, IMU e tributi locali. Prima la delega nasceva sempre ordinaria (o completava una bozza già presente) e il semplificato si poteva impostare solo modificandola dopo;
- il modello proposto è l'ordinario, o il semplificato se nella data c'è già una bozza semplificata da completare e tutti i versamenti lo ammettono. Dove il modello è obbligatorio la scelta non compare: ELIDE e accise restano nelle loro deleghe, le date con soli contributi INPS, INAIL o altri enti generano l'ordinario;
- con il semplificato scelto e tra gli spuntati un versamento che non ammette (INPS, INAIL, altri enti), la delega non viene generata e il messaggio indica quale versamento togliere.

#### `Dichiarativi` 0.3.0

- **archivio delle garanzie dei rimborsi IVA** nella scheda del contribuente (art. 38-bis, c. 5): tipo, garante, importo, rilascio, esecuzione del rimborso e svincolo. La scadenza è proposta in tre anni dall'esecuzione del rimborso o, se inferiore, nel periodo mancante alla decadenza dell'accertamento, e compare nello scadenzario dello studio finché la garanzia non è svincolata;
- **opzioni agricole del quadro VO** proposte dal confronto dei dati fiscali con quelli dell'anno precedente (`Services\Iva\OpzioniAgricole`): rinuncia all'esonero nei limiti di volume (VO3, casella 1), opzioni per i modi ordinari e revoche per art. 34 e 34-bis (VO3, caselle 3-6), associazioni (VO31), agriturismo (VO32), enoturismo (VO35) e oleoturismo (VO36), con l'avviso per le revoche entro il triennio;
- **acquisti delle connesse occasionali** nel rigo VF18, campo 1, invece che nei righi dell'aliquota; prima andavano spostati a mano;
- fixture: Nordic Trade con una polizza in corso e una fideiussione svincolata, Mario Campi con la rinuncia all'esonero e l'opzione per i modi ordinari, Tenuta Colli Berici con la revoca dell'opzione dell'agriturismo e un acquisto per le connesse occasionali.

#### `Anagrafica` 0.9.0

- nei **dati fiscali** storicizzati: regimi agricoli a cui si è rinunciato per applicare l'IVA nei modi ordinari.

#### `Contabilita` 0.5.0

- in prima nota, con il regime agricolo, la casella "Op. diverse" degli acquisti diventa la scelta **"Destinazione"**: operazioni diverse o connesse occasionali.

### 🐛 Correzioni

#### `Contabilita` 0.5.0

- **beni ammortizzabili nelle imprese agricole miste** (art. 34, c. 5): un acquisto su un conto di immobilizzazione segnato per le operazioni diverse dava detrazione. Ora è escluso dalla liquidazione e dal VF53, con un avviso nella proposta della dichiarazione.

### ⚠️ Limiti Noti

- **Quadro VO**: il ritorno all'esonero (VO3, casella 2) resta un avviso, perché non si distingue dal rientro nei limiti senza una rinuncia precedente; le revoche e le opzioni si leggono dai dati fiscali del contribuente, non dalle singole attività separate;
- **Connesse occasionali fuori dal regime speciale**: il rigo VF62 e la detrazione del 50% restano a mano, con un avviso;
- **Imprese agricole miste**: gli acquisti promiscui non danno detrazione in attesa della verifica della circolare 328/E, par. 6.4;
- **Garanzie del Gruppo IVA**: si registrano nello stesso archivio, ma la scadenza proposta è quella del rimborso e va controllata.

## [0.18.0-alpha] - 2026-11-06 - Casi Particolari IMU

Diciottesima versione alpha: in `Imu` i casi particolari delle aliquote e dell'imposta, dal comodato ai parenti ai pensionati residenti all'estero, dagli alloggi IACP ai concessionari di aree demaniali, e i territori dove l'IMU non si applica; il controllo delle aliquote differenziate non perde più le condizioni che non riconosce. Valgono le avvertenze della 0.1.0-alpha: la versione non è indicata per l'uso in produzione su dati reali.

Versioni dei moduli: `Anagrafica` 0.8.0, `Imu` 0.4.0 (richiede `Anagrafica` 0.8.0); `Telonio` 0.6.2, `Deleghe` 0.5.0, `Successioni` 0.3.2, `GestioneStudio` 0.1.1, `Contabilita` 0.4.0 e `Dichiarativi` 0.2.0 invariati.

**Aggiornamento del database**: `sisma aggiorna-database` esegue `Imu/Database/aggiornamento-0.4.sql` (colonna `caratteristiche_imu.alloggio_iacp`).

Dopo l'aggiornamento conviene rifare i calcoli IMU degli immobili interessati: le correzioni qui sotto cambiano gli importi nei comuni di Trento, Bolzano e Friuli Venezia Giulia, per i pensionati residenti all'estero con requisiti mancanti e dove una differenziata era applicata in automatico.

### ✨ Nuove Funzionalità

#### `Imu` 0.4.0

- **aliquote per il comodato ai parenti e per i pensionati residenti all'estero**: le spunte delle agevolazioni IMU entrano nel controllo delle differenziate (`Services\CompatibilitaDifferenziate`, `Services\ContestoAliquota`). Con il comodato del comma 747, lett. c) parentela, contratto registrato e abitazione principale sono verificati e l'aliquota "per le ipotesi diverse" è esclusa; le aliquote per i pensionati si considerano solo con l'agevolazione indicata;
- **alloggi regolarmente assegnati dagli IACP** (art. 1, comma 749, secondo periodo, L. 160/2019): nuova caratteristica IMU, restano imponibili con la detrazione per singolo alloggio; le differenziate IACP si considerano solo per questi alloggi;
- **esenzioni del comma 759** lett. a), c), d), e), f) in `EsenzioneImu` (enti pubblici, usi culturali, culto, Santa Sede, Stati esteri e organizzazioni internazionali), prima solo "Altra esenzione";
- fixture per tutti questi casi, con aliquote demo dello studio a Bergamo.

#### `Anagrafica` 0.8.0

- **concessione di area demaniale** tra i titoli di possesso (`TitoloPossesso`): il concessionario è soggetto passivo IMU (art. 1, comma 743, L. 160/2019).

### 🐛 Correzioni

#### `Imu` 0.4.0

- **IMU calcolata dove non si applica** (art. 1, comma 739, L. 160/2019): negli immobili delle province di Trento (IMIS) e Bolzano (IMI) e dal 2023 del Friuli Venezia Giulia (ILIA) il calcolo applicava l'IMU e la dichiarazione la proponeva. Ora l'immobile compare senza imposta con un avviso e non entra nelle dichiarazioni IMU; IMIS, IMI e ILIA non sono calcolate dal modulo. L'importazione non tratta più come errore le delibere IMIS e IMI presenti nell'elenco del Dipartimento delle Finanze;
- **differenziate perse o applicate per errore**: una differenziata senza condizioni riconosciute non era né applicata né segnalata, e una con più condizioni poteva essere applicata in automatico ignorando quelle non riconosciute (es. età del soggetto, durata del contratto). La descrizione ora si legge voce per voce: una voce non riconosciuta resta da verificare con il suo testo e l'aliquota è suggerita negli avvisi. Anche quando più differenziate risultano verificate vengono suggerite, invece di essere ignorate;
- riconosciuti i contratti ai sensi dell'art. 2, comma 1, L. 431/1998 e la voce "Tipo contratto: Locazione";
- **riduzione del pensionato residente all'estero** (L. 178/2020, c. 48): si applicava a qualsiasi immobile con la spunta. Ora spetta a una sola unità a uso abitativo, non locata né in comodato, posseduta in proprietà o usufrutto; negli altri casi il calcolo non la applica e lo segnala.

### ⚠️ Limiti Noti

- **IMIS, IMI e ILIA**: non sono calcolate, il modulo segnala solo che l'IMU non si applica;
- **Esenzioni riferite a una sola rata** (es. art. 78 D.L. 104/2020 per la seconda rata 2020): un'esenzione vale per il periodo indicato, non per la rata;
- **Dichiarazioni IMU**: per il concessionario di aree demaniali e per gli alloggi IACP non sono proposte in automatico;
- **Detrazione degli alloggi IACP nel modello F24**: il rigo la riporta sul codice 3918, da verificare sulle istruzioni ufficiali.

## [0.17.0-alpha] - 2026-11-06 - Rimborsi IVA, Accertamenti e Regimi Agricoli

Diciassettesima versione alpha: in `Dichiarativi` la dichiarazione IVA si completa con il rimborso annuale e le sue causali, l'archivio degli atti di accertamento e i regimi speciali agricoli, esonerati compresi; in `Contabilita` il credito chiesto con il modello IVA TR esce dal riporto con la rielaborazione automatica delle liquidazioni. Corregge diversi righi della 0.16.0-alpha. Valgono le avvertenze della 0.1.0-alpha: la versione non è indicata per l'uso in produzione su dati reali.

Versioni dei moduli: `Anagrafica` 0.7.0, `Contabilita` 0.4.0, `Dichiarativi` 0.2.0; `Telonio` 0.6.2, `Deleghe` 0.5.0, `Imu` 0.3.2, `Successioni` 0.3.2 e `GestioneStudio` 0.1.1 invariati.

**Aggiornamento del database**: `sisma aggiorna-database` esegue `Anagrafica/Database/aggiornamento-0.7.sql` (colonne `dati_fiscali.data_inizio_attivita`, `dati_fiscali.non_residente_iva`, `dati_fiscali.startup_innovativa`, `dati_fiscali.gruppo_iva_art70bis`, `dati_fiscali.regime_detrazione_iva`), `Contabilita/Database/aggiornamento-0.4.sql` (tabella `credito_trimestrale_iva`, con i crediti già annotati sulle liquidazioni; colonne `riga_iva.percentuale_compensazione`, `riga_iva.operazione_diversa`, `attivita_iva.regime_detrazione_iva`) e `Dichiarativi/Database/aggiornamento-0.2.sql` (tabella `atto_accertamento`, colonna `dichiarazione.verifica_accertamenti`), tutti idempotenti; `Contabilita/Database/dati-riferimento.sql` aggiunge i codici IVA dei conferimenti agricoli (`C2`-`C12.3`).

Dopo l'aggiornamento conviene riproporre le dichiarazioni IVA e i modelli IVA TR non ancora presentati: VL8, VL9, VL10, VL22, VL25, VW22, VW24, VS30, il rigo TA30 e i presupposti TD2 e TD5 possono cambiare. Nei dati fiscali conviene indicare la data di inizio attività delle imprese individuali: senza, i rimborsi oltre 30.000 euro segnalano il rischio.

### ✨ Nuove Funzionalità

#### `Anagrafica` 0.7.0

- nei **dati fiscali** storicizzati: regime speciale agricolo, inizio dell'attività, start-up innovativa, non residente con identificazione diretta o rappresentante fiscale, partecipazione a un Gruppo IVA (artt. 70-bis e seguenti).

#### `Contabilita` 0.4.0

- **rielaborazione automatica delle liquidazioni** dopo la presentazione o l'annullamento di un modello IVA TR, per la società e per il gruppo: si ferma alla prima con la comunicazione inviata, un versamento in una delega trasmessa o il giroconto stampato in definitiva, segnalandola;
- **credito chiesto con il modello IVA TR registrato a parte** (`credito_trimestrale_iva`): vale anche se la liquidazione che chiude il trimestre è elaborata dopo la presentazione, o eliminata e rielaborata;
- nuova **qualifica** delle righe IVA per le prestazioni verso soggetti non stabiliti dell'art. 30, c. 2, lett. d);
- **regimi speciali agricoli**, per azienda o per attività separata: art. 34 con la percentuale di compensazione sulle righe IVA, conferimenti, imprese miste con gli acquisti destinati alle operazioni diverse, IVA teorica delle cessioni non imponibili, connesse occasionali; art. 34-bis, agriturismo, enoturismo e oleoturismo con la detrazione del 50%, associazioni con un terzo; agricoltori esonerati senza liquidazioni, con la verifica dei limiti.

#### `Dichiarativi` 0.2.0

- **causale 11** del rimborso annuale per le persone fisiche che passano al regime forfetario dall'anno successivo;
- **causale 6** e rigo **TD4** per i non residenti, **causale 13** per l'ingresso in un Gruppo IVA (VX2 e VY2 campo 2, VY4 campo 3, credito ripartito al netto della parte da trasferire), **causale 10** per il credito non trasferibile di chi partecipa all'IVA di gruppo;
- **erogazione prioritaria** 2, 3, 4, 5 e 7 dal codice ATECO con il raccordo ufficiale ISTAT, nel modello IVA TR e nella dichiarazione annuale;
- **regimi speciali agricoli**: un modulo per regime con la casella del VF30, sezione 3-B del quadro VF (VF38-VF55), sezione 1 del VE per conferimenti ed esonerati, VF62, VF71 forfetario, TC5 del modello IVA TR, **causale 7** con il limite dell'IVA teorica, dichiarazione degli esonerati solo oltre i limiti e senza il quadro VH;
- **archivio degli atti di accertamento** nella scheda del contribuente, con le soglie dell'art. 38-bis, c. 4, lett. b), e casella del frontespizio che attesta la verifica degli atti;
- **rischi dei rimborsi oltre 30.000 euro** (art. 38-bis, c. 4): impresa attiva da meno di due anni, salvo le start-up innovative, cessazione e atti di accertamento oltre le soglie rendono dovuta la garanzia anche con visto e attestazione; **limite annuale di 2.000.000 di euro** per la procedura semplificata con le compensazioni in Deleghe;
- fixture: Adria Logistica (TD5 e causale 5), Cinema Odeon (TD3, causale 4 e priorità 7), Alberto Conti (causale 11), Nordic Trade (TD4, causale 6, garanzia dovuta), Beta Partecipata (causale 13), gruppo Delta (credito non trasferibile con VL9, VL10 e causale 10; credito di gruppo con VW24, VW25, VS30 e quadro VZ), Tenuta Colli Berici (cinque moduli per regime, comunicazione delle liquidazioni), Cantina Export (IVA teorica, causale 7), due agricoltori esonerati; i modelli TR di Omega Export e del gruppo Verde sono presentati con le liquidazioni dell'anno già elaborate.

### 🐛 Correzioni

#### `Dichiarativi` 0.2.0

- il **credito di gruppo** dell'anno precedente della controllante era contato anche nel suo VL8, oltre che nel VW25: ora il VL8 ne è al netto;
- il **credito dell'anno precedente** di chi partecipa al gruppo per l'intero anno finiva nel VL25 e si trasferiva al gruppo: ora è nel VL10 e nel VX2 (art. 73, ultimo comma);
- **VL22 e VW22** contavano tutti gli utilizzi dei crediti 6036-6038: ora solo quelli nelle deleghe trasmesse o quietanzate fino alla presentazione della dichiarazione, con un avviso per gli esclusi; stesso criterio per **VL9** e **VW24**;
- il rigo **TA30** e i presupposti **TD2** comprendevano le altre non imponibili (N3.6), che vanno nel TA31;
- **causale 3** dai righi VE30 e VE31 sulla somma in valore assoluto di VE40 e VE50, come dicono le istruzioni, e non dalle nature delle righe IVA;
- **TD5** comprendeva tutte le operazioni non soggette per territorialità: ora solo le prestazioni verso soggetti non stabiliti dell'art. 30, c. 2, lett. d), dalla qualifica della riga IVA; **causale 5** dalle operazioni non soggette (VE34) con le operazioni degli artt. 8, 8-bis, 9, 41 e 58 D.L. 331/1993;
- **VS30** dalle eccedenze di debito delle società (VK22), compreso il conguaglio annuale, invece che dai debiti lordi delle sole liquidazioni periodiche;
- nei Limiti Noti della 0.16.0-alpha la prevalenza del TD5 e della causale 5 è descritta come una lettura delle istruzioni: la regola è esplicita ed era il calcolo a semplificarla.

### ⚠️ Limiti Noti

- **File telematici**: i file "IVA26" e "IVT25" non sono ancora verificati con il software di controllo dell'Agenzia delle Entrate;
- **Regimi agricoli**: la percentuale di compensazione si sceglie sulla riga, senza una tabella dei prodotti; le opzioni del quadro VO, lo spostamento nel VF18 per il VF62, la rettifica nel passaggio tra regimi (art. 19-bis2) e le autofatture degli acquirenti dagli esonerati sono a mano;
- **Rimborso annuale**: la gestione delle garanzie (polizza, importo, scadenza) non è prevista;
- **Redditi PF**: solo il catalogo dei campi; proposta e file telematico non sono ancora disponibili.

## [0.16.0-alpha] - 2026-11-04 - Dichiarazioni Fiscali

Sedicesima versione alpha: il nuovo modulo `Dichiarativi` con la dichiarazione IVA annuale proposta dalla contabilità, il prospetto IVA 26 PR del gruppo, il rimborso annuale e il modello IVA TR; in `Contabilita` attività con contabilità separata, IVA di gruppo e pro-rata di detrazione. Valgono le avvertenze della 0.1.0-alpha: la versione non è indicata per l'uso in produzione su dati reali.

Versioni dei moduli: `Dichiarativi` 0.1.0 (nuovo), `Contabilita` 0.3.0, `Telonio` 0.6.2; `Anagrafica` 0.6.0, `Deleghe` 0.5.0, `Imu` 0.3.2, `Successioni` 0.3.2 e `GestioneStudio` 0.1.1 invariati.

**Aggiornamento del database**: `sisma aggiorna-database` installa il nuovo modulo con `Dichiarativi/Database/schema.sql` (tabelle `campo_modello`, `quadro_modello`, `dichiarazione`, `valore_dichiarazione`, `modulo_dichiarazione`, `sottoscrizione_controllo`) e `Dichiarativi/Database/dati-riferimento.sql` (catalogo dei campi dei modelli IVA 2026, IVA TR 2025 e Redditi PF 2026); esegue `Contabilita/Database/aggiornamento-0.3.sql` (colonne `riga_iva.natura_inversione`, `riga_iva.qualifica`, `riga_iva.aliquota_teorica`, `riga_iva.ripartizione`, `registrazione_contabile.importazione`, `registro_iva.unita_locale_id`, `registro_iva.attivita_iva_id`, `liquidazione_iva.trimestre_anticipato`, `liquidazione_iva.gruppo_iva_id`, `liquidazione_iva.trimestre_separato`, `liquidazione_iva.credito_trimestrale`, `acconto_iva.gruppo_iva_id`, `comunicazione_lipe.gruppo_iva_id`, `modulo_lipe.liquidazione_gruppo_iva_id`, chiavi uniche di `acconto_iva` e `comunicazione_lipe` estese al gruppo, tabelle `prorata_iva`, `attivita_iva`, `gruppo_iva`, `liquidazione_gruppo_iva`; idempotente), poi `Contabilita/Database/schema.sql` crea `liquidazione_attivita` e `partecipante_gruppo_iva`; `Contabilita/Database/dati-riferimento.sql` aggiunge al piano dei conti standard il conto 06.04.0004 "IVA di gruppo c/liquidazioni" e aggiorna le descrizioni dei codici IVA N3.4 e N3.6 con `Contabilita/Database/dati-riferimento.sql`.

### ✨ Nuove Funzionalità

#### `Dichiarativi` 0.1.0 — dichiarazioni fiscali

- **catalogo dei campi dei modelli** per modello e anno ricavato dalle specifiche tecniche ufficiali dell'Agenzia delle Entrate: `sisma specifiche-dichiarativi` scarica i PDF elencati in `Dichiarativi/Database/sorgenti/specifiche.csv`, ne legge le tabelle dei campi non posizionali (codice, formato, descrizione) e le denominazioni dei quadri con `pdftotext -tsv` e riscrive le sorgenti CSV; `sisma tabelle-dichiarativi` le importa (IVA 2026: 1.144 campi, Redditi PF 2026: 4.605);
- **dati dei quadri per codice del campo** (es. `VE023001`), come nel record a struttura variabile delle forniture: un solo motore per i modelli, valori in forma canonica validati sul formato delle specifiche, valori **proposti** dai dati della suite e **manuali** (corretti a mano, mantenuti dalle proposte successive con l'avviso delle differenze, ripristinabili), totali ricalcolati dopo ogni modifica;
- **dichiarazione IVA annuale** (modello IVA 2026, anno 2025) proposta da Contabilità: VA (ATECO di fine anno), VE per aliquota e natura con ventilazione annuale dei corrispettivi, scissione dei pagamenti (VE38) ed esigibilità differita (VE37), VF con la sola quota detraibile e il VF21 per l'indetraibile, VF29 con i beni ammortizzabili dai conti delle immobilizzazioni, VJ per le integrazioni in inversione contabile nel rigo della tipologia N6 della riga IVA, VH solo per i periodi senza comunicazione delle liquidazioni periodiche inviata, VL con il credito dell'anno precedente e il suo utilizzo in F24 dall'archivio di Deleghe, IVA periodica dovuta e versata, formule dei righi VL32 e VL33 delle specifiche e interessi dei trimestrali, VT obbligatorio con la ripartizione tra consumatori finali (per regione dell'unità locale del registro IVA o del luogo di esercizio) e soggetti IVA, VX;
- **pro-rata di detrazione** con operazioni esenti: casella del VF30, campi del VF34 dalle qualifiche delle righe IVA (esenti occasionali, beni ammortizzabili, ...) e percentuale con la formula e l'arrotondamento delle istruzioni, VF35 dall'aliquota teorica degli acquisti con dichiarazione d'intento, VF37 e VF71, cessioni di beni ammortizzabili nel VE40, importazioni nel VF28, sole operazioni esenti nel VF32; la percentuale diventa il pro-rata provvisorio dell'anno successivo in Contabilità;
- **attività con contabilità separata** della Contabilità: un modulo per attività creato e numerato dalla proposta (VE, VF, VJ e sezione 1 del VL dai suoi registri e dalle quote degli acquisti promiscui, pro-rata per attività registrato come provvisorio dell'anno successivo), VH con la liquidazione anticipata delle attività trimestrali e interessi nei righi VL23 e VL36;
- **IVA di gruppo** (art. 73, c. 3): per le società partecipanti crediti e debiti trasferiti nei righi VL21 e VL31, conguaglio trasferito nei righi VX7 e VX8 con il calcolo del paragrafo 2.9.1 delle specifiche, quadro VK con l'eccedenza compensata e la sezione 3 per la cessazione del controllo; per la controllante quadro VG (opzione, ingressi e uscite dal 1° gennaio, revoca) e prospetto IVA 26 PR (VS, VV, VW, VY) dalle dichiarazioni delle società e dalle liquidazioni del gruppo, campo V1000002 nel file, saldo e credito del gruppo in Deleghe;
- **modello IVA TR** per il credito dei primi tre trimestri: quadri TA, TB, TC e TD proposti dalle righe IVA con i presupposti (aliquota media, non imponibili, beni ammortizzabili, non soggette), prospetto TE della controllante con i moduli delle società, controlli (soglia di 2.582,28 euro, importi, visto oltre 5.000 euro annui), file telematico "IVT25", credito in compensazione nell'archivio di Deleghe con i codici 6036-6038 e righi VL20 e VL22 (VW20, VW22 e VS campo 5 per il gruppo) della dichiarazione annuale;
- **rimborso annuale**: indicato l'importo nel VX4 sono proposti causale (dalle righe IVA dell'anno e dalle dichiarazioni precedenti, con la minore eccedenza del triennio), erogazione prioritaria, esonero dalle garanzie e attestazione patrimoniale; nuovi flag del frontespizio per attestazione patrimoniale, adempimento collaborativo e concordato biennale; per il gruppo quota per società con causale, priorità ed esonero (VS campi 8-12, VS20, VS22), VY4, VK25 delle controllate, VW24 e VS30 da Deleghe, quadro VZ dai prospetti precedenti;
- **quadri VG e VS** oltre i tre e i dodici righi proposti nei quadri successivi (con il progressivo del record D insieme ai moduli della dichiarazione, senza contarli nel record B);
- **più moduli**: attività con contabilità separata non tenute nella suite (compilate a mano), soggetti partecipanti a operazioni straordinarie proposti dalla loro contabilità (VA1) fino alla data dell'operazione, prima frazione d'anno delle procedure concorsuali (VA3), con i righi VL32-VL41 per gruppi di moduli e il VX sulla somma dei moduli come nelle specifiche, un record D per modulo nel file;
- **quadro VC** degli esportatori abituali: plafond utilizzato per mese dalla data dei documenti con dichiarazione d'intento e dalle importazioni, volume d'affari ed esportazioni per mese dell'anno e del precedente, metodo solare o mensile con il plafond disponibile e la verifica mese per mese dei superamenti; gli altri quadri del modello (VD, VO, VQ, ...) si aggiungono e compilano a mano;
- **visto di conformità** del professionista o del CAF imprese (con il responsabile dell'assistenza fiscale) ed esonero (premialità ISA) nel frontespizio, **sottoscrizione dell'organo di controllo** (revisore, società di revisione, sindaci) proposta dai legami dell'Anagrafica, con gli avvisi per i crediti oltre 5.000 euro e i rimborsi oltre 30.000 euro; causale del rimborso controllata;
- **controlli** degli errori bloccanti del frontespizio e dei controlli di rispondenza delle specifiche (superabili con il flag di conferma), avvertenze sul visto di conformità oltre 5.000 euro;
- **frontespizio** proposto (natura giuridica dalla tabella delle istruzioni, rappresentante legale con il codice di carica, intermediario predefinito con l'impegno) e **file telematico** "IVA26" (record A, B, D, Z), stato di presentazione con data e protocollo, stampa di controllo;
- **saldo e credito in Deleghe F24**: saldo 6099 entro il 16 marzo, a rate mensili con il piano di rateazione di Deleghe o differito al termine del saldo delle imposte sui redditi con la maggiorazione dello 0,40% per mese o frazione (e l'ulteriore 0,40% nei 30 giorni successivi), anche a rate (per i trimestrali al posto del versamento della liquidazione del quarto trimestre), credito da compensare compensabile dal 1° gennaio fino a 5.000 euro e oltre dal decimo giorno dopo la presentazione; un credito IVA dell'anno già inserito a mano in Deleghe è adottato invece di essere duplicato;
- scadenze delle dichiarazioni nello scadenzario dello studio e indicatori in scrivania; fixture: esercizio, liquidazioni e comunicazioni 2025 di Alfa con la dichiarazione IVA presentata a credito con il visto, liquidazioni 2025 e dichiarazione IVA in bozza di Ferri (trimestrale, ventilazione, VT nel Lazio) con il saldo registrato, Poliambulatorio Sole (pro-rata con esenti occasionali, cessione di un bene ammortizzabile, VT in Lombardia ed Emilia-Romagna dal sezionale dell'ambulatorio di Piacenza, saldo a rate, visto del CAF), Omega Export (importazioni, quadro VC con il metodo mensile, società incorporata dal 1° luglio nel modulo 2, collegio sindacale), Biomedica Nord (esportatore abituale con operazioni esenti: pro-rata con il VF35 dagli acquisti con dichiarazione d'intento), Gialli Edilforniture (contabilità separate mensile e trimestrale con acquisti promiscui, un modulo per attività), gruppo IVA Verde (controllante, controllata mensile e trimestrale, ingresso di una controllata dal 2026: liquidazioni, acconto e comunicazioni del gruppo, quadri VK, VG e prospetto IVA 26 PR; modello IVA TR di gruppo del primo trimestre e rimborso di una quota di Verde Servizi), Omega Export con il modello IVA TR del terzo trimestre e il rimborso annuale.

#### `Contabilita` 0.3.0

- **tipologia dell'inversione contabile** sulle righe IVA degli acquisti (N6.1-N6.9: rottami, oro, subappalto edile, fabbricati, cellulari, prodotti elettronici, comparto edile, energia), proposta dall'importazione della fattura elettronica del fornitore e indicata nella prima nota (colonna "Tipologia RC"): determina il rigo del quadro VJ della dichiarazione IVA;
- **qualifica delle operazioni attive** sulle righe IVA (oro da investimento, esenti occasionali, n. 27-quinquies, beni ammortizzabili anche dal tipo documento TD26, esenti equiparate, non soggette senza detrazione), **aliquota teorica** degli acquisti con dichiarazione d'intento, casella **importazione** sulla testata degli acquisti e **unità locale** (sede operativa dell'Anagrafica) dei registri e sezionali IVA, per i quadri VF, VE, VC e VT della dichiarazione IVA;
- **attività con contabilità separata** (art. 36): attività con codice ATECO e periodicità propria, registri e sezionali assegnati alle attività, acquisti promiscui ripartiti riga per riga con le percentuali delle attività, liquidazioni distinte per attività e compensate nel periodo (con il pro-rata dell'attività), attività trimestrali di un'azienda mensile liquidate nel mese di fine trimestre con gli interessi dell'1%, comunicazione delle liquidazioni con il mese e il trimestre anticipato nello stesso modulo;
- **IVA di gruppo** (art. 73, c. 3; D.M. 13 dicembre 1979): opzione della controllante fino a revoca con le controllate (ingresso e uscita dal 1° gennaio, cessazione del controllo in corso d'anno, verifica del possesso sui legami "socio" dell'Anagrafica), liquidazioni delle società trasferite al gruppo senza versamento (giroconto sul nuovo conto "IVA di gruppo"), liquidazioni periodiche e acconto del gruppo con i versamenti della controllante in Deleghe, comunicazioni delle società con la partita IVA della controllante e comunicazione della liquidazione del gruppo, pagina "IVA di gruppo" dell'azienda;
- **trimestre separato dal mese**: le attività e le società trimestrali di un'azienda o di un gruppo mensile si possono liquidare a parte (codici 6031-6033, modulo distinto nella comunicazione, sequenza dei riporti mese, mese, mese, trimestre), scegliendo per ogni trimestre all'elaborazione del mese di fine trimestre; in alternativa resta il trimestre anticipato al mese;
- **credito chiesto con il modello IVA TR** escluso dal riporto al periodo successivo, annotato sulla liquidazione che chiude il trimestre e visibile nel prospetto;
- **pro-rata di detrazione provvisorio** dell'anno nella pagina delle liquidazioni (o registrato dalla dichiarazione IVA dell'anno precedente): l'IVA detraibile delle liquidazioni periodiche è ridotta alla percentuale, con l'avviso nel prospetto;
- descrizioni dei codici IVA N3.4 (operazioni assimilate e servizi internazionali, che concorrono al plafond) e N3.6 (altre operazioni non imponibili che non concorrono al plafond) allineate alle nature della fattura elettronica: prima N3.6 indicava i servizi internazionali, che vanno nel VE30 della dichiarazione.

#### `Telonio` 0.6.2

- il menu e la scrivania aprono l'applicativo Dichiarazioni fiscali (`/contribuente-dichiarativi/index`), con la descrizione aggiornata.

### ⚠️ Limiti Noti

- **File telematici**: i file "IVA26" e "IVT25" sono verificati nella struttura e nella lunghezza dei record, non ancora con il software di controllo dell'Agenzia delle Entrate;
- **Modello IVA TR**: alla presentazione il credito chiesto è escluso dal riporto, ma le liquidazioni successive già elaborate vanno rielaborate a mano;
- **Rimborso annuale**: causali 6, 7, 10, 11 e 13, codici di erogazione prioritaria per attività (2, 3, 4, 5, 7) e procedura semplificata (VX4, campo 2) si indicano a mano;
- **Rigo VS30**: stimato sui debiti trasferiti con le liquidazioni di gruppo dell'anno, senza il conguaglio del quarto trimestre: l'importo va controllato;
- **Da confermare**: il calcolo del VS30 e la prevalenza delle operazioni non soggette per il presupposto TD5 del modello IVA TR e la causale 5 sono letture delle istruzioni, non regole esplicite;
- **Redditi PF**: solo il catalogo dei campi; proposta e file telematico non sono ancora disponibili.

## [0.15.0-alpha] - 2026-11-01 - Comunicazioni IVA e stampe definitive

Quindicesima versione alpha: in `Contabilita` la comunicazione delle liquidazioni periodiche IVA, le stampe definitive numerate del giornale e dei registri IVA, i limiti della ventilazione dei corrispettivi e la verifica delle fatture elettroniche sullo schema ufficiale; backup del self-hosting anche solo su richiesta. Valgono le avvertenze della 0.1.0-alpha: la versione non è indicata per l'uso in produzione su dati reali.

Versioni dei moduli: `Contabilita` 0.2.0; `Telonio` 0.6.1, `Anagrafica` 0.6.0, `Deleghe` 0.5.0, `Imu` 0.3.2, `Successioni` 0.3.2 e `GestioneStudio` 0.1.1 invariati.

**Aggiornamento del database**: `sisma aggiorna-database` esegue `Contabilita/Database/aggiornamento-0.2.sql` (tabelle `comunicazione_lipe`, `modulo_lipe` e `stampa_definitiva`, colonne `riga_iva.altri_prodotti`, `liquidazione_iva.versamenti_auto` e `liquidazione_iva.crediti_imposta`); idempotente.

### ✨ Nuove Funzionalità

#### `Contabilita` 0.2.0

- **comunicazione delle liquidazioni periodiche IVA** (art. 21-bis D.L. 78/2010) per trimestre, preparata dalle liquidazioni elaborate: un modulo per mese dei mensili o uno per il trimestre dei trimestrali (quarto trimestre con il periodo "5", senza interessi né importo da versare), righi da VP2 a VP14 con i controlli delle specifiche (VP7 e VP8 solo nei periodi ammessi, VP2 con l'imponibile dei corrispettivi ventilati, VP13 con il metodo dell'acconto), dichiarante proposto dai legami dell'Anagrafica con il codice di carica, intermediario predefinito di Deleghe con l'impegno alla presentazione; file della fornitura IVP18 validato sullo schema ufficiale prima del download, stato di invio con data e protocollo, termine (30 settembre per il secondo trimestre, fine di febbraio per il quarto) con gli slittamenti;
- **versamenti auto UE e crediti d'imposta nella liquidazione IVA** (righi VP10 e VP11): l'IVA già versata con F24 elementi identificativi sulle prime cessioni interne di auto di provenienza UE (D.L. 262/2006) e i crediti d'imposta particolari scomputati dal versamento si indicano all'elaborazione e riducono l'importo da versare e il versamento in Deleghe; i crediti d'imposta non superano l'IVA da versare e non si usano nel quarto trimestre dei trimestrali;
- **stampe definitive** del libro giornale (per esercizio) e dei registri IVA (per anno solare) con le pagine numerate per anno: periodi contigui, impaginazione a righe fisse con i riporti del giornale, anteprima, ristampa identica e annullamento della sola ultima stampa; le registrazioni dei periodi stampati (comprese le integrazioni dell'inversione contabile nel registro delle vendite) non si creano, modificano o eliminano più;
- **limiti della ventilazione dei corrispettivi** verificati sui registri dell'anno precedente (artt. 4 e 5 D.M. 24 febbraio 1973): acquisti di "altri prodotti" non oltre il 50% del totale degli acquisti e importazioni, cessioni fatturate non oltre il 20% del volume d'affari, esclusi immobili e beni strumentali; prospetto nella pagina delle liquidazioni e avviso nella liquidazione se superati. La colonna "Riv." delle righe IVA degli acquisti distingue la rivendita dei prodotti ammessi da quella degli altri prodotti;
- **fatture elettroniche verificate sullo schema XSD ufficiale** (VFPR12 delle specifiche 1.9.1) all'importazione: i file non conformi si importano con l'avviso del primo errore;
- **schemi XSD ufficiali nel modulo** (`Contabilita/Application/Assets/xsd`, validazione senza rete) e comando `sisma specifiche-contabilita` per scaricarli di nuovo dalle fonti ufficiali;
- fixture: liquidazioni di febbraio (con versamenti auto UE e un credito d'imposta) e marzo, comunicazione del primo trimestre inviata, stampe definitive di gennaio del giornale e del registro delle vendite e fattura elettronica non conforme per Alfa; acquisto di altri prodotti, comunicazione del primo trimestre in bozza ed esercizio 2025 chiuso (base dei limiti della ventilazione) per Ferri.

#### Self-hosting

- **backup solo su richiesta**: con `BACKUP_SCHEDULE=off` (o `none`, `false`, senza distinzione di maiuscole) il servizio di backup non pianifica il dump notturno e lo esegue solo quando lanciato dall'host (`docker exec telonio_backup backup.sh`, ad esempio da un timer dell'host o prima di un intervento); la pulizia oltre la retention e le copie remote restano quelle del backup pianificato.

### 🐛 Correzioni

- **test delle fatture elettroniche falliti dopo il checkout su Windows**: con `core.autocrlf` Git convertiva i fine riga dei file XML di prova, che devono restare identici al contenuto firmato nelle buste `.p7m`; `.gitattributes` li esclude dalla conversione.

## [0.14.0-alpha] - 2026-11-01 - Contabilità

Quattordicesima versione alpha: nuovo applicativo `Contabilita`, contabilità in partita doppia e IVA delle aziende clienti, con l'importazione delle fatture elettroniche, i corrispettivi del mese in una sola maschera e la ventilazione dei corrispettivi. Valgono le avvertenze della 0.1.0-alpha: la versione non è indicata per l'uso in produzione su dati reali.

Versioni dei moduli: `Contabilita` 0.1.0 (nuovo), `Telonio` 0.6.1, `Anagrafica` 0.6.0; `Deleghe` 0.5.0, `Imu` 0.3.2, `Successioni` 0.3.2 e `GestioneStudio` 0.1.1 invariati.

### ✨ Nuove Funzionalità

#### `Contabilita` — contabilità in partita doppia

- **apertura della contabilità** del soggetto dell'Anagrafica (regime e periodicità IVA restano quelli dei suoi dati fiscali): copia del piano dei conti modello scelto, registri IVA di vendite, acquisti e corrispettivi, primo esercizio; esercizi successivi, chiusura (registrazioni non più modificabili) e riapertura;
- **piani dei conti** su tre livelli (mastro, conto, sottoconto): piano standard della suite con il raccordo alle voci degli schemi di bilancio degli artt. 2424 e 2425 c.c. (enum `VoceCee`), modelli dello studio copiati dalla suite e piano dell'azienda personalizzabile; le funzioni dei conti (clienti, fornitori, IVA, Erario c/IVA, banca, cassa, ricavi, costi, ...) guidano le proposte automatiche;
- **codici IVA** della suite (aliquote, nature N1-N7 della fattura elettronica, detraibilità, integrazione per inversione contabile, acquisti intracomunitari ed esteri, con la norma) e dello studio;
- **clienti e fornitori** delle aziende, separati per azienda, con i sottoconti del partitario aperti da soli;
- **prima nota** con testata, righe IVA e righe in partita doppia: "Proponi le righe" le compone dalla causale (vendite anche in scissione dei pagamenti, corrispettivi, acquisti con IVA indetraibile e integrazione dell'imposta, note di credito, incassi, pagamenti, versamenti IVA), controlli di quadratura e coerenza, numero progressivo nell'esercizio e protocollo IVA per registro e anno solare, sezionali;
- **ventilazione dei corrispettivi** (art. 24, c. 3, D.P.R. 633/1972; D.M. 24 febbraio 1973) per le aziende con l'opzione nei dati fiscali: corrispettivi da ventilare senza aliquota (codice `VEN`) nella maschera mensile, acquisti per rivendita segnati nelle righe IVA (proposti anche dalle fatture elettroniche), ripartizione nella liquidazione con i rapporti progressivi degli acquisti dal 1° gennaio e scorporo dell'imposta, prospetto della ventilazione, storno dell'imposta dai ricavi nel giroconto, ventilazione annuale con la differenza da riportare nella dichiarazione;
- **corrispettivi del mese in una sola maschera**: griglia dei giorni per codice IVA con gli importi comprensivi dell'imposta, salvata come unica registrazione mensile (art. 6, c. 4, D.P.R. 695/1996) con l'imposta scorporata dal totale di ciascuna aliquota (art. 27, c. 4, D.P.R. 633/1972) e il dettaglio giornaliero riportato nella stampa del registro dei corrispettivi (art. 24); salvare di nuovo il mese aggiorna la registrazione, azzerarlo la elimina;
- **liquidazioni IVA** mensili e trimestrali dai registri per data di competenza, con riporto del credito, debito non superiore a 100 euro rinviato (D.Lgs. 1/2024), credito annuale compensato, interessi dell'1% dei trimestrali, scomputo dell'acconto; il versamento (6001-6012, 6031-6033, 6099 per il quarto trimestre) è registrato nell'archivio di `Deleghe` e il giroconto in prima nota è facoltativo; **acconto IVA** con i metodi storico, previsionale e analitico (6013/6035, entro il 27 dicembre);
- **fatture elettroniche**: caricamento di file XML, firmati `.p7m` (DER o base64, anche BER a lunghezza indefinita) e archivi ZIP del portale Fatture e corrispettivi; direzione riconosciuta dalla partita IVA dell'azienda, controparti create, doppioni ignorati, avvisi (divisa, esigibilità differita, totali, integrazioni TD16); proposta della registrazione da confermare, con il codice di integrazione per gli acquisti in inversione contabile;
- **stampe**: registri IVA per periodo con i totali per codice e le integrazioni, prospetto della liquidazione, libro giornale, schede contabili (anche dei clienti e fornitori) e bilancio di verifica con il risultato del periodo;
- `sisma tabelle-contabilita` aggiorna piani dei conti modello e codici IVA della suite dalle sorgenti del modulo (`Contabilita/Database/sorgenti`); fixture demo che coprono tutte le funzioni (Alfa Consulting S.r.l. in contabilità ordinaria con vendite anche in scissione dei pagamenti, note di credito, inversione contabile, incassi, sezionale, fattura elettronica da registrare, liquidazione e acconto; il negozio "Alimentari Ferri" con la ventilazione, i corrispettivi mensili e la liquidazione trimestrale); indicatori in scrivania.

#### `Anagrafica`

- **ventilazione dei corrispettivi** nei dati fiscali storicizzati dei titolari di partita IVA con un regime che prevede le liquidazioni IVA, mostrata nella scheda del soggetto; la usa la liquidazione IVA della Contabilità.

#### `Telonio`

- il menu e la scrivania aprono l'applicativo Contabilità (`ModuloSuite::contabilita`, `/azienda/index`), con la descrizione aggiornata.

### 🔄 Aggiornamento da 0.13.0-alpha

`sh aggiorna.sh` (con `sudo` se la cartella dell'installazione appartiene a root): `sisma aggiorna-database` esegue `Anagrafica/Database/aggiornamento-0.6.sql` (idempotente, aggiunge a `dati_fiscali` la colonna `ventilazione_corrispettivi`) e installa il nuovo modulo con `Contabilita/Database/schema.sql` e `Contabilita/Database/dati-riferimento.sql`.

## [0.13.0-alpha] - 2026-10-30 - Delega Consegnata al Cliente

Tredicesima versione alpha: in `Deleghe` la delega che il cliente paga da sé si registra come consegnata, con data e canale della consegna, e si quietanza con il canale del pagamento; non entra più nelle forniture telematiche dello studio. Valgono le avvertenze della 0.1.0-alpha: la versione non è indicata per l'uso in produzione su dati reali.

Versioni dei moduli: `Deleghe` 0.5.0; `Telonio` 0.6.0, `Anagrafica` 0.5.1, `Imu` 0.3.2, `Successioni` 0.3.2 e `GestioneStudio` 0.1.1 invariati.

### ✨ Nuove Funzionalità

#### `Deleghe` — delega consegnata al cliente

- **nuovo stato "Consegnata"** per le deleghe che il cliente paga da sé (home banking, banca, posta), alternativo a "Trasmessa": dalla delega confermata "Consegna al cliente" registra la data e il canale della consegna (a mano, email/PEC, area clienti, altro). La delega consegnata non entra più nelle forniture telematiche dello studio;
- **quietanza del pagamento del cliente**: dalla delega consegnata "Registra quietanza" chiede, oltre alla data e al riferimento facoltativo (es. CRO), il canale del pagamento (home banking, banca/sportello, posta, altro). Il percorso è lineare, `confermata → consegnata → quietanzata`; "Annulla quietanza" torna a "consegnata" e "Annulla consegna" a "confermata", cancellando i dati registrati;
- consegna e quietanza, con canale, sono mostrate nella delega; nello scadenzario una data è completata anche quando i versamenti sono in deleghe consegnate al cliente.

### 🔄 Aggiornamento da 0.12.0-alpha

`sh aggiorna.sh` (con `sudo` se la cartella dell'installazione appartiene a root): `sisma aggiorna-database` esegue `Deleghe/Database/aggiornamento-0.5.sql` (idempotente), che aggiunge a `delega` le colonne `data_consegna`, `canale_consegna` e `canale_pagamento`.

## [0.12.0-alpha] - 2026-10-30 - Scelta dei Versamenti da Mettere in Delega

Dodicesima versione alpha: in `Deleghe` si scelgono i versamenti di una data da mettere in delega (generazione, nuova delega, aggiunta alla delega in bozza), quelli non scelti restano da versare, e "Togli dalla delega" è distinto da "Elimina". Valgono le avvertenze della 0.1.0-alpha: la versione non è indicata per l'uso in produzione su dati reali.

Versioni dei moduli: `Deleghe` 0.4.0; `Telonio` 0.6.0, `Anagrafica` 0.5.1, `Imu` 0.3.2, `Successioni` 0.3.2 e `GestioneStudio` 0.1.1 invariati.

### ✨ Nuove Funzionalità

#### `Deleghe` — scelta dei versamenti da mettere in delega

- **"Genera la delega"** nella scheda del contribuente inserisce solo i versamenti spuntati della data (di default tutti): gli altri restano da versare, per pagarli in un'altra data o ravvederli dopo la scadenza. Prima la delega riceveva per forza tutti i versamenti della data;
- **"Nuova delega"** elenca i versamenti da versare della data scelta, con la spunta (di default su tutti), e inserisce solo quelli spuntati; se nel form si cambia la data, i versamenti della nuova data si scelgono dalla delega con "Aggiungi alla delega";
- **"Aggiungi alla delega"** nella delega in bozza elenca i versamenti da versare della stessa data rimasti fuori e aggiunge solo quelli scelti (non preselezionati, perché di solito sono stati lasciati fuori apposta), invece di aggiungerli tutti.

### 🔧 Modifiche

#### `Deleghe`

- nella delega "Togli dalla delega" (il versamento torna da versare) è un pulsante con la sua etichetta invece della sola icona accanto al cestino, e la conferma di "Elimina" avverte che il versamento viene cancellato dall'archivio e non torna da versare.

### 🔄 Aggiornamento da 0.11.0-alpha

Nessuna modifica al database: `sh aggiorna.sh` (con `sudo` se la cartella dell'installazione appartiene a root).

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
