<?php

namespace Dichiarativi\Application\Services;

use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Models\DichiarazioneModel;
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
        $anno = (int) date('Y') - 1;
        $model = new DichiarazioneModel($this->dataMapper);
        return [
            'Dichiarazioni IVA ' . ($anno + 1) => $model->contaPerAnno($studio, $anno, ModelloDichiarativo::iva),
            'Dichiarazioni ' . ($anno + 1) => $model->contaPerAnno($studio, $anno),
        ];
    }
}
