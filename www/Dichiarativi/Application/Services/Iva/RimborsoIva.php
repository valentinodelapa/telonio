<?php

namespace Dichiarativi\Application\Services\Iva;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Models\CampoModelloModel;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Models\SottoscrizioneControlloModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Rimborso annuale del credito IVA (rigo VX4 e quadro VS, campi 9-12, del
 * prospetto IVA 26 PR), dalle istruzioni della dichiarazione IVA:
 * - causale (VX4 campo 3): 1 cessazione dell'attività; 2 aliquota media,
 *   3 operazioni non imponibili oltre il 25%, 4 beni ammortizzabili, 5
 *   operazioni non soggette prevalenti (art. 30, c. 2, con un credito
 *   oltre 2.582,28 euro), calcolate dalle righe IVA dell'anno come i
 *   presupposti del modello IVA TR; 8 minore eccedenza detraibile del
 *   triennio dalle dichiarazioni dei due anni precedenti presenti nella
 *   suite; 9 se ricorrono la 8 e la 4. Le altre causali (6 non residenti,
 *   7 produttori agricoli, 10, 11, 13) restano da indicare a mano;
 * - erogazione prioritaria (campo 4): 6 per le operazioni in scissione
 *   dei pagamenti (con l'imposta nel campo 5), 1 e 8 per il reverse charge
 *   del subappalto edile e dei servizi connessi agli edifici; i codici
 *   per codice di attività restano a mano;
 * - esonero dalle garanzie (campo 7) e attestazione patrimoniale (campo
 *   10) per i rimborsi oltre 30.000 euro.
 */
class RimborsoIva
{

    /** Credito oltre cui competono i rimborsi dell'art. 30, c. 2 */
    public const SOGLIA = 2582.28;

    /** Rimborsi oltre cui servono garanzia o visto e attestazione patrimoniale (art. 38-bis) */
    public const LIMITE_GARANZIA = 30000;

    /** Causali per presupposto del modello IVA TR */
    private const CAUSALI_TR = ['TD001001' => '2', 'TD002001' => '3', 'TD005001' => '5'];

    /** @var string[] */
    private array $avvisi = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return string[]
     */
    public function getAvvisi(): array
    {
        return $this->avvisi;
    }

    /**
     * Causale, erogazione prioritaria, esonero e attestazione del rigo VX4
     * quando è indicato l'importo da chiedere a rimborso; altrimenti un
     * avviso se il credito si potrebbe chiedere a rimborso.
     */
    public function proponiVx4(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione): void
    {
        $credito = $quadri->getImporto('VX002001', 1);
        $rimborso = $quadri->getImporto('VX004001', 1);
        if ($credito <= 0) {
            return;
        }
        $presupposti = $this->getPresupposti($dichiarazione->soggetto, $dichiarazione->anno, $credito, $quadri);
        if ($rimborso <= 0) {
            if ($presupposti['causale'] !== null) {
                $this->avvisi[] = sprintf('Il credito di € %s si può chiedere a rimborso (causale %s%s): indicare l\'importo nel rigo VX4.', number_format($credito, 0, ',', '.'),
                        $presupposti['causale'], $presupposti['limite'] === null ? '' : ', fino a € ' . number_format($presupposti['limite'], 0, ',', '.'));
            }
            return;
        }
        $quadri->proponi('VX004003', $presupposti['causale'], 1);
        $quadri->proponi('VX004004', $presupposti['priorita'], 1);
        $quadri->proponi('VX004005', $presupposti['priorita'] === '6' ? $presupposti['scissione'] : null, 1);
        if ($presupposti['causale'] === null) {
            $this->avvisi[] = 'Rigo VX4: nessuna causale di rimborso risulta dalla contabilità e dalle dichiarazioni nella suite; indicarla a mano (campo 3).';
        } elseif (($presupposti['limite'] !== null) && ($rimborso > $presupposti['limite'])) {
            $this->avvisi[] = sprintf('Rigo VX4: con la causale %s il rimborso compete fino a € %s.', $presupposti['causale'], number_format($presupposti['limite'], 0, ',', '.'));
        }
        array_push($this->avvisi, ...$presupposti['avvisi']);
        // oltre 30.000 euro: esonero dalla garanzia con visto e attestazione patrimoniale
        $oltreLimite = $rimborso > self::LIMITE_GARANZIA;
        $sottoscritta = $this->isSottoscritta($dichiarazione);
        $quadri->proponi('VX004007', $oltreLimite ? $dichiarazione->getCodiceEsoneroGaranzie(['visto' => '1', 'collaborativo' => '4'], $sottoscritta) : null, 1);
        $attestazione = $oltreLimite && $dichiarazione->attestazionePatrimoniale && ($dichiarazione->hasVisto() || $sottoscritta);
        foreach (['VX00410A', 'VX00410B', 'VX00410C', 'VX00410D'] as $codice) {
            $quadri->proponi($codice, $attestazione ? true : null, 1);
        }
    }

    /**
     * Causale e erogazione prioritaria del rimborso di una società del
     * gruppo (quadro VS, campi 9, 11 e 12), con la sua eccedenza di credito.
     *
     * @return array{causale: ?string, limite: ?int, priorita: ?string, scissione: int, avvisi: string[]}
     */
    public function getPresuppostiSocieta(Soggetto $soggetto, int $anno, int $credito): array
    {
        $presupposti = $this->getPresupposti($soggetto, $anno, $credito, null);
        // nel quadro VS le causali sono quelle dell'art. 30, c. 2 (1-7)
        if (in_array($presupposti['causale'], ['8', '9'], true)) {
            $presupposti['causale'] = $presupposti['tr'] ?? null;
        }
        return $presupposti;
    }

    /**
     * Presupposti del rimborso annuale dalle righe IVA dell'anno e dalle
     * dichiarazioni precedenti.
     *
     * @return array{causale: ?string, limite: ?int, priorita: ?string, scissione: int, avvisi: string[], tr: ?string}
     */
    public function getPresupposti(Soggetto $soggetto, int $anno, int $credito, ?QuadriDichiarazione $quadri): array
    {
        $risultato = ['causale' => null, 'limite' => null, 'priorita' => null, 'scissione' => 0, 'avvisi' => [], 'tr' => null];
        $fine = new SismaDate($anno . '-12-31');
        if (($soggetto->dataCessazione !== null) && ((int) $soggetto->dataCessazione->format('Y') === $anno) && $soggetto->isCessato($fine)) {
            $risultato['causale'] = '1';
            return $risultato;
        }
        $prova = $this->getProva($soggetto, $anno);
        $causali = [];
        $ammortizzabili = null;
        if ($prova !== null) {
            $dati = (new PropostaTr($this->dataMapper))->proponiPeriodo($prova, $soggetto, $anno, new SismaDate($anno . '-01-01'), $fine, '');
            foreach (self::CAUSALI_TR as $presupposto => $causale) {
                if (in_array($presupposto, $dati['presupposti'], true)) {
                    $causali[] = $causale;
                }
            }
            // operazioni non imponibili sul totale dei righi VE40 e VE50 della dichiarazione
            if (($quadri !== null) && (in_array('3', $causali, true) === false)) {
                $totale = abs($quadri->getImporto('VE040001', 1)) + abs($quadri->getImporto('VE050001', 1));
                $nonImponibili = $quadri->getImporto('VE030001', 1) + $quadri->getImporto('VE031001', 1);
                if (($totale > 0) && ((int) ceil(round($nonImponibili * 100 / $totale, 6)) > 25)) {
                    $causali[] = '3';
                }
            }
            if ($dati['ammortizzabili'] > 0) {
                $ammortizzabili = (int) round($dati['ammortizzabili']);
            }
            $risultato['priorita'] = $prova->get('TD008001');
            $risultato['scissione'] = (int) round($dati['scissione']);
        } else {
            $risultato['avvisi'][] = 'Presupposti del rimborso non calcolati: manca il catalogo del modello IVA TR (eseguire sisma aggiorna-database).';
        }
        sort($causali);
        $sopraSoglia = $credito > self::SOGLIA;
        $risultato['tr'] = $sopraSoglia ? ($causali[0] ?? ($ammortizzabili !== null ? '4' : null)) : null;
        $triennio = $quadri === null ? null : $this->getMinoreEccedenza($soggetto, $anno, $credito, $quadri);
        if ($sopraSoglia && (count($causali) > 0)) {
            $risultato['causale'] = $causali[0];
        } elseif ($sopraSoglia && ($ammortizzabili !== null)) {
            // beni ammortizzabili: nei limiti dell'imposta sugli acquisti, con la minore eccedenza del triennio codice 9
            $risultato['causale'] = $triennio !== null ? '9' : '4';
            $risultato['limite'] = $triennio !== null ? $ammortizzabili + $triennio : $ammortizzabili;
        } elseif ($triennio !== null) {
            $risultato['causale'] = '8';
            $risultato['limite'] = $triennio;
        }
        if (($risultato['causale'] !== null) && ($risultato['limite'] !== null)) {
            $risultato['limite'] = min($risultato['limite'], $credito);
        }
        return $risultato;
    }

    /**
     * Minore eccedenza detraibile del triennio (causale 8): credito
     * riportato dai due anni precedenti (VX5 meno il credito compensato in
     * F24 nell'anno successivo, VL9) e credito dell'anno (VX2).
     */
    public function getMinoreEccedenza(Soggetto $soggetto, int $anno, int $credito, QuadriDichiarazione $quadri): ?int
    {
        $model = new DichiarazioneModel($this->dataMapper);
        $precedente = $model->getPerModello($soggetto, ModelloDichiarativo::iva, $anno - 1);
        $secondo = $model->getPerModello($soggetto, ModelloDichiarativo::iva, $anno - 2);
        if (($precedente === null) || ($secondo === null)) {
            return null;
        }
        $quadriPrecedente = new QuadriDichiarazione($precedente, $this->dataMapper);
        $quadriSecondo = new QuadriDichiarazione($secondo, $this->dataMapper);
        $eccedenze = [
            $quadriSecondo->getImporto('VX005001', 1) - $quadriPrecedente->getImporto('VL009001', 1),
            $quadriPrecedente->getImporto('VX005001', 1) - $quadri->getImporto('VL009001', 1),
            $credito,
        ];
        return min($eccedenze) > 0 ? min($eccedenze) : null;
    }

    /**
     * Quadri di prova del modello IVA TR (non salvati) per i presupposti
     * dell'anno.
     */
    private function getProva(Soggetto $soggetto, int $anno): ?QuadriDichiarazione
    {
        $catalogo = (new CampoModelloModel($this->dataMapper))->getCatalogo(ModelloDichiarativo::ivaTr, ModelloDichiarativo::getAnnoModello($anno, ModelloDichiarativo::ivaTr));
        if (isset($catalogo['TD001001']) === false) {
            return null;
        }
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->soggetto = $soggetto;
        $dichiarazione->modello = ModelloDichiarativo::ivaTr;
        $dichiarazione->anno = $anno;
        return new QuadriDichiarazione($dichiarazione, $this->dataMapper, $catalogo);
    }

    private function isSottoscritta(Dichiarazione $dichiarazione): bool
    {
        return isset($dichiarazione->id) && (count((new SottoscrizioneControlloModel($this->dataMapper))->getSottoscrizioni($dichiarazione)) > 0);
    }
}
