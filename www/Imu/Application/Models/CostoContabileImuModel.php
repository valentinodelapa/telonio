<?php

namespace Imu\Application\Models;

use Anagrafica\Application\Entities\Immobile;
use Imu\Application\Entities\CostoContabileImu;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class CostoContabileImuModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return CostoContabileImu::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(Immobile $immobile): SismaCollection
    {
        return $this->getByImmobile($immobile, null, ['anno' => 'ASC', 'id' => 'ASC']);
    }
}
