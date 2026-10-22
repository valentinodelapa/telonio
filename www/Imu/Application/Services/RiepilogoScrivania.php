<?php

namespace Imu\Application\Services;

use CommSuite\Application\Entities\Studio;
use Imu\Application\Models\CalcoloImuModel;
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
        $anno = (int) date('Y');
        $calcoloModel = new CalcoloImuModel($this->dataMapper);
        return [
            'Calcoli ' . $anno => $calcoloModel->contaPerAnno($studio, $anno),
            'Calcoli ' . ($anno - 1) => $calcoloModel->contaPerAnno($studio, $anno - 1),
        ];
    }
}
