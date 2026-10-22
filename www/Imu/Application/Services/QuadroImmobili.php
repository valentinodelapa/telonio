<?php

namespace Imu\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Models\DatiCatastaliModel;
use Anagrafica\Application\Models\PossessoModel;
use Imu\Application\Models\AgevolazioneImuModel;
use Imu\Application\Models\AliquotaApplicataModel;
use Imu\Application\Models\CaratteristicheImuModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Immobili dell'Anagrafica posseduti dal contribuente nell'anno, con i
 * dati che entrano nel calcolo: possessi, classamento, caratteristiche e
 * agevolazioni IMU, aliquota scelta e prospetto del comune.
 */
class QuadroImmobili
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return array<int, array> per id dell'immobile
     */
    public function getImmobili(Soggetto $soggetto, int $anno): array
    {
        $inizio = $anno . '-01-01';
        $fine = $anno . '-12-31';
        $aliquote = new AliquoteComune($soggetto->studio, $this->dataMapper);
        $aliquoteApplicate = (new AliquotaApplicataModel($this->dataMapper))->getPerImmobile($soggetto, $anno);
        $immobili = [];
        foreach ((new PossessoModel($this->dataMapper))->getStoricoSoggetto($soggetto) as $possesso) {
            if (($possesso->validoDal->format('Y-m-d') > $fine) || (($possesso->validoAl !== null) && ($possesso->validoAl->format('Y-m-d') < $inizio))) {
                continue;
            }
            $immobile = $possesso->immobile;
            if (isset($immobili[$immobile->id]) === false) {
                $alFine = min($fine, $possesso->validoAl?->format('Y-m-d') ?? $fine);
                $immobili[$immobile->id] = [
                    'immobile' => $immobile,
                    'possessi' => [],
                    'datiCatastali' => (new DatiCatastaliModel($this->dataMapper))->getVigenteAl($immobile, new \DateTimeImmutable($alFine)),
                    'caratteristiche' => self::nelPeriodo((new CaratteristicheImuModel($this->dataMapper))->getStorico($immobile), $inizio, $fine),
                    'agevolazioni' => self::nelPeriodo((new AgevolazioneImuModel($this->dataMapper))->getStorico($soggetto, $immobile), $inizio, $fine),
                    'aliquotaApplicata' => $aliquoteApplicate[$immobile->id] ?? null,
                    'prospetto' => $aliquote->getProspetto($immobile->comune->codiceCatastale, $anno),
                ];
            }
            $immobili[$immobile->id]['possessi'][] = $possesso;
        }
        uasort($immobili, fn(array $primo, array $secondo) => [$primo['immobile']->comune->denominazione, $primo['immobile']->tipoCatasto->value, $primo['immobile']->id] <=> [$secondo['immobile']->comune->denominazione, $secondo['immobile']->tipoCatasto->value, $secondo['immobile']->id]);
        return $immobili;
    }

    private static function nelPeriodo(iterable $storico, string $inizio, string $fine): array
    {
        $versioni = [];
        foreach ($storico as $versione) {
            if (($versione->validoDal->format('Y-m-d') <= $fine) && (($versione->validoAl === null) || ($versione->validoAl->format('Y-m-d') >= $inizio))) {
                $versioni[] = $versione;
            }
        }
        return $versioni;
    }
}
