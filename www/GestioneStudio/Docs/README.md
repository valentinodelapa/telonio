# Modulo GestioneStudio

Gestione dello studio: scadenzario con le scadenze di tutti gli
applicativi della suite, calendario fiscale degli adempimenti e
adempimenti a cui è tenuto ciascun cliente, con il responsabile e lo
stato di lavorazione.

## Flusso di lavoro

1. **Calendario degli adempimenti** (`/adempimento/index`): gli
   adempimenti del calendario fiscale comune a tutti gli studi (versamenti
   IVA e ritenute, LIPE, dichiarazioni dei redditi, IVA, IRAP e 770,
   Certificazione Unica, acconti, contributi IVS, bollo sulle fatture
   elettroniche, Intrastat, ...) con i termini effettivi dell'anno; lo
   studio aggiunge i propri adempimenti (scadenze interne) e le proroghe.
2. **Assegnazione ai clienti**: dall'adempimento ("Assegna ai clienti",
   con i clienti proposti in base all'Anagrafica) o dalla scheda del
   cliente, con la data da cui l'adempimento è dovuto e il responsabile.
3. **Scadenzario** (`/scadenzario/index`, ingresso del modulo): le
   scadenze del periodo di tutti gli applicativi, per stato, responsabile
   e applicativo, raggruppate per mese; da ogni riga si segna la scadenza
   come completata, la si riapre o se ne registrano stato, responsabile e
   note.
4. **Scheda del cliente** (`/scadenzario/soggetto/soggetto/<id>`, anche
   dal pulsante "Scadenze" della scheda anagrafica): adempimenti a cui è
   tenuto e scadenze dell'anno di tutti gli applicativi.

A differenza degli altri moduli, che si aprono dal contribuente, la vista
principale è dello studio: lo scadenzario serve a organizzare il lavoro
di tutti gli operatori nel periodo.

## Installazione

Il modulo è registrato in `MODULE_FOLDERS`. Script da eseguire come root
sullo schema del progetto, dopo quelli di `CommSuite` e `Anagrafica`:

1. `GestioneStudio/Database/schema.sql`
2. `GestioneStudio/Database/dati-riferimento.sql` (calendario fiscale:
   necessario anche in produzione, vedi "Calendario fiscale")

Non richiede gli altri applicativi: ne raccoglie le scadenze se sono
installati (vedi "Scadenze degli altri moduli").

Le fixture del modulo (`sisma fixtures`) assegnano adempimenti del
calendario ai soggetti demo dell'Anagrafica (IVA mensile e dichiarazione
dei redditi di Alfa; IVA trimestrale, contributi IVS e saldo e acconto
delle imposte di Verdi, soggetto ISA, con la proroga del 2026;
dichiarazione dei redditi di Rossi) e creano un adempimento dello studio
(consegna dei documenti per la dichiarazione) assegnato a Rossi;
richiedono il calendario fiscale (punto 2).

## Modello dei dati

| Entità | Contenuto | Storicizzata |
|---|---|---|
| `Adempimento` | codice, denominazione, categoria (`CategoriaAdempimento`), soggetti a cui si propone (`AmbitoAdempimento`), fonte normativa, slittamento dei termini festivi, differimento di agosto, attivo sì/no. Senza studio è del calendario fiscale (sola lettura), con lo studio è proprio dello studio | no |
| `TermineAdempimento` | termine per periodo dell'anno di riferimento: periodo (es. gennaio, 1° trimestre, anno), giorno (0 = ultimo del mese), mese, anni dopo l'anno di riferimento | no |
| `ProrogaAdempimento` | nuovo termine per un anno di riferimento, per tutti o per i soli soggetti con i requisiti (`CondizioneProroga`), con il provvedimento e le note; senza studio del calendario fiscale, con lo studio inserita dallo studio (prevale) | no |
| `AssegnazioneAdempimento` | adempimento a cui è tenuto il soggetto, con il responsabile (operatore dello studio) e le note | sì |
| `LavorazioneScadenza` | stato di lavorazione (`StatoLavorazione`), responsabile, note, data e autore del completamento di una scadenza di qualsiasi modulo, collegata a modulo e riferimento | no |

### Enum o entità

Categorie, ambiti e stati di lavorazione sono **enum**: vocabolari chiusi
con comportamento (l'ambito decide la proposta ai clienti, lo stato se la
scadenza è aperta). Gli adempimenti sono **entità**: il calendario è
ampio, cambia nel tempo e lo studio vi aggiunge i propri.

## Termini

Ogni termine si riferisce a un **anno di riferimento** A (il periodo
d'imposta o l'anno della liquidazione): cade il giorno e mese indicati
dell'anno A più gli anni successivi (es. IVA mensile di dicembre: 16
gennaio dell'anno dopo; dichiarazione dei redditi: 31 ottobre dell'anno
dopo). Il termine effettivo (`Services\CalendarioAdempimenti`) è:

1. il termine ordinario, o la proroga dell'anno (dello studio, altrimenti
   del calendario fiscale; le condizionate solo per i soggetti con i
   requisiti, vedi "Proroghe");
2. se l'adempimento lo prevede, differito al 20 agosto quando cade dal 1°
   al 20 agosto (art. 37, c. 11-bis, D.L. 223/2006);
3. se cade di sabato o in un giorno festivo, spostato al primo giorno
   lavorativo successivo (art. 7, c. 1, lett. h, D.L. 70/2011).

Il calcolo dei giorni lavorativi è comune a tutta la suite
(`CommSuite\HelperClasses\Calendario::termineFiscale`), con le festività
nazionali compreso il 4 ottobre dal 2026 (L. 151/2025).

## Proroghe

Una proroga vale per tutti o solo per i soggetti con i requisiti della
condizione (`Enumerations\CondizioneProroga`), verificati da
`Services\RequisitiProroga` sui dati fiscali dell'Anagrafica in vigore alla
fine dell'anno di riferimento:

- **soggetti ISA**: applicano gli ISA (anche con cause di esclusione), o
  sono in regime forfetario o di vantaggio; oppure partecipano, come soci o
  collaboratori dell'impresa familiare, a una società di persone, a una
  società di capitali in trasparenza o a un'impresa familiare con questi
  requisiti (artt. 5, 115 e 116 TUIR).

In ordine di precedenza valgono prima le proroghe dello studio, poi quelle
del calendario fiscale, e per ciascuno prima le condizionate. Nel
calendario dello studio il termine effettivo è quello generale; le
proroghe condizionate compaiono con il loro badge e si applicano nelle
scadenze dei clienti.

Proroghe di legge nel calendario fiscale (`Database/sorgenti/proroghe.csv`):

| Adempimento | Anno | Termine | Soggetti | Provvedimento |
|---|---|---|---|---|
| saldo e primo acconto delle imposte (anche IRAP e IVA da dichiarazione) | 2025 | 20/07/2026 (o 20/08/2026 con lo 0,80%) | ISA | art. 1-sexies, c. 1 e 2, D.L. 63/2026, conv. L. 113/2026 |
| diritto annuale della Camera di commercio | 2026 | 20/07/2026 (o 20/08/2026 con lo 0,80%) | ISA | idem (segue il termine del primo acconto) |

Le proroghe degli anni precedenti al 2025 non sono riportate.

## Adempimenti dei clienti

L'assegnazione è storicizzata come i dati dell'Anagrafica
(`Anagrafica\Services\StoricoService`): l'adempimento è dovuto dalla
decorrenza fino alla fine della validità e valgono i termini effettivi
che vi cadono. Per una cessazione la validità si chiude dopo l'ultimo
adempimento dovuto (es. la dichiarazione dell'anno di cessazione). Un
nuovo responsabile da una data è una variazione: la versione precedente
si chiude il giorno prima. Le assegnazioni e le lavorazioni di un soggetto
si eliminano con il soggetto.

La proposta dei clienti (`AmbitoAdempimento::isProponibile`) usa i dati
dell'Anagrafica: tipo di soggetto e, dai dati fiscali in vigore alla
decorrenza, partita IVA, attività d'impresa, regime fiscale, periodicità
IVA e qualifica di sostituto d'imposta (es. IVA mensile solo ai mensili,
LIPE e dichiarazione IVA non ai forfetari, ritenute, CU e 770 ai sostituti
d'imposta, 730 alle persone fisiche senza partita IVA); ai deceduti e ai
cessati non propone nulla. Gli adempimenti che i dati non permettono di
riconoscere (es. Intrastat) si assegnano a richiesta. La proposta va
sempre confermata.

## Scadenze degli altri moduli

Il modulo non dipende dagli applicativi di cui mostra le scadenze: il
contratto è in CommSuite. Ogni modulo che ha scadenze le espone con la
classe `<Modulo>\Application\Services\ScadenzeModulo`, che implementa
`CommSuite\Application\Interfaces\FonteScadenze`;
`CommSuite\Application\Services\RaccoltaScadenze` le raccoglie dai moduli
disponibili. Un modulo futuro (es. contabilità, dichiarazioni, parcelle)
entra nello scadenzario solo dichiarando la sua fonte, senza modificare
questo modulo.

```php
class ScadenzeModulo implements FonteScadenze
{
    public function __construct(private DataMapper $dataMapper = new DataMapper()) {}

    public function getScadenze(Studio $studio, SismaDate $dal, SismaDate $al, ?BaseEntity $soggetto = null): array
    {
        return [new Scadenza(
            modulo: ModuloSuite::imu,
            riferimento: 'dichiarazione-' . $dichiarazione->id,  // stabile anche se la data cambia
            data: Calendario::termineFiscale($termine, false),     // termine effettivo
            descrizione: 'Dichiarazione IMU 2025 - ROMA',
            soggetto: $dichiarazione->soggetto,
            url: '/dichiarazione-imu/show/dichiarazione/' . $dichiarazione->id,
            completata: $dichiarazione->stato === StatoDichiarazione::presentata,
        )];
    }
}
```

Le scadenze si calcolano al momento dai dati del modulo, che resta l'unica
fonte: lo scadenzario conserva solo lo stato di lavorazione
(`LavorazioneScadenza`, collegata a modulo e riferimento), così una
proroga o una modifica nel modulo si riflette subito senza disallineamenti.
Una scadenza che il modulo considera eseguita (`completata`) risulta
completata e il suo stato si gestisce nel modulo.

Fonti attuali:

| Modulo | Scadenze | Completata quando |
|---|---|---|
| `GestioneStudio` | termini degli adempimenti assegnati ai clienti | stato registrato nello scadenzario |
| `Deleghe` | versamenti dell'archivio del contribuente (anche quelli registrati da IMU e Successioni) raggruppati per data di scadenza | tutti i versamenti sono in deleghe trasmesse o quietanzate, o regolarizzati con ravvedimento |
| `Imu` | dichiarazioni IMU registrate, termine al 30 giugno dell'anno successivo | dichiarazione presentata |
| `Successioni` | dichiarazioni di successione, termine di dodici mesi dall'apertura (art. 31 TUS), riferite al defunto | dichiarazione presentata |

Gli indicatori della scrivania (`Services\RiepilogoScrivania`) contano le
scadenze ancora aperte: scadute nell'ultimo anno, entro 7 ed entro 30
giorni.

## Calendario fiscale

L'Agenzia delle Entrate pubblica lo scadenzario fiscale solo come pagine
web, senza un formato da importare: adempimenti, termini e proroghe di
legge sono curati nelle sorgenti del modulo (`Database/sorgenti`), con la
norma che fissa ciascun termine:

- `adempimenti.csv`: id stabile (sotto 1000), codice, denominazione,
  categoria e ambito (valori degli enum), differimento di agosto, fonte;
- `termini.csv`: termini per periodo; l'id è adempimento × 100 + ordine;
- `proroghe.csv`: nuovo termine per adempimento, ordine e anno di
  riferimento, con i soggetti a cui si applica (valore di
  `CondizioneProroga`), il provvedimento e le note (vedi "Proroghe").

Gli id stabili permettono di aggiornare il calendario senza toccare le
assegnazioni dei clienti; gli adempimenti propri degli studi partono da
1000 e i loro termini da 100000.

```bash
# aggiorna il database dalle sorgenti (gli adempimenti tolti restano non attivi,
# i dati degli studi non sono toccati)
docker exec comm_suite_php sh -c 'cd /var/www/html && sisma calendario-fiscale'

# rigenera lo script SQL versionato nel modulo
docker exec comm_suite_php sh -c 'cd /var/www/html && sisma calendario-fiscale --sql=GestioneStudio/Database/dati-riferimento.sql'
```

Il comando si ferma senza modificare nulla se una sorgente ha
un'intestazione diversa, un valore non valido (id, codice, categoria,
ambito, data) o un adempimento senza fonte o senza termini.

## Multi-studio

Assegnazioni, lavorazioni, adempimenti e proroghe dello studio
appartengono allo studio; il calendario fiscale è comune e in sola
lettura. I controller verificano l'accesso ai soggetti con
`Anagrafica\Permissions\SoggettoPermission` e agli adempimenti con
`GestioneStudioController::autorizzaAdempimento`; il responsabile deve
essere un operatore dello studio.

## Da completare

- attività, pratiche e tempi degli operatori per il controllo di gestione
  (base per il modulo Parcelle e fatture);
- esercizi non coincidenti con l'anno solare per le dichiarazioni delle
  società e degli enti;
- scadenze dei moduli futuri (contabilità, dichiarazioni, bilancio,
  parcelle) tramite le rispettive `ScadenzeModulo`;
- promemoria via e-mail ai responsabili.
