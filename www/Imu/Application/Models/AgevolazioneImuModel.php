<?php

namespace Imu\Application\Models;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Soggetto;
use Imu\Application\Entities\AgevolazioneImu;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class AgevolazioneImuModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return AgevolazioneImu::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getStorico(Soggetto $soggetto, Immobile $immobile): SismaCollection
    {
        return $this->getBySoggettoAndImmobile($soggetto, $immobile, null, ['validoDal' => 'DESC']);
    }

    public function getVigenteAl(Soggetto $soggetto, Immobile $immobile, \DateTimeInterface $data): ?AgevolazioneImu
    {
        foreach ($this->getStorico($soggetto, $immobile) as $agevolazione) {
            if ($agevolazione->isVigenteAl($data)) {
                return $agevolazione;
            }
        }
        return null;
    }
}
