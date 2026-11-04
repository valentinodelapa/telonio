<?php

namespace Dichiarativi\Application\Services\Iva;

use Anagrafica\Application\Entities\Soggetto;
use Deleghe\Application\Models\CreditoModel;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Enumerations\StatoDichiarazione;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Crediti IVA trimestrali dei modelli IVA TR presentati nell'anno, per la
 * dichiarazione annuale: rimborsi infrannuali richiesti (VL20, VW20 e VS
 * campo 5 del gruppo) e crediti dei primi tre trimestri compensati in F24
 * (VL22, VW22) dagli utilizzi dei crediti 6036-6038 dell'archivio di
 * Deleghe.
 */
class CreditiTrimestrali
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Rimborsi infrannuali richiesti con i modelli IVA TR presentati (TD6
     * del soggetto, TE50 del gruppo per la controllante).
     */
    public function getRimborsiRichiesti(Soggetto $soggetto, int $anno): int
    {
        $totale = 0.0;
        foreach ($this->getQuadriPresentati($soggetto, $anno) as $quadri) {
            $totale += $quadri->getDecimale('TD006001', 1) + $quadri->getDecimale('TE050001', 1);
        }
        return (int) round($totale);
    }

    /**
     * Rimborso infrannuale chiesto dalla controllante per una società del
     * gruppo (rigo TD9 dei moduli del prospetto TE).
     */
    public function getRimborsoSocieta(Soggetto $controllante, int $anno, ?string $partitaIva): int
    {
        $totale = 0.0;
        foreach ($this->getQuadriPresentati($controllante, $anno) as $quadri) {
            foreach ($quadri->getModuli() as $modulo) {
                if (($partitaIva !== null) && ($quadri->get('TD009001', $modulo) === $partitaIva)) {
                    $totale += $quadri->getDecimale('TD009002', $modulo);
                }
            }
        }
        return (int) round($totale);
    }

    /**
     * Crediti dei primi tre trimestri utilizzati in compensazione nelle
     * deleghe F24.
     */
    public function getCompensati(Soggetto $soggetto, int $anno): int
    {
        $model = new CreditoModel($this->dataMapper);
        $centesimi = 0;
        foreach ($model->getElenco($soggetto) as $credito) {
            if (($credito->annoRiferimento === $anno) && in_array($credito->codiceTributo?->codice, VersamentiTr::CODICI, true)) {
                $centesimi += $model->getUtilizzato($credito);
            }
        }
        return (int) round($centesimi / 100);
    }

    /**
     * @return QuadriDichiarazione[]
     */
    private function getQuadriPresentati(Soggetto $soggetto, int $anno): array
    {
        $elenco = [];
        foreach ((new DichiarazioneModel($this->dataMapper))->getTrimestrali($soggetto, ModelloDichiarativo::ivaTr, $anno) as $dichiarazione) {
            if ($dichiarazione->stato === StatoDichiarazione::presentata) {
                $elenco[] = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
            }
        }
        return $elenco;
    }
}
