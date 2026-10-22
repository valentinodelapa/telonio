<?php

namespace Imu\Application\Services;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Entities\Immobile;
use Imu\Application\Models\CoefficienteFabbricatiDModel;
use Imu\Application\Models\CostoContabileImuModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Valore IMU dell'immobile per intero (art. 1, commi 745-746, L.
 * 160/2019), usato dal calcolo e dalla dichiarazione:
 * - fabbricati: rendita rivalutata del 5% per il moltiplicatore;
 * - terreni: reddito dominicale rivalutato del 25% per 135;
 * - fabbricati del gruppo D a valore contabile: costi per i coefficienti
 *   del decreto annuale; il coefficiente del 1982 vale anche per i costi
 *   precedenti; i costi dell'anno contano solo se l'immobile è acquisito
 *   nell'anno (valore al 1° gennaio o alla data di acquisizione).
 */
class ValoreImmobile
{

    private const RIVALUTAZIONE_RENDITA = 1.05;
    private const RIVALUTAZIONE_REDDITO_DOMINICALE = 1.25;
    private const MOLTIPLICATORE_TERRENI = 135;
    private const PRIMO_ANNO_COEFFICIENTI = 1982;
    private const ANNI_RIPIEGO_COEFFICIENTI = 3;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public static function fabbricato(DatiCatastali $datiCatastali): float
    {
        return $datiCatastali->categoria === null ? 0.0 : round((float) $datiCatastali->rendita * self::RIVALUTAZIONE_RENDITA * CalcoloImposta::getMoltiplicatore($datiCatastali->categoria), 2);
    }

    public static function terreno(DatiCatastali $datiCatastali): float
    {
        return round((float) $datiCatastali->redditoDominicale * self::RIVALUTAZIONE_REDDITO_DOMINICALE * self::MOLTIPLICATORE_TERRENI, 2);
    }

    /**
     * @return array{valore: float, annoDecreto: ?int, costi: int} annoDecreto
     *         = anno dei coefficienti applicati (null se non in archivio)
     */
    public function contabile(Immobile $immobile, int $anno, bool $acquistoNellAnno): array
    {
        $coefficienteModel = new CoefficienteFabbricatiDModel($this->dataMapper);
        $annoDecreto = $anno;
        $coefficienti = $coefficienteModel->getPerAnno($anno);
        while ((count($coefficienti) === 0) && ($annoDecreto > $anno - self::ANNI_RIPIEGO_COEFFICIENTI)) {
            $coefficienti = $coefficienteModel->getPerAnno(--$annoDecreto);
        }
        $valore = 0.0;
        $costi = 0;
        foreach ((new CostoContabileImuModel($this->dataMapper))->getElenco($immobile) as $costo) {
            if (($costo->anno < $anno) || (($costo->anno === $anno) && $acquistoNellAnno)) {
                $annoCoefficiente = min(max($costo->anno, self::PRIMO_ANNO_COEFFICIENTI), $annoDecreto);
                $valore += $costo->importo * ($coefficienti[$annoCoefficiente] ?? 1.0);
                $costi++;
            }
        }
        return ['valore' => round($valore, 2), 'annoDecreto' => count($coefficienti) > 0 ? $annoDecreto : null, 'costi' => $costi];
    }
}
