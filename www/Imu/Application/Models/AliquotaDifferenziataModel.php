<?php

namespace Imu\Application\Models;

use Imu\Application\Entities\AliquotaDifferenziata;
use Imu\Application\Entities\ProspettoAliquote;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class AliquotaDifferenziataModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return AliquotaDifferenziata::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(ProspettoAliquote $prospetto): SismaCollection
    {
        return $this->getByProspettoAliquote($prospetto, null, ['ordine' => 'ASC', 'id' => 'ASC']);
    }
}
