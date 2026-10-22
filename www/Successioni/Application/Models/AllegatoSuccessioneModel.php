<?php

namespace Successioni\Application\Models;

use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;
use Successioni\Application\Entities\AllegatoSuccessione;
use Successioni\Application\Entities\DichiarazioneSuccessione;

class AllegatoSuccessioneModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return AllegatoSuccessione::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(DichiarazioneSuccessione $dichiarazione): SismaCollection
    {
        return $this->getByDichiarazioneSuccessione($dichiarazione, null, ['tipoAllegato' => 'ASC', 'id' => 'ASC']);
    }
}
