<?php

namespace GestioneStudio\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use CommSuite\Application\Entities\Studio;
use CommSuite\Application\HelperClasses\Calendario;
use GestioneStudio\Application\Entities\Adempimento;
use GestioneStudio\Application\Entities\ProrogaAdempimento;
use GestioneStudio\Application\Entities\TermineAdempimento;
use GestioneStudio\Application\Enumerations\CondizioneProroga;
use GestioneStudio\Application\Models\ProrogaAdempimentoModel;
use GestioneStudio\Application\Models\TermineAdempimentoModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Termini effettivi degli adempimenti: termine ordinario dell'anno di
 * riferimento, sostituito dalla proroga dell'anno (dello studio, altrimenti
 * del calendario fiscale; quelle condizionate solo per i soggetti con i
 * requisiti), poi differito al 20 agosto e al primo giorno
 * lavorativo secondo l'adempimento (Calendario::termineFiscale).
 */
class CalendarioAdempimenti
{

    /** @var array<int, TermineAdempimento[]> */
    private array $termini = [];

    /** @var array<string, ProrogaAdempimento[]> */
    private array $proroghe = [];

    private RequisitiProroga $requisiti;

    public function __construct(private Studio $studio, private DataMapper $dataMapper = new DataMapper())
    {
        $this->requisiti = new RequisitiProroga($dataMapper);
    }

    /**
     * @return TermineAdempimento[]
     */
    public function getTerminiOrdinari(Adempimento $adempimento): array
    {
        return $this->termini[$adempimento->id] ??= (new TermineAdempimentoModel($this->dataMapper))->getTermini($adempimento);
    }

    /**
     * Proroga da applicare: la prima, in ordine di precedenza, i cui
     * requisiti sono soddisfatti dal soggetto alla fine dell'anno di
     * riferimento. Senza soggetto valgono solo le proroghe generali.
     */
    public function getProroga(TermineAdempimento $termine, int $anno, ?Soggetto $soggetto = null): ?ProrogaAdempimento
    {
        $proroghe = $this->proroghe[$termine->id . '/' . $anno] ??= (new ProrogaAdempimentoModel($this->dataMapper))->getApplicabili($termine, $anno, $this->studio);
        $fineAnno = new SismaDate($anno . '-12-31');
        foreach ($proroghe as $proroga) {
            if ($proroga->condizione === CondizioneProroga::tutti) {
                return $proroga;
            }
            if (($soggetto !== null) && $this->requisiti->isSoddisfatta($proroga->condizione, $soggetto, $fineAnno)) {
                return $proroga;
            }
        }
        return null;
    }

    public function getTermine(TermineAdempimento $termine, int $anno, ?Soggetto $soggetto = null): TermineEffettivo
    {
        $ordinario = $termine->getTermineOrdinario($anno);
        $proroga = $this->getProroga($termine, $anno, $soggetto);
        $base = $proroga?->data ?? $ordinario;
        $adempimento = $termine->adempimento;
        $data = $adempimento->slittamentoFestivi ? Calendario::termineFiscale($base, $adempimento->prorogaAgosto) : $base;
        return new TermineEffettivo($termine, $anno, $data, $ordinario, $proroga);
    }

    /**
     * Termini dell'adempimento con data effettiva nel periodo, per data.
     *
     * @return TermineEffettivo[]
     */
    public function getTermini(Adempimento $adempimento, SismaDate $dal, SismaDate $al, ?Soggetto $soggetto = null): array
    {
        $termini = $this->getTerminiOrdinari($adempimento);
        if (count($termini) === 0) {
            return [];
        }
        $scarto = max(array_map(fn(TermineAdempimento $termine) => $termine->anniSuccessivi, $termini));
        $effettivi = [];
        for ($anno = (int) $dal->format('Y') - $scarto - 1; $anno <= (int) $al->format('Y'); $anno++) {
            foreach ($termini as $termine) {
                $effettivo = $this->getTermine($termine, $anno, $soggetto);
                $giorno = $effettivo->data->format('Y-m-d');
                if (($giorno >= $dal->format('Y-m-d')) && ($giorno <= $al->format('Y-m-d'))) {
                    $effettivi[] = $effettivo;
                }
            }
        }
        usort($effettivi, fn(TermineEffettivo $a, TermineEffettivo $b) => [$a->data->format('Y-m-d'), $a->termine->ordine] <=> [$b->data->format('Y-m-d'), $b->termine->ordine]);
        return $effettivi;
    }

    /**
     * Termini dell'adempimento per l'anno di riferimento, per ordine.
     *
     * @return TermineEffettivo[]
     */
    public function getTerminiAnno(Adempimento $adempimento, int $anno): array
    {
        return array_map(fn(TermineAdempimento $termine) => $this->getTermine($termine, $anno), $this->getTerminiOrdinari($adempimento));
    }
}
