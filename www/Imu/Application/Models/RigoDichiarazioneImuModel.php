<?php

namespace Imu\Application\Models;

use Imu\Application\Entities\DichiarazioneImu;
use Imu\Application\Entities\RigoDichiarazioneImu;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class RigoDichiarazioneImuModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return RigoDichiarazioneImu::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getRighi(DichiarazioneImu $dichiarazione): SismaCollection
    {
        return $this->getByDichiarazioneImu($dichiarazione, null, ['quadro' => 'ASC', 'numeroOrdine' => 'ASC', 'id' => 'ASC']);
    }
}
