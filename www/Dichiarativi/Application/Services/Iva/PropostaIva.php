<?php

namespace Dichiarativi\Application\Services\Iva;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeDetrazioneIva;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Anagrafica\Application\Models\IndirizzoModel;
use Contabilita\Application\Entities\AccontoIva;
use Contabilita\Application\Entities\AttivitaIva;
use Contabilita\Application\Entities\ComunicazioneLipe;
use Contabilita\Application\Entities\LiquidazioneIva;
use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Enumerations\EsigibilitaIva;
use Contabilita\Application\Enumerations\NaturaIva;
use Contabilita\Application\Enumerations\QualificaOperazione;
use Contabilita\Application\Enumerations\StatoComunicazioneLipe;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\AccontoIvaModel;
use Contabilita\Application\Models\ComunicazioneLipeModel;
use Contabilita\Application\Models\LiquidazioneIvaModel;
use Contabilita\Application\Models\RegistroIvaModel;
use Contabilita\Application\Models\RigaContabileModel;
use Contabilita\Application\Models\RigaIvaModel;
use Contabilita\Application\Services\AttivitaSeparate;
use Contabilita\Application\Services\CalcoloLiquidazioneIva;
use Contabilita\Application\Services\Lipe\PreparazioneLipe;
use Contabilita\Application\Services\RegimeIva;
use Contabilita\Application\Services\VentilazioneCorrispettivi;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Models\CreditoModel;
use Deleghe\Application\Models\IntermediarioModel;
use Deleghe\Application\Models\VersamentoModel;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Entities\ModuloDichiarazione;
use Dichiarativi\Application\Entities\RettificaDetrazione;
use Dichiarativi\Application\Enumerations\TipoModulo;
use Dichiarativi\Application\Models\ModuloDichiarazioneModel;
use Dichiarativi\Application\Models\RettificaDetrazioneModel;
use Dichiarativi\Application\Models\SottoscrizioneControlloModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Proposta della dichiarazione IVA annuale dai dati della Contabilità
 * dell'anno (istruzioni e specifiche tecniche del modello IVA 2026), per
 * modulo: il modulo 1 dal contribuente, i moduli dei soggetti partecipanti
 * a operazioni straordinarie dalla loro contabilità, quelli delle attività
 * separate e della prima frazione d'anno compilati a mano (con i totali
 * ricalcolati).
 *
 * - VA: codice dell'attività (ATECO dei dati fiscali di fine anno), VA1
 *   per i soggetti trasformati, VA3 per la prima frazione d'anno;
 * - VE: righe IVA dei registri delle vendite e dei corrispettivi con data
 *   di competenza nell'anno, imponibili per aliquota (VE20-VE23) con
 *   l'imposta calcolata sull'imponibile arrotondato e la differenza con
 *   l'imposta dei registri nel VE25; corrispettivi da ventilare ripartiti
 *   con la ventilazione annuale; operazioni non imponibili, esenti, non
 *   soggette e in inversione contabile per natura (VE30-VE35); scissione
 *   dei pagamenti nel VE38 ed esigibilità differita nel VE37 (fuori dalle
 *   sezioni 1 e 2); volume d'affari VE50;
 * - VF: acquisti per aliquota con la sola quota detraibile (VF1-VF13; la
 *   quota di imponibile corrispondente all'imposta indetraibile nel VF21),
 *   acquisti senza imposta per natura (VF17-VF19), acquisti
 *   intracomunitari (VF28), ripartizione VF29 (beni ammortizzabili dai
 *   conti delle immobilizzazioni, beni per la rivendita); con operazioni
 *   esenti, pro-rata di detrazione (VF30 casella 3, VF34, VF37) o sole
 *   operazioni esenti (VF32); IVA ammessa in detrazione VF71;
 * - VJ: imposta delle integrazioni degli acquisti in inversione contabile
 *   (intracomunitari VJ9, da non residenti VJ3, interni nel rigo della
 *   tipologia N6 indicata sulla riga IVA);
 * - VH: solo per i periodi la cui comunicazione delle liquidazioni
 *   periodiche non risulta inviata (le liquidazioni come nel rigo VP14);
 * - VL: IVA dovuta o a credito, credito dell'anno precedente dall'archivio
 *   dei crediti di Deleghe (codice 6099) e suo utilizzo in F24, interessi
 *   trimestrali, crediti d'imposta e versamenti auto UE delle liquidazioni,
 *   IVA periodica dovuta (VP14 e acconto) e versata (F24 trasmessi o
 *   quietanzati con i codici delle liquidazioni e dell'acconto), VL32 e
 *   VL33 con le formule del paragrafo 2.6 delle specifiche per uno o più
 *   moduli, interessi annuali dei trimestrali (VL36);
 * - VT (modulo 1): operazioni imponibili verso consumatori finali (senza
 *   partita IVA o corrispettivi) e soggetti IVA, con la ripartizione
 *   regionale nel luogo di esercizio dell'attività;
 * - VC (modulo 1, esportatori abituali): plafond utilizzato, volume
 *   d'affari ed esportazioni per mese dell'anno e del precedente, plafond
 *   disponibile con il metodo solare;
 * - VX (modulo 1): IVA da versare o a credito (paragrafo 2.9), credito
 *   interamente da riportare (VX5) salvo la richiesta di rimborso.
 *
 * I valori inseriti a mano non sono sovrascritti e i totali si calcolano
 * sui valori effettivi.
 */
class PropostaIva
{

    /** Quadri proposti in ogni modulo */
    public const QUADRI = ['VA', 'VE', 'VF', 'VJ', 'VH', 'VL'];

    /** Quadri proposti solo nel modulo 1 */
    public const QUADRI_PRIMO_MODULO = ['VT', 'VC', 'VX', 'VK', 'VG', 'VS', 'VV', 'VW', 'VY', 'VO'];

    /** Righi della sezione 2 del quadro VE per aliquota */
    public const RIGHI_VE = ['4' => 20, '5' => 21, '10' => 22, '22' => 23];

    /** Righi VE1-VE11 per percentuale di compensazione; i righi VF39-VF49 sono gli stessi più 38 */
    public const RIGHI_COMPENSAZIONE = ['2' => 1, '4' => 2, '6.4' => 3, '7' => 4, '7.3' => 5, '7.5' => 6, '8.3' => 7, '8.5' => 8, '8.8' => 9, '10' => 10, '12.3' => 11];

    /** Righi VF1-VF13 per aliquota o percentuale di compensazione */
    public const RIGHI_VF = ['2' => 1, '4' => 2, '5' => 3, '6.4' => 4, '7' => 5, '7.3' => 6, '7.5' => 7, '8.3' => 8, '8.5' => 9, '8.8' => 10, '10' => 11, '12.3' => 12, '22' => 13];

    /** Campi del quadro VE per natura delle operazioni attive */
    public const NATURE_VE = [
        'N3.1' => 'VE030002', 'N3.2' => 'VE030003', 'N3.3' => 'VE030004', 'N3.4' => 'VE030005',
        'N3.5' => 'VE031001', 'N3.6' => 'VE032001', 'N4' => 'VE033001', 'N2.1' => 'VE034001',
        'N6.1' => 'VE035002', 'N6.2' => 'VE035003', 'N6.3' => 'VE035004', 'N6.4' => 'VE035005',
        'N6.5' => 'VE035006', 'N6.6' => 'VE035007', 'N6.7' => 'VE035008', 'N6.8' => 'VE035009', 'N6.9' => null,
    ];

    /** Campi del quadro VF per natura degli acquisti senza imposta */
    public const NATURE_VF = [
        'N3.5' => 'VF017001', 'N3.1' => 'VF018001', 'N3.2' => 'VF018001', 'N3.3' => 'VF018001', 'N3.4' => 'VF018001', 'N3.6' => 'VF018001',
        'N2.1' => 'VF018001', 'N5' => 'VF018001', 'N7' => 'VF018001', 'N4' => 'VF018002', 'N2.2' => 'VF019001',
    ];

    /** Righi del quadro VJ per tipologia dell'inversione contabile interna */
    public const RIGHI_VJ = ['N6.1' => 6, 'N6.2' => 7, 'N6.3' => 12, 'N6.4' => 13, 'N6.5' => 14, 'N6.6' => 15, 'N6.7' => 16, 'N6.8' => 17];

    /** Operazioni che concorrono al plafond e al volume d'affari degli esportatori (VC, colonne 4 e 6) */
    public const NATURE_PLAFOND = ['N3.1', 'N3.2', 'N3.3', 'N3.4'];

    /** Codici tributo dei versamenti periodici e dell'acconto (rigo VL30, campo 3) */
    public const CODICI_VERSAMENTI_PERIODICI = ['6001', '6002', '6003', '6004', '6005', '6006', '6007', '6008', '6009', '6010', '6011', '6012', '6031', '6032', '6033', '6013', '6035'];

    /** @var string[] */
    private array $avvisi = [];

    /** @var array{imponibile: float, imposta: float, consumatoriImponibile: float, consumatoriImposta: float} ripartizione per il VT */
    private array $vt;

    /** @var array<int, bool> moduli proposti da un contribuente trimestrale */
    private array $trimestrali = [];

    /** @var array<int, array{imponibile: float, imposta: float, predefinita: bool}> IVA non assolta sugli acquisti con plafond per modulo (VF35) */
    private array $ivaNonAssolta = [];

    /** IVA dovuta del quarto trimestre delle attività separate trimestrali (VL36) */
    private float $debitoQuartoTrimestre = 0.0;

    /** Qualifiche delle operazioni attive: campo del quadro VF34 */
    public const CAMPI_QUALIFICA = ['O' => 'VF034001', 'E' => 'VF034002', 'Q' => 'VF034003', 'P' => 'VF034007', 'F' => 'VF034008'];

    /** Aliquota dell'IVA non assolta sugli acquisti con plafond senza aliquota teorica */
    public const ALIQUOTA_TEORICA = 22.0;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Propone i quadri di tutti i moduli e salva la dichiarazione con gli
     * avvisi.
     *
     * @param ModuloDichiarazione[]|null $moduli moduli oltre al primo (di default quelli salvati)
     * @return string[] avvisi
     */
    public function proponi(Dichiarazione $dichiarazione, ?QuadriDichiarazione $quadri = null, ?array $moduli = null): array
    {
        $this->avvisi = [];
        $this->vt = ['imponibile' => 0.0, 'imposta' => 0.0, 'consumatoriImponibile' => 0.0, 'consumatoriImposta' => 0.0, 'regioni' => []];
        $this->trimestrali = [];
        $this->ivaNonAssolta = [];
        if (($quadri === null) && ($moduli === null)) {
            (new ModuliAttivita($this->dataMapper))->sincronizza($dichiarazione);
        }
        $quadri ??= new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $moduli ??= isset($dichiarazione->id) ? (new ModuloDichiarazioneModel($this->dataMapper))->getModuli($dichiarazione) : [];
        // attività separate: la prima è il modulo 1, le altre hanno un modulo collegato
        $attivita = (new AttivitaSeparate($this->dataMapper))->getAttivita($dichiarazione->soggetto, $dichiarazione->anno);
        $this->debitoQuartoTrimestre = $this->getDebitoQuartoTrimestre($dichiarazione, $attivita);
        $elenco = [1 => ['tipo' => TipoModulo::principale, 'fonte' => $dichiarazione->soggetto, 'operazione' => null, 'attivita' => count($attivita) === 0 ? null : reset($attivita)]];
        foreach ($moduli as $modulo) {
            $elenco[$modulo->numero] = ['tipo' => $modulo->tipo, 'fonte' => $modulo->attivitaIva !== null ? $dichiarazione->soggetto : ($modulo->tipo->hasFonte() ? $modulo->soggettoFonte : null),
                'operazione' => $modulo->dataOperazione, 'attivita' => $modulo->attivitaIva];
        }
        if ((count($attivita) > 1) && (count(array_filter($moduli, fn(ModuloDichiarazione $modulo) => $modulo->attivitaIva !== null)) !== count($attivita) - 1)) {
            $this->avvisa('Attività separate: i moduli non corrispondono alle attività della Contabilità, riproporre la dichiarazione per crearli.');
        }
        ksort($elenco);
        // moduli non più presenti; i quadri VG e VS successivi al primo hanno lo stesso numero ma non sono moduli
        foreach (array_diff($quadri->getModuli(), array_keys($elenco)) as $soppresso) {
            $quadri->eliminaModulo($soppresso, 'VG', 'VS');
        }
        foreach ($elenco as $numero => ['tipo' => $tipo, 'fonte' => $fonte, 'operazione' => $operazione, 'attivita' => $singola]) {
            $quadri->usaModulo($numero);
            $quadri->avviaProposta(...($numero === 1 ? array_merge(self::QUADRI, self::QUADRI_PRIMO_MODULO) : self::QUADRI));
            $etichetta = ($numero === 1 ? '' : 'Modulo ' . $numero . ': ') . ($singola === null ? '' : 'attività ' . $singola->getEtichetta() . ', ');
            $this->proponiRettifiche($quadri, $dichiarazione, $numero, $singola, $etichetta);
            if ($fonte !== null) {
                $this->proponiDaContabilita($quadri, $dichiarazione, $fonte, $tipo, $etichetta, $operazione, $singola);
            } else {
                if ($tipo === TipoModulo::primaFrazione) {
                    $quadri->proponi('VA003001', true);
                }
                $this->proponiTotaliVe($quadri, null);
                $this->proponiTotaliVf($quadri, null, 0.0, 0.0);
                $this->proponiTotaliVj($quadri);
                $this->proponiProrata($quadri, $etichetta);
                $this->proponiImpostaAnnuale($quadri);
                $this->avvisa($etichetta . 'modulo da compilare a mano (' . mb_strtolower($tipo->getLabel()) . '): i totali sono ricalcolati dai righi inseriti.');
            }
        }
        $this->proponiRisultatiModuli($quadri, $dichiarazione, array_keys($elenco));
        $quadri->usaModulo(1);
        $this->proponiOperazioniVt($quadri, $dichiarazione, array_keys($elenco));
        $this->proponiPlafond($quadri, $dichiarazione);
        $this->proponiRisultato($quadri, $dichiarazione, array_keys($elenco));
        $this->proponiVisto($quadri, $dichiarazione);
        array_push($this->avvisi, ...(new OpzioniAgricole($this->dataMapper))->proponi($quadri, $dichiarazione->soggetto, $dichiarazione->anno));
        array_push($this->avvisi, ...$quadri->getDifferenzeManuali());
        $quadri->salva();
        $dichiarazione->dataElaborazione = new SismaDateTime();
        $dichiarazione->avvisi = count($this->avvisi) > 0 ? implode("\n", $this->avvisi) : null;
        $this->dataMapper->save($dichiarazione);
        return $this->avvisi;
    }

    /**
     * Attività separate trimestrali di un'azienda che liquida mensilmente:
     * IVA dovuta del quarto trimestre, che si versa con la dichiarazione
     * con gli interessi dell'1% (rigo VL36).
     *
     * @param AttivitaIva[] $attivita
     */
    private function getDebitoQuartoTrimestre(Dichiarazione $dichiarazione, array $attivita): float
    {
        if (count($attivita) === 0) {
            return 0.0;
        }
        $regime = new RegimeIva($this->dataMapper);
        try {
            if ($regime->getPeriodicita($dichiarazione->soggetto, $dichiarazione->anno) !== PeriodicitaIva::mensile) {
                return 0.0;
            }
            $azienda = $regime->getPeriodicitaAzienda($dichiarazione->soggetto, $dichiarazione->anno);
        } catch (\DomainException) {
            return 0.0;
        }
        $separate = new AttivitaSeparate($this->dataMapper);
        $calcolo = new CalcoloLiquidazioneIva($this->dataMapper);
        $debito = 0.0;
        foreach ($attivita as $singola) {
            if ($separate->getPeriodicita($singola, $azienda) === PeriodicitaIva::trimestrale) {
                $debito += max(0.0, $calcolo->calcola($dichiarazione->soggetto, new SismaDate($dichiarazione->anno . '-10-01'), new SismaDate($dichiarazione->anno . '-12-31'), $singola)->getIvaPeriodo());
            }
        }
        return $debito;
    }

    /**
     * Righe dell'attività separata: quelle dei suoi registri e le quote
     * degli acquisti promiscui (righe ricalcolate, non salvate).
     *
     * @param RigaIva[] $righe
     * @return RigaIva[]
     */
    private function getRigheAttivita(array $righe, Soggetto $soggetto, int $anno, AttivitaIva $attivita, bool $primoModulo, string $etichetta): array
    {
        $separate = new AttivitaSeparate($this->dataMapper);
        $proprie = $nonRipartite = [];
        foreach ($righe as $riga) {
            $quote = $separate->getQuote($riga, $soggetto, $anno);
            if (count($quote) === 0) {
                $nonRipartite[$riga->registrazioneContabile->id] = $riga->registrazioneContabile->getDescrizioneCompleta();
                continue;
            }
            $quota = $quote[$attivita->numero] ?? 0.0;
            if ($quota <= 0) {
                continue;
            }
            if ($quota < 1) {
                $riga = clone $riga;
                $riga->imponibile = round($riga->imponibile * $quota, 2);
                $riga->imposta = round($riga->imposta * $quota, 2);
            }
            $proprie[] = $riga;
        }
        if ($primoModulo && (count($nonRipartite) > 0)) {
            $this->avvisa($etichetta . 'acquisti promiscui senza ripartizione tra le attività, esclusi: ' . implode('; ', $nonRipartite) . '.');
        }
        return $proprie;
    }

    /**
     * Quadri VA, VE, VF, VJ, VH e VL del modulo corrente dalla contabilità
     * del soggetto.
     */
    private function proponiDaContabilita(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione, Soggetto $soggetto, TipoModulo $tipo, string $etichetta, ?SismaDate $operazione = null, ?AttivitaIva $attivita = null): void
    {
        $anno = $dichiarazione->anno;
        $regime = new RegimeIva($this->dataMapper);
        // attività alla fine dell'anno (codici ATECO 2025 dal 1° aprile 2025); per le attività separate il codice dell'attività
        $datiFiscali = (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($soggetto, new SismaDate($anno . '-12-31')) ?? $regime->getDatiFiscali($soggetto, $anno);
        $codiceAteco = $attivita?->codiceAteco?->codice ?? $datiFiscali?->codiceAteco?->codice;
        if ($codiceAteco === null) {
            $this->avvisa($etichetta . 'manca il codice ATECO nei dati fiscali dell\'Anagrafica: indicarlo nel rigo VA2.');
        } else {
            $quadri->proponi('VA002001', str_replace('.', '', $codiceAteco));
        }
        // ultima dichiarazione prima dell'ingresso in un Gruppo IVA (artt. 70-bis e seguenti)
        if ($tipo === TipoModulo::principale) {
            $quadri->proponi('VA011001', RimborsoIva::isIngressoGruppoIva($regime->getDatiFiscali($soggetto, $anno), $regime->getDatiFiscali($soggetto, $anno + 1)) ? true : null);
        }
        if ($tipo === TipoModulo::soggettoTrasformato) {
            $partitaIva = $datiFiscali?->partitaIva ?? $soggetto->partitaIva;
            if ($partitaIva === null) {
                $this->avvisa($etichetta . 'manca la partita IVA del soggetto partecipante all\'operazione straordinaria (rigo VA1).');
            } else {
                $quadri->proponi('VA001001', $partitaIva);
            }
        }
        // soggetto trasformato: operazioni fino al giorno precedente l'operazione straordinaria
        $fine = $operazione === null ? new SismaDate($anno . '-12-31') : new SismaDate((new \DateTimeImmutable($operazione->format('Y-m-d')))->modify('-1 day')->format('Y-m-d'));
        $righe = iterator_to_array((new RigaIvaModel($this->dataMapper))->getPerPeriodo($soggetto, new SismaDate($anno . '-01-01'), $fine), false);
        if ($attivita !== null) {
            $righe = $this->getRigheAttivita($righe, $soggetto, $anno, $attivita, $quadri->getModuloCorrente() === 1, $etichetta);
        }
        if (count($righe) === 0) {
            $this->avvisa($etichetta . 'nessuna registrazione IVA del ' . $anno . ' in Contabilità: quadri VE e VF da compilare a mano.');
        }
        $regimeDetrazione = (new AttivitaSeparate($this->dataMapper))->getRegimeDetrazione($soggetto, $anno, $attivita);
        $this->proponiOperazioniAttive($quadri, $soggetto, $anno, $righe, $regime->isVentilazione($soggetto, $anno), $etichetta, $regimeDetrazione);
        $this->proponiOperazioniPassive($quadri, $righe, $etichetta, $regimeDetrazione);
        if ($regimeDetrazione === null) {
            $this->proponiProrata($quadri, $etichetta);
            $this->proponiConnesseOrdinarie($quadri, $righe, $etichetta);
        } else {
            $this->proponiRegimeDetrazione($quadri, $regimeDetrazione, $righe, $etichetta);
        }
        if (($attivita !== null) && ($quadri->getModuloCorrente() > 1)) {
            // attività separate: VH e sezioni 2 e 3 del VL nel solo primo modulo
            $this->proponiImpostaAnnuale($quadri);
            return;
        }
        try {
            $periodicita = $regime->getPeriodicita($soggetto, $anno);
        } catch (\DomainException $errore) {
            $periodicita = null;
            $this->avvisa($etichetta . ($regimeDetrazione === RegimeDetrazioneIva::agricoloEsonerato ? 'agricoltore esonerato tenuto alla dichiarazione: il quadro VH non si compila.'
                                : $errore->getMessage() . ' Righi delle liquidazioni periodiche (VH, VL) da compilare a mano.'));
        }
        $this->trimestrali[$quadri->getModuloCorrente()] = $periodicita === PeriodicitaIva::trimestrale;
        $liquidazioni = $periodicita === null ? [] : (new LiquidazioneIvaModel($this->dataMapper))->getPerAnno($soggetto, $anno);
        $ultimoPeriodo = null;
        if (($operazione !== null) && ($periodicita !== null)) {
            // periodi chiusi prima dell'operazione straordinaria
            $mese = (int) $fine->format('n') - ((int) $fine->format('Y') < $anno ? 12 : 0);
            $ultimoPeriodo = $periodicita === PeriodicitaIva::mensile ? $mese : intdiv($mese + 2, 3);
            $liquidazioni = array_filter($liquidazioni, fn(LiquidazioneIva $liquidazione) => $liquidazione->periodo <= $ultimoPeriodo);
        }
        if ($periodicita !== null) {
            $this->proponiVariazioniPeriodiche($quadri, $dichiarazione, $soggetto, $periodicita, $liquidazioni, $etichetta, $ultimoPeriodo);
        }
        $this->proponiImpostaAnnuale($quadri);
        $this->proponiLiquidazioneAnnuale($quadri, $dichiarazione, $soggetto, $periodicita, $liquidazioni);
    }

    /**
     * @param RigaIva[] $righe
     */
    private function proponiOperazioniAttive(QuadriDichiarazione $quadri, Soggetto $soggetto, int $anno, array $righe, bool $ventilazione, string $etichetta, ?RegimeDetrazioneIva $regimeDetrazione = null): void
    {
        $sezione1 = $sezione2 = $altre = [];
        $impostaRegistri = 0.0;
        $ventilare = false;
        $registroCorrispettivi = null;
        foreach ($righe as $riga) {
            if ($riga->registroIva->tipo === TipoRegistroIva::acquisti) {
                continue;
            }
            $codice = $riga->codiceIva;
            if ($codice->ventilazione) {
                $ventilare = true;
                $registroCorrispettivi ??= $riga->registroIva;
                continue;
            }
            $esigibilita = $riga->registrazioneContabile->esigibilita;
            $qualifica = $riga->qualifica ?? (self::isImmobilizzazione($riga->conto?->voceCee?->value) ? QualificaOperazione::beneAmmortizzabile : null);
            if ($qualifica === QualificaOperazione::beneAmmortizzabile) {
                // cessioni di beni ammortizzabili: nel volume d'affari solo per la diminuzione del VE40
                $altre['VE040001'] = ($altre['VE040001'] ?? 0) + $riga->imponibile;
                if ($codice->natura === NaturaIva::esenti) {
                    $altre['VF034004'] = ($altre['VF034004'] ?? 0) + $riga->imponibile;
                }
            } elseif (($qualifica !== null) && isset(self::CAMPI_QUALIFICA[$qualifica->value])) {
                $altre[self::CAMPI_QUALIFICA[$qualifica->value]] = ($altre[self::CAMPI_QUALIFICA[$qualifica->value]] ?? 0) + $riga->imponibile;
            }
            if ($codice->natura !== null) {
                $campo = self::NATURE_VE[$codice->natura->value] ?? null;
                if (array_key_exists($codice->natura->value, self::NATURE_VE) === false) {
                    $this->avvisa($etichetta . 'operazioni attive con natura ' . $codice->natura->value . ' (' . $codice->descrizione . ') non comprese nel volume d\'affari: verificare.');
                    continue;
                }
                if (str_starts_with($codice->natura->value, 'N6')) {
                    $altre['VE035001'] = ($altre['VE035001'] ?? 0) + $riga->imponibile;
                }
                if (($campo !== null) && str_starts_with($campo, 'VE030')) {
                    $altre['VE030001'] = ($altre['VE030001'] ?? 0) + $riga->imponibile;
                }
                if ($campo !== null) {
                    $altre[$campo] = ($altre[$campo] ?? 0) + $riga->imponibile;
                }
                continue;
            }
            if ($esigibilita === EsigibilitaIva::scissionePagamenti) {
                $altre['VE038001'] = ($altre['VE038001'] ?? 0) + $riga->imponibile;
                continue;
            }
            if ($esigibilita === EsigibilitaIva::differita) {
                $altre['VE037001'] = ($altre['VE037001'] ?? 0) + $riga->imponibile;
                continue;
            }
            // sezione 1: conferimenti alle cooperative e cessioni degli esonerati, con la percentuale di compensazione come aliquota
            $compensazione = $qualifica === QualificaOperazione::conferimentoAgricolo ? $codice->aliquota : ($regimeDetrazione === RegimeDetrazioneIva::agricoloEsonerato ? $riga->percentualeCompensazione : null);
            if ($compensazione !== null) {
                $this->accumula($sezione1, $compensazione, $riga->imponibile);
                $impostaRegistri += $riga->imponibile * $compensazione / 100;
            } else {
                $this->accumula($sezione2, $codice->aliquota, $riga->imponibile);
                $impostaRegistri += $riga->imposta;
            }
            // consumatori finali: corrispettivi e fatture a clienti senza partita IVA (quadro VT)
            $this->vt['imponibile'] += $riga->imponibile;
            $this->vt['imposta'] += $riga->imposta;
            if (($riga->registroIva->tipo === TipoRegistroIva::corrispettivi) || (trim((string) $riga->registrazioneContabile->controparte?->partitaIva) === '')) {
                $this->vt['consumatoriImponibile'] += $riga->imponibile;
                $this->vt['consumatoriImposta'] += $riga->imposta;
                $this->accumulaRegione($riga->registroIva->unitaLocale?->comune?->siglaProvincia, $riga->imponibile, $riga->imposta);
            }
        }
        if ($ventilare && $ventilazione) {
            try {
                foreach ((new VentilazioneCorrispettivi($this->dataMapper))->calcola($soggetto, new SismaDate($anno . '-01-01'), new SismaDate($anno . '-12-31'))->righe as $ripartizione) {
                    $this->accumula($sezione2, $ripartizione['codice']->aliquota, $ripartizione['imponibile']);
                    $impostaRegistri += $ripartizione['imposta'];
                    $this->vt['imponibile'] += $ripartizione['imponibile'];
                    $this->vt['imposta'] += $ripartizione['imposta'];
                    $this->vt['consumatoriImponibile'] += $ripartizione['imponibile'];
                    $this->vt['consumatoriImposta'] += $ripartizione['imposta'];
                    $this->accumulaRegione($registroCorrispettivi?->unitaLocale?->comune?->siglaProvincia, $ripartizione['imponibile'], $ripartizione['imposta']);
                }
                $this->avvisa($etichetta . 'corrispettivi da ventilare ripartiti con la ventilazione annuale (rapporti degli acquisti per la rivendita dell\'intero anno).');
            } catch (\DomainException $errore) {
                $this->avvisa($etichetta . 'ventilazione dei corrispettivi non eseguita: ' . $errore->getMessage());
            }
        } elseif ($ventilare) {
            $this->avvisa($etichetta . 'corrispettivi da ventilare registrati senza l\'opzione per la ventilazione nei dati fiscali: non compresi nel quadro VE.');
        }
        foreach ($sezione1 as $percentuale => $imponibile) {
            $rigo = self::RIGHI_COMPENSAZIONE[$percentuale] ?? null;
            if ($rigo === null) {
                $this->avvisa($etichetta . 'operazioni con la percentuale di compensazione del ' . $percentuale . '% senza un rigo nella sezione 1 del quadro VE: indicarle a mano.');
                continue;
            }
            $imponibileRigo = (int) round($imponibile);
            $quadri->proponi(sprintf('VE%03d001', $rigo), $imponibileRigo);
            $quadri->proponi(sprintf('VE%03d002', $rigo), (int) round($imponibileRigo * (float) $percentuale / 100));
        }
        foreach ($sezione2 as $aliquota => $imponibile) {
            $rigo = self::RIGHI_VE[$aliquota] ?? null;
            if ($rigo === null) {
                $this->avvisa($etichetta . 'operazioni attive con aliquota ' . $aliquota . '% senza un rigo nella sezione 2 del quadro VE: indicarle a mano.');
                continue;
            }
            $imponibileRigo = (int) round($imponibile);
            $quadri->proponi(sprintf('VE%03d001', $rigo), $imponibileRigo);
            $quadri->proponi(sprintf('VE%03d002', $rigo), (int) round($imponibileRigo * (float) $aliquota / 100));
        }
        foreach ($altre as $campo => $importo) {
            $quadri->proponi($campo, (int) round($importo));
        }
        $this->proponiTotaliVe($quadri, count($sezione1) + count($sezione2) > 0 ? $impostaRegistri : null);
    }

    /**
     * Totali del quadro VE dai righi effettivi; con l'imposta dei registri
     * la differenza di arrotondamento va nel VE25.
     */
    private function proponiTotaliVe(QuadriDichiarazione $quadri, ?float $impostaRegistri): void
    {
        $totaleImponibile = $totaleImposta = 0;
        foreach (array_merge(range(1, 11), array_values(self::RIGHI_VE)) as $rigo) {
            $totaleImponibile += $quadri->getImporto(sprintf('VE%03d001', $rigo));
            $totaleImposta += $quadri->getImporto(sprintf('VE%03d002', $rigo));
        }
        $quadri->proponi('VE024001', $totaleImponibile);
        $quadri->proponi('VE024002', $totaleImposta);
        if ($impostaRegistri !== null) {
            $quadri->proponi('VE025002', (int) round($impostaRegistri) - $quadri->getImporto('VE024002'));
        }
        $quadri->proponi('VE026002', $quadri->getImporto('VE024002') + $quadri->getImporto('VE025002'));
        $quadri->proponi('VE050001', $quadri->getImporto('VE024001') + $quadri->somma('VE030001', 'VE031001', 'VE032001', 'VE033001', 'VE034001', 'VE035001', 'VE036001', 'VE037001', 'VE038001', 'VE038002')
                - $quadri->somma('VE039001', 'VE040001'));
    }

    /**
     * @param RigaIva[] $righe
     */
    private function proponiOperazioniPassive(QuadriDichiarazione $quadri, array $righe, string $etichetta, ?RegimeDetrazioneIva $regimeDetrazione = null): void
    {
        $imponibili = $vj = $altre = [];
        $impostaRegistri = 0.0;
        $ammortizzabili = $rivendita = 0.0;
        $registrazioni = [];
        $senzaTipologia = false;
        foreach ($righe as $riga) {
            if ($riga->registroIva->tipo !== TipoRegistroIva::acquisti) {
                continue;
            }
            $codice = $riga->codiceIva;
            // acquisti delle connesse occasionali (art. 34-bis): tutti nel VF18, campo 1
            $connessa = ($riga->qualifica === QualificaOperazione::connessaOccasionale) && (($regimeDetrazione === null) || $regimeDetrazione->isCompensazione());
            if ($codice->natura !== null) {
                $campo = self::NATURE_VF[$codice->natura->value] ?? null;
                $campo = $connessa && ($campo !== null) ? 'VF018001' : $campo;
                if ($campo === null) {
                    if ($codice->natura->value !== 'N1') {
                        $this->avvisa($etichetta . 'acquisti con natura ' . $codice->natura->value . ' (' . $codice->descrizione . ') non compresi nel quadro VF: verificare.');
                    }
                    continue;
                }
                $altre[$campo] = ($altre[$campo] ?? 0) + $riga->imponibile;
                if ($campo === 'VF017001') {
                    $modulo = $quadri->getModuloCorrente();
                    $this->ivaNonAssolta[$modulo] ??= ['imponibile' => 0.0, 'imposta' => 0.0, 'predefinita' => false];
                    $this->ivaNonAssolta[$modulo]['imponibile'] += $riga->imponibile;
                    $this->ivaNonAssolta[$modulo]['imposta'] += $riga->imponibile * ($riga->aliquotaTeorica ?? self::ALIQUOTA_TEORICA) / 100;
                    $this->ivaNonAssolta[$modulo]['predefinita'] = $this->ivaNonAssolta[$modulo]['predefinita'] || ($riga->aliquotaTeorica === null);
                }
                if ($riga->registrazioneContabile->importazione) {
                    $altre['VF028003'] = ($altre['VF028003'] ?? 0) + $riga->imponibile;
                }
                if ($campo === 'VF019001') {
                    $altre['VF019002'] = ($altre['VF019002'] ?? 0) + $riga->imponibile;
                }
            } else {
                $detraibile = $riga->imponibile * $riga->detraibilita / 100;
                if ($riga->registrazioneContabile->importazione) {
                    $altre['VF028003'] = ($altre['VF028003'] ?? 0) + $riga->imponibile;
                    $altre['VF028004'] = ($altre['VF028004'] ?? 0) + $riga->imposta;
                }
                if ($connessa) {
                    $altre['VF018001'] = ($altre['VF018001'] ?? 0) + $riga->imponibile;
                } else {
                    if ($riga->detraibilita > 0) {
                        $this->accumula($imponibili, $codice->aliquota, $detraibile);
                        $impostaRegistri += $riga->getImpostaDetraibile();
                    }
                    if ($riga->detraibilita < 100) {
                        $altre['VF021001'] = ($altre['VF021001'] ?? 0) + $riga->imponibile - $detraibile;
                    }
                }
                if ($codice->inversioneContabile) {
                    $rigo = match (true) {
                        str_starts_with($codice->codice, 'UE') => 9,
                        str_starts_with($codice->codice, 'ES') => 3,
                        default => self::RIGHI_VJ[$riga->naturaInversione?->value ?? ''] ?? 12,
                    };
                    $senzaTipologia = $senzaTipologia || (($rigo === 12) && ($riga->naturaInversione?->value !== 'N6.3'));
                    $chiave = $this->chiaveAliquota($codice->aliquota);
                    $vj[$rigo][$chiave] = ($vj[$rigo][$chiave] ?? 0) + $riga->imponibile;
                    if ($rigo === 9) {
                        $altre['VF028001'] = ($altre['VF028001'] ?? 0) + $riga->imponibile;
                        $altre['VF028002'] = ($altre['VF028002'] ?? 0) + $riga->imposta;
                    }
                }
            }
            if ($riga->rivendita || $riga->altriProdotti) {
                $rivendita += $riga->imponibile;
            }
            $registrazioni[$riga->registrazioneContabile->id] = $riga->registrazioneContabile;
        }
        // beni ammortizzabili: importi in dare sui conti delle immobilizzazioni immateriali e materiali
        $righeContabili = new RigaContabileModel($this->dataMapper);
        foreach ($registrazioni as $registrazione) {
            foreach ($righeContabili->getRighe($registrazione) as $rigaContabile) {
                if (self::isImmobilizzazione($rigaContabile->conto->voceCee?->value)) {
                    $ammortizzabili += $rigaContabile->dare - $rigaContabile->avere;
                }
            }
        }
        foreach ($imponibili as $aliquota => $imponibile) {
            $rigo = self::RIGHI_VF[$aliquota] ?? null;
            if ($rigo === null) {
                $this->avvisa($etichetta . 'acquisti con aliquota ' . $aliquota . '% senza un rigo nei righi VF1-VF13: indicarli a mano.');
                continue;
            }
            $imponibileRigo = (int) round($imponibile);
            $quadri->proponi(sprintf('VF%03d001', $rigo), $imponibileRigo);
            $quadri->proponi(sprintf('VF%03d002', $rigo), (int) round($imponibileRigo * (float) $aliquota / 100));
        }
        foreach ($altre as $campo => $importo) {
            $quadri->proponi($campo, (int) round($importo));
        }
        $this->proponiTotaliVf($quadri, count($imponibili) > 0 ? $impostaRegistri : null, $ammortizzabili, $rivendita);
        if ($quadri->getImporto('VF025001') > 0) {
            $this->avvisa($etichetta . 'ripartizione del rigo VF29: beni ammortizzabili dai conti delle immobilizzazioni, beni per la rivendita dalle righe IVA; i beni strumentali non ammortizzabili (canoni di leasing e locazione) vanno spostati a mano nel campo 2.');
        }
        foreach ($vj as $rigo => $perAliquota) {
            $imponibileRigo = $impostaRigo = 0;
            foreach ($perAliquota as $aliquota => $imponibile) {
                $imponibileRigo += (int) round($imponibile);
                $impostaRigo += (int) round(round($imponibile) * (float) $aliquota / 100);
            }
            $quadri->proponi(sprintf('VJ%03d001', $rigo), $imponibileRigo);
            $quadri->proponi(sprintf('VJ%03d002', $rigo), $impostaRigo);
        }
        if ($senzaTipologia) {
            $this->avvisa($etichetta . 'acquisti interni in inversione contabile senza la tipologia N6 sulla riga IVA (o N6.9): proposti nel rigo VJ12 (subappalti edili); indicare la tipologia in prima nota o spostarli nel rigo corretto.');
        }
        $this->proponiTotaliVj($quadri);
    }

    private function proponiTotaliVf(QuadriDichiarazione $quadri, ?float $impostaRegistri, float $ammortizzabili, float $rivendita): void
    {
        $totaleImponibile = $totaleImposta = 0;
        for ($rigo = 1; $rigo <= 13; $rigo++) {
            $totaleImponibile += $quadri->getImporto(sprintf('VF%03d001', $rigo));
            $totaleImposta += $quadri->getImporto(sprintf('VF%03d002', $rigo));
        }
        $totaleImponibile += $quadri->somma('VF017001', 'VF018001', 'VF018002', 'VF019001', 'VF020001', 'VF021001', 'VF022001', 'VF023001') - $quadri->getImporto('VF024001');
        $quadri->proponi('VF025001', $totaleImponibile);
        $quadri->proponi('VF025002', $totaleImposta);
        if ($impostaRegistri !== null) {
            $quadri->proponi('VF026002', (int) round($impostaRegistri) - $quadri->getImporto('VF025002'));
        }
        $quadri->proponi('VF027002', $quadri->getImporto('VF025002') + $quadri->getImporto('VF026002'));
        if ($quadri->getImporto('VF025001') > 0) {
            $ammortizzabili = min((int) round($ammortizzabili), $quadri->getImporto('VF025001'));
            $rivendita = min((int) round($rivendita), $quadri->getImporto('VF025001') - $ammortizzabili);
            $quadri->proponi('VF029001', $ammortizzabili);
            $quadri->proponi('VF029003', $rivendita);
            $quadri->proponi('VF029004', $quadri->getImporto('VF025001') - $quadri->somma('VF029001', 'VF029002', 'VF029003'));
        }
        $quadri->proponi('VF071002', $quadri->getImporto('VF027002') + $quadri->getImporto('VF070002'));
    }

    private function proponiTotaliVj(QuadriDichiarazione $quadri): void
    {
        $totale = 0;
        for ($rigo = 1; $rigo <= 18; $rigo++) {
            $totale += $quadri->getImporto(sprintf('VJ%03d002', $rigo));
        }
        $quadri->proponi('VJ019002', $totale);
    }

    /**
     * Rigo VF70 dal prospetto D, se compilato; senza prospetto il rigo resta
     * da indicare a mano e il cambio di regime è segnalato con la proposta.
     */
    private function proponiRettifiche(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione, int $modulo, ?AttivitaIva $attivita, string $etichetta): void
    {
        if (isset($dichiarazione->id) === false) {
            return;
        }
        $righi = (new RettificaDetrazioneModel($this->dataMapper))->getRighi($dichiarazione, $modulo);
        if (count($righi) > 0) {
            $quadri->proponi('VF070002', (int) round(array_sum(array_map(fn(RettificaDetrazione $rettifica) => $rettifica->importo, $righi))));
            return;
        }
        $proposta = (new ProspettoRettifiche($this->dataMapper))->getProposta($dichiarazione->soggetto, $dichiarazione->anno, $attivita);
        if (count($proposta['beni']) > 0) {
            $this->avvisa($etichetta . sprintf('cambio di regime: rettifica della detrazione (art. 19-bis2, c. 3) da indicare nel prospetto D, proposti € %s per i beni ammortizzabili; rimanenze e servizi non utilizzati vanno aggiunti a mano.', number_format($proposta['totale'], 0, ',', '.')));
        }
    }

    /**
     * Operazioni connesse occasionali dell'impresa agricola che applica l'IVA
     * nei modi ordinari (Appendice, "Attività agricole connesse"): un solo
     * modulo, rigo VF62, e il 50% dell'imposta si aggiunge alla detrazione.
     *
     * @param RigaIva[] $righe
     */
    private function proponiConnesseOrdinarie(QuadriDichiarazione $quadri, array $righe, string $etichetta): void
    {
        $imponibile = $imposta = 0.0;
        foreach ($righe as $riga) {
            if (($riga->registroIva->tipo !== TipoRegistroIva::acquisti) && ($riga->qualifica === QualificaOperazione::connessaOccasionale)) {
                $imponibile += $riga->imponibile;
                $imposta += $riga->imposta;
            }
        }
        if ($imposta <= 0) {
            return;
        }
        $quadri->proponi('VF062001', (int) round($imponibile));
        $quadri->proponi('VF062002', (int) round($imposta));
        $quadri->proponi('VF071002', $quadri->getImporto('VF071002') + (int) round($quadri->getImporto('VF062002') * 0.5));
        if ($quadri->get('VF030003') !== null) {
            $this->avvisa($etichetta . 'rigo VF62 con il pro-rata: le istruzioni li indicano come alternativi (VF30, casella 3), verificare il rigo VF60.');
        }
    }

    /**
     * IVA ammessa in detrazione nei regimi speciali agricoli (casella del
     * VF30 e VF71): la quota forfetaria del VE26 o, con il regime dell'art.
     * 34, la sezione 3-B del quadro VF: operazioni diverse e loro detrazione
     * (VF38, VF53), detrazione forfetizzata per percentuale di compensazione
     * (VF39-VF49), IVA teorica delle cessioni non imponibili (VF54), attività
     * connesse occasionali (VF62).
     *
     * @param RigaIva[] $righe
     */
    private function proponiRegimeDetrazione(QuadriDichiarazione $quadri, RegimeDetrazioneIva $regime, array $righe, string $etichetta): void
    {
        $quadri->proponi($regime->getCasellaVf30(), true);
        if ($regime->isCompensazione() === false) {
            $quadri->proponi('VF071002', (int) round($quadri->getImporto('VE026002') * $regime->getQuotaForfetaria()) + $quadri->getImporto('VF070002'));
            $this->avvisa($etichetta . mb_strtolower($regime->getLabel()) . ': IVA detraibile forfetaria sulle operazioni imponibili (VF71), gli acquisti non si detraggono.');
            return;
        }
        $compensazione = [];
        $diverseImponibile = $diverseImposta = $detraibili = $teorica = $connesseImponibile = $connesseImposta = 0.0;
        $acquistiConnesse = false;
        foreach ($righe as $riga) {
            if ($riga->registroIva->tipo === TipoRegistroIva::acquisti) {
                $detraibili += $riga->getDetrazioneOperazioneDiversa();
                $acquistiConnesse = $acquistiConnesse || ($riga->qualifica === QualificaOperazione::connessaOccasionale);
                if ($riga->operazioneDiversa && $riga->isBeneAmmortizzabile()) {
                    $this->avvisa($etichetta . 'bene ammortizzabile segnato per le operazioni diverse: IVA esclusa dal VF53 (art. 34, c. 5).');
                }
                continue;
            }
            if ($riga->qualifica === QualificaOperazione::connessaOccasionale) {
                $connesseImponibile += $riga->imponibile;
                $connesseImposta += $riga->imposta;
                continue;
            }
            $percentuale = $riga->percentualeCompensazione ?? ($riga->qualifica === QualificaOperazione::conferimentoAgricolo ? $riga->codiceIva->aliquota : null);
            if ($percentuale === null) {
                if ($riga->codiceIva->natura === null) {
                    $diverseImponibile += $riga->imponibile;
                    $diverseImposta += $riga->imposta;
                }
            } elseif ($riga->codiceIva->natura !== null) {
                $teorica += $riga->imponibile * $percentuale / 100;
            } else {
                $this->accumula($compensazione, $percentuale, $riga->imponibile);
            }
        }
        $quadri->proponi('VF038001', (int) round($diverseImponibile));
        $quadri->proponi('VF038002', (int) round($diverseImposta));
        foreach ($compensazione as $percentuale => $imponibile) {
            $rigo = (self::RIGHI_COMPENSAZIONE[$percentuale] ?? null) === null ? null : self::RIGHI_COMPENSAZIONE[$percentuale] + 38;
            if ($rigo === null) {
                $this->avvisa($etichetta . 'cessioni con la percentuale di compensazione del ' . $percentuale . '% senza un rigo VF39-VF49: indicarle a mano.');
                continue;
            }
            $quadri->proponi(sprintf('VF%03d001', $rigo), (int) round($imponibile));
            $quadri->proponi(sprintf('VF%03d002', $rigo), (int) round(round($imponibile) * (float) $percentuale / 100));
        }
        $totaleImponibile = $totaleImposta = 0;
        for ($rigo = 39; $rigo <= 49; $rigo++) {
            $totaleImponibile += $quadri->getImporto(sprintf('VF%03d001', $rigo));
            $totaleImposta += $quadri->getImporto(sprintf('VF%03d002', $rigo));
        }
        $quadri->proponi('VF052001', $totaleImponibile);
        $quadri->proponi('VF052002', $totaleImposta + $quadri->getImporto('VF051002'));
        $quadri->proponi('VF053002', (int) round($detraibili));
        $quadri->proponi('VF054002', (int) round($teorica));
        $quadri->proponi('VF055002', $quadri->somma('VF052002', 'VF053002', 'VF054002'));
        $quadri->proponi('VF062001', (int) round($connesseImponibile));
        $quadri->proponi('VF062002', (int) round($connesseImposta));
        $quadri->proponi('VF071002', $quadri->getImporto('VF055002') + (int) round($quadri->getImporto('VF062002') * 0.5) + $quadri->getImporto('VF070002'));
        if (($quadri->getImporto('VF062002') > 0) && ($acquistiConnesse === false)) {
            $this->avvisa($etichetta . 'attività connesse occasionali (VF62) senza acquisti destinati: indicarli nella prima nota, per riportarli nel rigo VF18, campo 1.');
        }
        if (($diverseImponibile > 0) && ($detraibili <= 0)) {
            $this->avvisa($etichetta . 'operazioni diverse (VF38) senza acquisti destinati: indicarli nella prima nota, altrimenti non c\'è detrazione (VF53). Di un acquisto promiscuo si segna, su una riga a parte, solo la quota specificamente imputabile; i costi indivisibili non si detraggono (art. 34, c. 5).');
        }
    }

    /**
     * Quota forfetaria dei regimi speciali barrati nel VF30, null senza.
     */
    public static function getQuotaForfetaria(QuadriDichiarazione $quadri): ?float
    {
        foreach (RegimeDetrazioneIva::cases() as $regime) {
            if (($regime->getQuotaForfetaria() !== null) && ($quadri->get($regime->getCasellaVf30()) !== null)) {
                return $regime->getQuotaForfetaria();
            }
        }
        return null;
    }

    /**
     * Pro-rata di detrazione (art. 19, c. 5, e 19-bis D.P.R. 633/1972) con
     * operazioni esenti nel VE33: casella 3 del VF30, percentuale del VF34
     * campo 9 con la formula delle istruzioni (i campi da 1 a 8 si
     * completano a mano), VF37 e VF71; con sole operazioni esenti la
     * casella del VF32 e nessuna detrazione.
     */
    private function proponiProrata(QuadriDichiarazione $quadri, string $etichetta): void
    {
        $esenti = $quadri->getImporto('VE033001');
        if (($esenti <= 0) || ($quadri->get('VF031002') !== null) || ($quadri->get('VF033001') !== null)) {
            return;
        }
        $dannoDiritto = $quadri->getImporto('VE050001') - $esenti;
        if ($dannoDiritto <= 0) {
            $quadri->proponi('VF032001', true);
            $quadri->proponi('VF071002', $quadri->getImporto('VF070002'));
            $this->avvisa($etichetta . 'solo operazioni esenti: nessuna IVA detraibile (VF32); gli acquisti vanno spostati nel rigo VF22.');
            return;
        }
        $quadri->proponi('VF030003', true);
        $nonAssolta = $this->ivaNonAssolta[$quadri->getModuloCorrente()] ?? null;
        if ($nonAssolta !== null) {
            $quadri->proponi('VF035001', (int) round($nonAssolta['imposta']));
            if ($nonAssolta['predefinita']) {
                $this->avvisa($etichetta . 'IVA non assolta sugli acquisti con dichiarazione d\'intento (VF35) calcolata al 22% per le righe senza aliquota teorica: indicarla nella prima nota se diversa.');
            }
        }
        $percentuale = self::calcolaPercentuale($quadri);
        $quadri->proponi('VF034009', $percentuale);
        $base = $quadri->getImporto('VF027002') + $quadri->getImporto('VF035001') - $quadri->getImporto('VF036001');
        $quadri->proponi('VF037001', (int) round($base * $percentuale / 100) - $quadri->getImporto('VF035001') + $quadri->getImporto('VF036001'));
        $quadri->proponi('VF071002', $quadri->getImporto('VF037001') + $quadri->getImporto('VF070002'));
        $this->avvisa($etichetta . sprintf('operazioni esenti: pro-rata di detrazione del %d%% (VF34, con le qualifiche delle righe IVA delle vendite); le operazioni fuori dai registri (campi 5 e 6) si indicano a mano.', $percentuale));
    }

    /**
     * Percentuale di detrazione (VF34, campo 9): formula delle istruzioni,
     * arrotondata all'unità sulle prime tre cifre decimali (0,502 a 1, 7,500
     * a 7), tra 0 e 100.
     */
    public static function calcolaPercentuale(QuadriDichiarazione $quadri): int
    {
        $c = fn(int $campo) => $quadri->getImporto(sprintf('VF034%03d', $campo));
        $ve50 = $quadri->getImporto('VE050001');
        $denominatore = $ve50 - $c(8) + $c(5) + $c(6) - $c(2) - $c(3);
        if ($denominatore <= 0) {
            return 0;
        }
        $numeratore = $ve50 - $c(8) + $c(1) + $c(5) + $c(6) + $c(7) - ($quadri->getImporto('VE033001') - $c(4));
        $percentuale = round($numeratore / $denominatore * 100, 3);
        $intera = floor($percentuale);
        $arrotondata = (int) ($intera + (($percentuale - $intera) > 0.5 ? 1 : 0));
        return max(0, min(100, $arrotondata));
    }

    /**
     * Quadro VH per i periodi senza comunicazione delle liquidazioni
     * periodiche inviata.
     *
     * @param array<int, LiquidazioneIva> $liquidazioni
     */
    private function proponiVariazioniPeriodiche(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione, Soggetto $soggetto, PeriodicitaIva $periodicita, array $liquidazioni, string $etichetta, ?int $ultimoPeriodo = null): void
    {
        $comunicazione = new ComunicazioneLipe($this->dataMapper);
        $comunicazione->studio = $dichiarazione->studio;
        $comunicazione->soggetto = $soggetto;
        $comunicazione->anno = $dichiarazione->anno;
        $preparazione = new PreparazioneLipe($this->dataMapper);
        $comunicazioni = (new ComunicazioneLipeModel($this->dataMapper))->getPerAnno($soggetto, $dichiarazione->anno);
        $mensile = $periodicita === PeriodicitaIva::mensile;
        $nonInviati = [];
        foreach ($mensile ? range(1, min(12, $ultimoPeriodo ?? 12)) : range(1, min(3, $ultimoPeriodo ?? 3)) as $periodo) {
            $trimestre = $mensile ? intdiv($periodo - 1, 3) + 1 : $periodo;
            if (isset($comunicazioni[$trimestre]) && ($comunicazioni[$trimestre]->stato === StatoComunicazioneLipe::inviata)) {
                continue;
            }
            $descrizione = LiquidazioneIva::descriviPeriodo($periodicita, $dichiarazione->anno, $periodo);
            if (isset($liquidazioni[$periodo]) === false) {
                $this->avvisa($etichetta . 'liquidazione di ' . $descrizione . ' non elaborata in Contabilità: quadro VH da completare.');
                continue;
            }
            $nonInviati[] = $descrizione;
            $avvisi = [];
            $modulo = $preparazione->preparaModulo($comunicazione, $liquidazioni[$periodo], $avvisi);
            $rigo = $mensile ? $periodo + intdiv($periodo - 1, 3) : $periodo * 4;
            $quadri->proponi(sprintf('VH%03d001', $rigo), (int) round($modulo->importoACredito));
            $quadri->proponi(sprintf('VH%03d002', $rigo), (int) round($modulo->importoDaVersare));
            if ($liquidazioni[$periodo]->trimestreAnticipato !== null) {
                // attività separate: trimestre liquidato con il mese di fine trimestre
                $quadri->proponi(sprintf('VH%03d004', $liquidazioni[$periodo]->trimestreAnticipato * 4), true);
            }
            if (($modulo->acconto > 0) && ($modulo->metodoAcconto !== null)) {
                $quadri->proponi('VH017001', (int) round($modulo->acconto));
                $quadri->proponi('VH017002', $modulo->metodoAcconto);
            }
        }
        // attività trimestrali liquidate a parte: rigo del trimestre (VH4, VH8, VH12)
        if ($mensile) {
            foreach ((new LiquidazioneIvaModel($this->dataMapper))->getTrimestriSeparati($soggetto, $dichiarazione->anno) as $trimestre => $separata) {
                if ((isset($comunicazioni[$trimestre]) && ($comunicazioni[$trimestre]->stato === StatoComunicazioneLipe::inviata)) || (($ultimoPeriodo !== null) && ($trimestre * 3 > $ultimoPeriodo))) {
                    continue;
                }
                $avvisi = [];
                $modulo = $preparazione->preparaModulo($comunicazione, $separata, $avvisi);
                $quadri->proponi(sprintf('VH%03d001', $trimestre * 4), (int) round($modulo->importoACredito));
                $quadri->proponi(sprintf('VH%03d002', $trimestre * 4), (int) round($modulo->importoDaVersare));
                $nonInviati[] = $separata->getDescrizionePeriodo();
            }
        }
        if (count($nonInviati) > 0) {
            $this->avvisa($etichetta . 'quadro VH compilato per i periodi senza comunicazione delle liquidazioni periodiche inviata: ' . implode(', ', $nonInviati) . '.');
        }
    }

    /**
     * Sezione 1 del quadro VL: IVA a debito, detraibile, dovuta o a credito.
     */
    private function proponiImpostaAnnuale(QuadriDichiarazione $quadri): void
    {
        $quadri->proponi('VL001001', $quadri->getImporto('VE026002') + $quadri->getImporto('VJ019002'));
        $quadri->proponi('VL002001', $quadri->getImporto('VF071002'));
        $differenza = $quadri->getImporto('VL001001') - $quadri->getImporto('VL002001');
        $quadri->proponi('VL003001', max(0, $differenza));
        $quadri->proponi('VL004001', max(0, -$differenza));
    }

    /**
     * Sezioni 2 e 3 del quadro VL dalla contabilità e da Deleghe.
     *
     * @param array<int, LiquidazioneIva> $liquidazioni
     */
    private function proponiLiquidazioneAnnuale(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione, Soggetto $soggetto, ?PeriodicitaIva $periodicita, array $liquidazioni): void
    {
        [$credito, $compensato] = $this->getCreditoAnnoPrecedente($soggetto, $dichiarazione->anno, CreditiTrimestrali::getDataLimite($dichiarazione));
        // controllante: il credito di gruppo dell'anno precedente va nel VW25, non nel VL8
        $gruppi = new PropostaGruppoIva($this->dataMapper);
        $partecipazione = $gruppi->getPartecipazione($soggetto, $dichiarazione->anno);
        if (($partecipazione !== null) && ($partecipazione['partecipante'] === null)) {
            $credito = max(0, $credito - $gruppi->getCreditoGruppoPrecedente($partecipazione['gruppo'], $dichiarazione));
        }
        $quadri->proponi('VL008001', $credito);
        $quadri->proponi('VL009001', min($compensato, $quadri->getImporto('VL008001')));
        // chi partecipa al gruppo per l'intero anno non vi fa confluire il credito precedente (art. 73, ultimo comma): VL10 invece del VL25, riportato nel VX2
        $eccedenza = $quadri->getImporto('VL008001') - $quadri->getImporto('VL009001');
        $nonTrasferibile = ($partecipazione !== null) && ($partecipazione['ultimoMese'] >= 12);
        $quadri->proponi('VL010001', $nonTrasferibile ? $eccedenza : 0);
        $quadri->proponi('VL025001', $nonTrasferibile ? 0 : $eccedenza);
        // crediti trimestrali dei modelli IVA TR (per le società del gruppo nel prospetto IVA 26 PR della controllante)
        if (($soggetto->id === $dichiarazione->soggetto->id) && ($partecipazione === null)) {
            $trimestrali = new CreditiTrimestrali($this->dataMapper);
            $quadri->proponi('VL020001', $trimestrali->getRimborsiRichiesti($soggetto, $dichiarazione->anno));
            $entro = CreditiTrimestrali::getDataLimite($dichiarazione);
            $quadri->proponi('VL022001', $trimestrali->getCompensati($soggetto, $dichiarazione->anno, $entro));
            $esclusi = $trimestrali->getEsclusi($soggetto, $dichiarazione->anno, $entro);
            if ($esclusi > 0) {
                $this->avvisa(sprintf('Rigo VL22: € %s di crediti trimestrali non compresi, in deleghe non trasmesse o successive al %s.', number_format($esclusi, 0, ',', '.'), $entro->format('d/m/Y')));
            }
            if ($quadri->getImporto('VL020001') + $quadri->getImporto('VL022001') > 0) {
                $this->avvisa('Righi VL20 e VL22 dai modelli IVA TR presentati (rimborsi richiesti) e dagli utilizzi in F24 dei crediti 6036-6038 dell\'anno.');
            }
        }
        $trimestrale = $periodicita === PeriodicitaIva::trimestrale;
        // con le attività separate anche i trimestri liquidati a parte dalle attività trimestrali
        $liquidazioni = array_merge(array_values($liquidazioni), array_values((new LiquidazioneIvaModel($this->dataMapper))->getTrimestriSeparati($soggetto, $dichiarazione->anno)));
        $periodiche = array_filter($liquidazioni, fn(LiquidazioneIva $liquidazione) => ($trimestrale === false) || ($liquidazione->periodo < 4));
        // interessi dei trimestrali, comprese le attività separate trimestrali liquidate con il mese di fine trimestre
        $quadri->proponi('VL023001', (int) round(array_sum(array_map(fn(LiquidazioneIva $liquidazione) => $liquidazione->interessi, $periodiche))));
        $quadri->proponi('VL027001', (int) round(array_sum(array_map(fn(LiquidazioneIva $liquidazione) => $liquidazione->creditiImposta, $liquidazioni))));
        $quadri->proponi('VL029001', (int) round(array_sum(array_map(fn(LiquidazioneIva $liquidazione) => $liquidazione->versamentiAuto, $liquidazioni))));
        $acconto = (new AccontoIvaModel($this->dataMapper))->getPerAnno($soggetto, $dichiarazione->anno);
        $accontoDovuto = ($acconto !== null) && ($acconto->importo >= AccontoIva::IMPORTO_MINIMO) ? $acconto->importo : 0.0;
        // IVA di gruppo: crediti trasferiti nel VL21, debiti nel VL31, nel VL30 solo l'IVA dei periodi dopo l'uscita
        $gruppo = $soggetto->id === $dichiarazione->soggetto->id ? (new PropostaGruppoIva($this->dataMapper))->getPartecipazione($soggetto, $dichiarazione->anno) : null;
        if ($gruppo !== null) {
            $trasferimenti = PropostaGruppoIva::getTrasferimenti($periodiche);
            $quadri->proponi('VL021001', (int) round($trasferimenti['crediti']));
            $quadri->proponi('VL031001', (int) round($trasferimenti['debiti']));
            $periodiche = array_filter($periodiche, fn(LiquidazioneIva $liquidazione) => $liquidazione->isTrasferita() === false);
            $accontoDovuto = $gruppo['ultimoMese'] >= 12 ? 0.0 : $accontoDovuto;
        }
        if ($periodicita !== null) {
            $quadri->proponi('VL030002', (int) round(array_sum(array_map(fn(LiquidazioneIva $liquidazione) => $liquidazione->importoDovuto, $periodiche)) + $accontoDovuto));
            $quadri->proponi('VL030003', $this->getVersamentiPeriodici($dichiarazione, $soggetto));
        }
        $quadri->proponi('VL030001', max($quadri->getImporto('VL030002'), $quadri->somma('VL030003', 'VL030004', 'VL030005')));
    }

    /**
     * Righi VL32-VL41 per gruppi di moduli (paragrafo 2.6 delle
     * specifiche): un solo gruppo per il modulo unico e le contabilità
     * separate (sezioni 2 e 3 nel modulo 1), un gruppo per soggetto nelle
     * operazioni straordinarie (VA1), due gruppi con la prima frazione
     * d'anno (VA3). I risultati vanno nel primo modulo del gruppo.
     *
     * @param int[] $moduli
     */
    private function proponiRisultatiModuli(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione, array $moduli): void
    {
        foreach (self::getGruppi($quadri, $moduli) as $gruppo) {
            $primo = $gruppo[0];
            $quadri->usaModulo($primo);
            $debiti = $crediti = 0;
            foreach ($gruppo as $modulo) {
                $debiti += $quadri->getImporto('VL003001', $modulo);
                $crediti += $quadri->getImporto('VL004001', $modulo);
            }
            $debiti += $quadri->somma('VL020001', 'VL021001', 'VL022001', 'VL023001');
            $crediti += $quadri->somma('VL011001', 'VL012001', 'VL024001', 'VL025001', 'VL026001', 'VL027001', 'VL028001', 'VL029001', 'VL031001');
            $a = $debiti - $crediti - $quadri->getImporto('VL030001');
            $b = $debiti - $crediti - $quadri->somma('VL030003', 'VL030004', 'VL030005');
            $quadri->proponi('VL032001', $a >= 0 ? $a : 0);
            $quadri->proponi('VL033001', ($a < 0) && ($b < 0) ? -$b : 0);
            $daVersare = $quadri->getImporto('VL032001') - $quadri->somma('VL034001', 'VL035001');
            $interessi = ($this->trimestrali[$primo] ?? false) ? $daVersare : min($daVersare, (int) round($this->debitoQuartoTrimestre));
            $quadri->proponi('VL036001', $interessi > 0 ? (int) round($interessi / 100) : 0);
            $quadri->proponi('VL038001', $daVersare + $quadri->getImporto('VL036001'));
            $quadri->proponi('VL039001', $quadri->getImporto('VL033001') - $quadri->getImporto('VL037001'));
            $quadri->proponi('VL041001', ($a < 0) && (-$a - $quadri->getImporto('VL033001') > 0)
                        ? $quadri->getImporto('VL030002') - $quadri->somma('VL030003', 'VL030004', 'VL030005') : 0);
        }
    }

    /**
     * Gruppi di moduli per il calcolo dei righi VL32 e VL33.
     *
     * @param int[] $moduli
     * @return list<int[]>
     */
    public static function getGruppi(QuadriDichiarazione $quadri, array $moduli): array
    {
        sort($moduli);
        $primaFrazione = array_values(array_filter($moduli, fn(int $modulo) => $quadri->get('VA003001', $modulo) !== null));
        if ((count($moduli) > 1) && (count($primaFrazione) > 0)) {
            return array_values(array_filter([$primaFrazione, array_values(array_diff($moduli, $primaFrazione))], fn(array $gruppo) => count($gruppo) > 0));
        }
        $trasformati = array_filter($moduli, fn(int $modulo) => $quadri->get('VA001001', $modulo) !== null);
        if (count($trasformati) === 0) {
            return [$moduli];
        }
        $gruppi = [];
        $chiave = null;
        foreach ($moduli as $modulo) {
            $partitaIva = (string) $quadri->get('VA001001', $modulo);
            if (($chiave === null) || ($partitaIva !== $chiave)) {
                $gruppi[] = [];
                $chiave = $partitaIva;
            }
            $gruppi[array_key_last($gruppi)][] = $modulo;
        }
        return $gruppi;
    }

    /**
     * Quadro VT (modulo 1) per l'insieme dei moduli.
     *
     * @param int[] $moduli
     */
    private function proponiOperazioniVt(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione, array $moduli): void
    {
        $imponibile = $imposta = 0;
        foreach ($moduli as $modulo) {
            $imponibile += $quadri->getImporto('VE024001', $modulo);
            $imposta += $quadri->getImporto('VE026002', $modulo);
        }
        if ($imponibile === 0) {
            return;
        }
        $quadri->proponi('VT001001', $imponibile);
        $quadri->proponi('VT001002', $imposta);
        if ($this->vt['imponibile'] == 0) {
            $this->avvisa('Quadro VT: ripartire a mano le operazioni imponibili tra consumatori finali (campi 3 e 4, con la regione) e soggetti IVA (campi 5 e 6).');
            return;
        }
        $consumatori = (int) round($imponibile * $this->vt['consumatoriImponibile'] / $this->vt['imponibile']);
        $impostaConsumatori = $this->vt['imposta'] == 0 ? 0 : (int) round($imposta * $this->vt['consumatoriImposta'] / $this->vt['imposta']);
        $quadri->proponi('VT001003', $consumatori);
        $quadri->proponi('VT001004', $impostaConsumatori);
        $quadri->proponi('VT001005', $imponibile - $consumatori);
        $quadri->proponi('VT001006', $imposta - $impostaConsumatori);
        if ($consumatori === 0) {
            return;
        }
        // regioni dei registri collegati a un'unità locale, gli altri nel luogo di esercizio dell'attività
        $predefinito = RegioniVt::getRigo($this->getProvinciaEsercizio($dichiarazione->soggetto, $dichiarazione->anno));
        $perRigo = [];
        foreach ($this->vt['regioni'] as $chiave => $importi) {
            $rigo = $chiave === '' ? $predefinito : (RegioniVt::getRigo($chiave) ?? $predefinito);
            if ($rigo === null) {
                $this->avvisa('Quadro VT: luogo di esercizio dell\'attività non individuato nell\'Anagrafica (sede operativa, sede legale, domicilio fiscale o residenza) né sul registro IVA: indicare a mano la regione delle operazioni verso consumatori finali.');
                return;
            }
            $perRigo[$rigo]['imponibile'] = ($perRigo[$rigo]['imponibile'] ?? 0.0) + $importi['imponibile'];
            $perRigo[$rigo]['imposta'] = ($perRigo[$rigo]['imposta'] ?? 0.0) + $importi['imposta'];
        }
        ksort($perRigo);
        $restoImponibile = $consumatori;
        $restoImposta = $impostaConsumatori;
        $ultimo = array_key_last($perRigo);
        foreach ($perRigo as $rigo => $importi) {
            $imponibileRigo = $rigo === $ultimo ? $restoImponibile : (int) round($consumatori * $importi['imponibile'] / $this->vt['consumatoriImponibile']);
            $impostaRigo = $rigo === $ultimo ? $restoImposta : ($this->vt['consumatoriImposta'] == 0 ? 0 : (int) round($impostaConsumatori * $importi['imposta'] / $this->vt['consumatoriImposta']));
            $quadri->proponi(sprintf('VT%03d001', $rigo), $imponibileRigo);
            $quadri->proponi(sprintf('VT%03d002', $rigo), $impostaRigo);
            $restoImponibile -= $imponibileRigo;
            $restoImposta -= $impostaRigo;
        }
        $this->avvisa('Quadro VT: operazioni verso consumatori finali (senza partita IVA e corrispettivi) attribuite a ' . implode(', ', array_map(fn(int $rigo) => RegioniVt::RIGHI[$rigo][0], array_keys($perRigo)))
                . ' (unità locale del registro IVA o luogo di esercizio dell\'attività).');
    }

    private function accumulaRegione(?string $siglaProvincia, float $imponibile, float $imposta): void
    {
        $chiave = (string) $siglaProvincia;
        $this->vt['regioni'][$chiave]['imponibile'] = ($this->vt['regioni'][$chiave]['imponibile'] ?? 0.0) + $imponibile;
        $this->vt['regioni'][$chiave]['imposta'] = ($this->vt['regioni'][$chiave]['imposta'] ?? 0.0) + $imposta;
    }

    private function getProvinciaEsercizio(Soggetto $soggetto, int $anno): ?string
    {
        // le unità locali con un proprio registro IVA non sono il luogo di esercizio degli altri registri
        $collegate = [];
        foreach ((new RegistroIvaModel($this->dataMapper))->getElenco($soggetto) as $registro) {
            if ($registro->unitaLocale !== null) {
                $collegate[$registro->unitaLocale->id] = true;
            }
        }
        $indirizzi = [];
        foreach ((new IndirizzoModel($this->dataMapper))->getVigentiAl($soggetto, new SismaDate($anno . '-12-31')) as $indirizzo) {
            if (isset($collegate[$indirizzo->id]) === false) {
                $indirizzi[$indirizzo->tipoIndirizzo->value] = $indirizzo;
            }
        }
        foreach ([TipoIndirizzo::sedeOperativa, TipoIndirizzo::sedeLegale, TipoIndirizzo::domicilioFiscale, TipoIndirizzo::residenza] as $tipo) {
            if (isset($indirizzi[$tipo->value]) && ($indirizzi[$tipo->value]->comune?->siglaProvincia !== null)) {
                return $indirizzi[$tipo->value]->comune->siglaProvincia;
            }
        }
        return null;
    }

    /**
     * Quadro VC per gli esportatori abituali che hanno acquistato con la
     * dichiarazione d'intento (VF17): dati mensili dalla contabilità del
     * contribuente, colonne 5 e 6 (anno precedente) solo con il metodo
     * mensile, plafond disponibile al 1° gennaio e verifica mese per mese
     * del plafond utilizzato.
     */
    private function proponiPlafond(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione): void
    {
        if ($quadri->getImporto('VF017001') <= 0) {
            return;
        }
        $soggetto = $dichiarazione->soggetto;
        $anno = $dichiarazione->anno;
        $mensile = $quadri->get('VC014003') !== null;
        $corrente = $this->getDatiPlafond($soggetto, $anno);
        $precedente = $this->getDatiPlafond($soggetto, $anno - 1);
        $totali = array_fill(1, 6, 0);
        for ($mese = 1; $mese <= 12; $mese++) {
            $valori = [1 => $corrente['acquisti'][$mese], 2 => $corrente['importazioni'][$mese], 3 => $corrente['volume'][$mese], 4 => $corrente['esportazioni'][$mese],
                5 => $mensile ? $precedente['volume'][$mese] : 0, 6 => $mensile ? $precedente['esportazioni'][$mese] : 0];
            foreach ($valori as $colonna => $importo) {
                $quadri->proponi(sprintf('VC%03d%03d', $mese, $colonna), (int) round($importo));
                $totali[$colonna] += $quadri->getImporto(sprintf('VC%03d%03d', $mese, $colonna));
            }
        }
        foreach ($totali as $colonna => $totale) {
            $quadri->proponi(sprintf('VC013%03d', $colonna), $totale);
        }
        $esportazioniPrecedenti = array_sum($precedente['esportazioni']);
        // solare: operazioni dell'anno precedente; mensile: dodici mesi precedenti al netto degli acquisti con plafond degli stessi mesi (valido per gennaio)
        $plafond = (int) round($mensile ? $esportazioniPrecedenti - array_sum($precedente['acquisti']) - array_sum($precedente['importazioni']) : $esportazioniPrecedenti);
        $quadri->proponi('VC014001', max(0, $plafond));
        if ($mensile === false) {
            $quadri->proponi('VC014002', true);
        }
        $volumePrecedente = array_sum($precedente['volume']);
        if (($volumePrecedente > 0) && ($esportazioniPrecedenti <= $volumePrecedente * 0.10)) {
            $this->avvisa(sprintf('Quadro VC: nell\'anno precedente le operazioni che concorrono al plafond (€ %s) non superano il 10%% del volume d\'affari (€ %s): verificare lo status di esportatore abituale (art. 1, c. 1, lett. a), D.L. 746/1983).',
                            number_format($esportazioniPrecedenti, 0, ',', '.'), number_format($volumePrecedente, 0, ',', '.')));
        }
        foreach ($this->verificaPlafond($corrente, $precedente, $mensile, $quadri->getImporto('VC014001')) as $avviso) {
            $this->avvisa($avviso);
        }
        $this->avvisa(sprintf('Quadro VC (metodo %s): plafond utilizzato per mese dalla data dei documenti di acquisto con dichiarazione d\'intento (momento di effettuazione) e dalle importazioni, volume d\'affari ed esportazioni per mese dai registri; plafond disponibile al 1° gennaio € %s.%s',
                        $mensile ? 'mensile' : 'solare', number_format(max(0, $plafond), 0, ',', '.'), $mensile ? '' : ' Per il metodo mensile barrare la casella 3 del VC14 e riproporre.'));
    }

    /**
     * Superamenti del plafond disponibile mese per mese: con il metodo
     * solare il plafond dell'anno meno gli utilizzi progressivi; con il
     * metodo mensile le operazioni che concorrono al plafond nei dodici mesi
     * precedenti meno gli acquisti e le importazioni con plafond degli stessi
     * mesi.
     *
     * @return string[]
     */
    public static function verificaPlafond(array $corrente, array $precedente, bool $mensile, int $plafondAnnuale): array
    {
        $mesi = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
        $utilizzato = 0.0;
        for ($mese = 1; $mese <= 12; $mese++) {
            $utilizzoMese = $corrente['acquisti'][$mese] + $corrente['importazioni'][$mese];
            if ($mensile) {
                $disponibile = 0.0;
                for ($indietro = 1; $indietro <= 12; $indietro++) {
                    $riferimento = $mese - $indietro;
                    $dati = $riferimento >= 1 ? $corrente : $precedente;
                    $indice = $riferimento >= 1 ? $riferimento : $riferimento + 12;
                    $disponibile += $dati['esportazioni'][$indice] - $dati['acquisti'][$indice] - $dati['importazioni'][$indice];
                }
            } else {
                $disponibile = $plafondAnnuale - $utilizzato;
            }
            if (($utilizzoMese > 0) && ($utilizzoMese > $disponibile + 0.005)) {
                return [sprintf('Quadro VC: plafond superato a %s (utilizzati € %s, disponibili € %s): regolarizzare gli acquisti oltre il limite (circolare n. 50/E del 2002, risoluzione n. 16/E del 2017).',
                            $mesi[$mese - 1], number_format($utilizzoMese, 0, ',', '.'), number_format(max(0, $disponibile), 0, ',', '.'))];
            }
            $utilizzato += $utilizzoMese;
        }
        return [];
    }

    /**
     * @return array{acquisti: array<int, float>, importazioni: array<int, float>, volume: array<int, float>, esportazioni: array<int, float>}
     */
    private function getDatiPlafond(Soggetto $soggetto, int $anno): array
    {
        $dati = ['acquisti' => array_fill(1, 12, 0.0), 'importazioni' => array_fill(1, 12, 0.0), 'volume' => array_fill(1, 12, 0.0), 'esportazioni' => array_fill(1, 12, 0.0)];
        foreach ((new RigaIvaModel($this->dataMapper))->getPerPeriodo($soggetto, new SismaDate($anno . '-01-01'), new SismaDate($anno . '-12-31')) as $riga) {
            $natura = $riga->codiceIva->natura?->value;
            if ($riga->registroIva->tipo === TipoRegistroIva::acquisti) {
                if ($natura === 'N3.5') {
                    $data = $riga->registrazioneContabile->dataDocumento ?? $riga->dataCompetenzaIva;
                    if ((int) $data->format('Y') === $anno) {
                        $dati[$riga->registrazioneContabile->importazione ? 'importazioni' : 'acquisti'][(int) $data->format('n')] += $riga->imponibile;
                    }
                }
                continue;
            }
            $mese = (int) $riga->dataCompetenzaIva->format('n');
            // volume d'affari senza le operazioni dell'art. 21, c. 6-bis (non soggette), le escluse e le cessioni di beni ammortizzabili
            if ((($natura === null) || (in_array($natura, ['N1', 'N2.1', 'N2.2', 'N7'], true) === false)) && ($riga->qualifica !== QualificaOperazione::beneAmmortizzabile)) {
                $dati['volume'][$mese] += $riga->imponibile;
            }
            if (in_array($natura, self::NATURE_PLAFOND, true)) {
                $dati['esportazioni'][$mese] += $riga->imponibile;
            }
        }
        return $dati;
    }

    /**
     * Quadro VX (modulo 1) con i risultati di tutti i moduli.
     *
     * @param int[] $moduli
     */
    private function proponiRisultato(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione, array $moduli): void
    {
        $gruppi = new PropostaGruppoIva($this->dataMapper);
        $partecipazione = $gruppi->getPartecipazione($dichiarazione->soggetto, $dichiarazione->anno);
        if ($partecipazione !== null) {
            $quadri->proponi('VK001001', $partecipazione['gruppo']->controllante->partitaIva);
            $quadri->proponi('VK001002', $partecipazione['ultimoMese']);
        }
        [$imposta, $impostaGruppo] = self::calcolaImposteVx($quadri, $moduli, $dichiarazione);
        $quadri->proponi('VX001001', max(0, $imposta));
        $quadri->proponi('VX002001', max(0, -$imposta));
        if (($partecipazione !== null) && ($partecipazione['ultimoMese'] >= 12)) {
            $quadri->proponi('VX007001', max(0, $impostaGruppo));
            $quadri->proponi('VX008001', max(0, -$impostaGruppo));
            if ($impostaGruppo !== 0) {
                $this->avvisa(sprintf('IVA di gruppo: conguaglio annuale %s di € %s trasferito alla controllante (rigo %s), non versato dalla società.', $impostaGruppo > 0 ? 'a debito' : 'a credito',
                                number_format(abs($impostaGruppo), 0, ',', '.'), $impostaGruppo > 0 ? 'VX7' : 'VX8'));
            }
        }
        if ($partecipazione !== null) {
            $liquidazioni = (new LiquidazioneIvaModel($this->dataMapper))->getSequenza($dichiarazione->soggetto, $dichiarazione->anno);
            $gruppi->proponiVk($quadri, $dichiarazione, $partecipazione, $liquidazioni);
            if ($partecipazione['partecipante'] === null) {
                $gruppi->proponiProspetto($quadri, $dichiarazione, $partecipazione['gruppo']);
            }
        }
        $gruppi->proponiVg($quadri, $dichiarazione);
        array_push($this->avvisi, ...$gruppi->getAvvisi());
        // rimborso annuale: causale, erogazione prioritaria, esonero dalle garanzie
        $rimborso = new RimborsoIva($this->dataMapper);
        $rimborso->proponiVx4($quadri, $dichiarazione);
        array_push($this->avvisi, ...$rimborso->getAvvisi());
        $quadri->proponi('VX005001', max(0, self::getCreditoRipartibile($quadri) - $quadri->getImporto('VX004001') - $quadri->getImporto('VX006002')));
        if (($imposta > 0) && ($imposta <= 10)) {
            $this->avvisa('IVA da versare non superiore a 10,33 euro: il saldo non è dovuto.');
        }
    }

    /**
     * Credito da ripartire tra i righi VX4, VX5 e VX6: VX2 più VX3 o, con
     * l'ingresso in un Gruppo IVA, la differenza tra i campi 1 e 2 del VX2.
     */
    public static function getCreditoRipartibile(QuadriDichiarazione $quadri, ?int $modulo = null): int
    {
        return $quadri->getImporto('VX002002', $modulo) > 0 ? $quadri->getImporto('VX002001', $modulo) - $quadri->getImporto('VX002002', $modulo)
                : $quadri->getImporto('VX002001', $modulo) + $quadri->getImporto('VX003001', $modulo);
    }

    /**
     * IMPOSTA e IMPOSTA GRUPPO del paragrafo 2.9.1 delle specifiche per i
     * righi VX1, VX2, VX7 e VX8: con il quadro VK nel modulo 1 e il
     * controllo per tutto l'anno il risultato del primo modulo e dei moduli
     * delle società dello stesso gruppo va al gruppo; restano della società
     * i moduli dei soggetti esterni e i crediti non trasferibili (VL10,
     * VL11 e VL12 campo 2, VL40).
     *
     * @param int[] $moduli
     * @return array{0: int, 1: int}
     */
    public static function calcolaImposteVx(QuadriDichiarazione $quadri, array $moduli, ?Dichiarazione $dichiarazione = null): array
    {
        sort($moduli);
        $moduli = count($moduli) === 0 ? [1] : $moduli;
        $risultato = fn(int $modulo) => $quadri->getImporto('VL038001', $modulo) - $quadri->getImporto('VL039001', $modulo);
        $controllante = $quadri->get('VK001001', 1);
        if (($controllante !== null) && ($quadri->getImporto('VK001002', 1) >= 12)) {
            $esterni = $stessoGruppo = $usciti = $altroGruppo = 0;
            $nonTrasferibili = 0;
            foreach ($moduli as $modulo) {
                $nonTrasferibili += $quadri->getImporto('VL010001', $modulo) + $quadri->getImporto('VL011002', $modulo)
                        + $quadri->getImporto('VL012002', $modulo) + $quadri->getImporto('VL040001', $modulo);
                if (($modulo === 1) || ($quadri->get('VA001001', $modulo) === null)) {
                    continue;
                }
                $gruppo = $quadri->get('VK001001', $modulo);
                if ($gruppo === null) {
                    $esterni += $risultato($modulo);
                } elseif ($gruppo !== $controllante) {
                    $altroGruppo += $risultato($modulo);
                } elseif ($quadri->get('VK001004', $modulo) !== null) {
                    $usciti += $risultato($modulo);
                } else {
                    $stessoGruppo += $risultato($modulo);
                }
            }
            return [$esterni + $usciti + $altroGruppo - $nonTrasferibili, $risultato(1) + $stessoGruppo];
        }
        $concorsuale = in_array($dichiarazione?->codiceCarica?->value ?? $quadri->getDichiarazione()->codiceCarica?->value, ['3', '4'], true) && ($quadri->get('VA003001', 1) !== null);
        $imposta = 0;
        foreach ($moduli as $modulo) {
            if (($concorsuale === false) || ($modulo > 1)) {
                $imposta += $quadri->getImporto('VL038001', $modulo);
            }
            $imposta -= $quadri->getImporto('VL039001', $modulo) + $quadri->getImporto('VL040001', $modulo);
        }
        return [$imposta, 0];
    }

    /**
     * Visto di conformità proposto per il credito da compensare oltre 5.000
     * euro, senza sottoscrizione dell'organo di controllo: il professionista
     * dell'intermediario predefinito, se persona fisica.
     */
    private function proponiVisto(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione): void
    {
        if (($quadri->getImporto('VX005001') <= VersamentiIva::LIMITE_CREDITO_LIBERO) || $dichiarazione->hasVisto() || $dichiarazione->esoneroVisto) {
            return;
        }
        if (isset($dichiarazione->id) && (count((new SottoscrizioneControlloModel($this->dataMapper))->getSottoscrizioni($dichiarazione)) > 0)) {
            return;
        }
        $professionista = $dichiarazione->intermediario?->soggetto ?? (new IntermediarioModel($this->dataMapper))->getPredefinito($dichiarazione->studio)?->soggetto;
        if (($professionista !== null) && $professionista->isPersonaFisica()) {
            $dichiarazione->vistoProfessionista = $professionista;
            $this->avvisa('Credito da compensare oltre 5.000 euro: proposto il visto di conformità di ' . $professionista->nominativo . ' (frontespizio).');
        } else {
            $this->avvisa('Credito da compensare oltre 5.000 euro: indicare nel frontespizio il professionista che appone il visto di conformità o la sottoscrizione dell\'organo di controllo.');
        }
    }

    /**
     * Credito IVA dell'anno precedente nell'archivio dei crediti di
     * Deleghe (codice tributo 6099) e parte già utilizzata in F24.
     *
     * @return array{0: int, 1: int}
     */
    private function getCreditoAnnoPrecedente(Soggetto $soggetto, int $anno, SismaDate $entro): array
    {
        $creditoModel = new CreditoModel($this->dataMapper);
        $trimestrali = new CreditiTrimestrali($this->dataMapper);
        $importo = 0.0;
        $utilizzato = 0;
        foreach ($creditoModel->getElenco($soggetto) as $credito) {
            if (($credito->codiceTributo?->codice === '6099') && ($credito->annoRiferimento === $anno - 1)) {
                $importo += $credito->importo;
                $utilizzato += $trimestrali->getUtilizzatoEntro($credito, $entro);
            }
        }
        if ($importo > 0) {
            $this->avvisa(sprintf('Credito IVA %d di %s dall\'archivio dei crediti di Deleghe (rigo VL8), utilizzato in F24 per € %s (VL9) nelle deleghe trasmesse fino alla presentazione.',
                            $anno - 1, $soggetto->nominativo, number_format($utilizzato / 100, 2, ',', '.')));
        }
        return [(int) round($importo), (int) round($utilizzato / 100)];
    }

    /**
     * IVA periodica versata (rigo VL30, campo 3): importi a debito dei
     * versamenti periodici e dell'acconto dell'anno nelle deleghe
     * trasmesse o quietanzate, anche se compensati.
     */
    private function getVersamentiPeriodici(Dichiarazione $dichiarazione, Soggetto $soggetto): int
    {
        $versato = 0.0;
        $daVersare = false;
        $versamenti = (new VersamentoModel($this->dataMapper))->getPerPeriodo($dichiarazione->studio, new SismaDate($dichiarazione->anno . '-01-01'),
                new SismaDate(($dichiarazione->anno + 1) . '-12-31'), $soggetto);
        foreach ($versamenti as $versamento) {
            if (($versamento->annoRiferimento !== $dichiarazione->anno) || (in_array($versamento->codiceTributo?->codice, self::CODICI_VERSAMENTI_PERIODICI, true) === false)
                    || PropostaGruppoIva::isVersamentoGruppo($versamento->riferimentoOrigine)) {
                continue;
            }
            if (in_array($versamento->delega?->stato, [StatoDelega::trasmessa, StatoDelega::quietanzata], true)) {
                $versato += $versamento->importoDebito;
            } else {
                $daVersare = true;
            }
        }
        if ($daVersare) {
            $this->avvisa('Versamenti periodici dell\'anno ancora da versare o in deleghe non trasmesse né quietanzate: esclusi dal rigo VL30, campo 3.');
        }
        return (int) round($versato);
    }

    /**
     * Voce CEE delle immobilizzazioni immateriali (B.I) e materiali (B.II)
     * dell'attivo, i cui acquisti sono beni ammortizzabili (VF29, campo 1).
     */
    public static function isImmobilizzazione(?string $voceCee): bool
    {
        return RigaIva::isImmobilizzazione($voceCee);
    }

    private function accumula(array &$perAliquota, float $aliquota, float $importo): void
    {
        $chiave = $this->chiaveAliquota($aliquota);
        $perAliquota[$chiave] = ($perAliquota[$chiave] ?? 0.0) + $importo;
    }

    private function chiaveAliquota(float $aliquota): string
    {
        return rtrim(rtrim(number_format($aliquota, 2, '.', ''), '0'), '.');
    }

    private function avvisa(string $avviso): void
    {
        $avviso = mb_strtoupper(mb_substr($avviso, 0, 1)) . mb_substr($avviso, 1);
        if (in_array($avviso, $this->avvisi, true) === false) {
            $this->avvisi[] = $avviso;
        }
    }
}
