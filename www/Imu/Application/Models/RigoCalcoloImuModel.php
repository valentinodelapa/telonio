<?php

namespace Imu\Application\Models;

use Imu\Application\Entities\CalcoloImu;
use Imu\Application\Entities\RigoCalcoloImu;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class RigoCalcoloImuModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return RigoCalcoloImu::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getRighi(CalcoloImu $calcolo): SismaCollection
    {
        return $this->getByCalcoloImu($calcolo, null, ['id' => 'ASC']);
    }
}
