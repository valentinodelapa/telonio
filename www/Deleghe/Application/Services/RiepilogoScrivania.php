<?php

namespace Deleghe\Application\Services;

use CommSuite\Application\Entities\Studio;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Models\DelegaModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
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
        $delegaModel = new DelegaModel($this->dataMapper);
        return [
            'Da versare entro 30 giorni' => $delegaModel->contaDaVersareEntro($studio, new SismaDate('+30 days')),
            'In bozza' => $delegaModel->countByStudioAndStato($studio, StatoDelega::bozza),
            'Totale deleghe' => $delegaModel->countByStudio($studio),
        ];
    }
}
