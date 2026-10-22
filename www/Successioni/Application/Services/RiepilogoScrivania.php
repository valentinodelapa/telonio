<?php

namespace Successioni\Application\Services;

use CommSuite\Application\Entities\Studio;
use Successioni\Application\Enumerations\StatoDichiarazioneSuccessione;
use Successioni\Application\Models\DichiarazioneSuccessioneModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

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
        $model = new DichiarazioneSuccessioneModel($this->dataMapper);
        return [
            'Dichiarazioni in bozza' => $model->countByStudioAndStato($studio, StatoDichiarazioneSuccessione::bozza),
            'Dichiarazioni presentate' => $model->countByStudioAndStato($studio, StatoDichiarazioneSuccessione::presentata),
        ];
    }
}
