<?php

namespace Contabilita\Application\Services;

use Contabilita\Application\Models\FatturaElettronicaModel;
use Contabilita\Application\Models\LiquidazioneIvaModel;
use Contabilita\Application\Models\PianoContiModel;
use SismaFramework\Orm\HelperClasses\DataMapper;
use Telonio\Application\Entities\Studio;

/**
 * Indicatori del modulo mostrati nella scrivania della suite.
 */
class RiepilogoScrivania
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public function getIndicatori(Studio $studio): array
    {
        return [
            'Aziende in contabilità' => (new PianoContiModel($this->dataMapper))->contaAziende($studio),
            'Fatture elettroniche da registrare' => (new FatturaElettronicaModel($this->dataMapper))->contaDaRegistrare($studio),
            'Liquidazioni IVA ' . date('Y') => (new LiquidazioneIvaModel($this->dataMapper))->contaPerAnno($studio, (int) date('Y')),
        ];
    }
}
