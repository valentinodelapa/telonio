<?php

namespace Imu\Application\Models;

use Anagrafica\Application\Entities\Immobile;
use Imu\Application\Entities\CaratteristicheImu;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class CaratteristicheImuModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return CaratteristicheImu::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getStorico(Immobile $immobile): SismaCollection
    {
        return $this->getByImmobile($immobile, null, ['validoDal' => 'DESC']);
    }

    public function getVigenteAl(Immobile $immobile, \DateTimeInterface $data): ?CaratteristicheImu
    {
        foreach ($this->getStorico($immobile) as $caratteristiche) {
            if ($caratteristiche->isVigenteAl($data)) {
                return $caratteristiche;
            }
        }
        return null;
    }
}
