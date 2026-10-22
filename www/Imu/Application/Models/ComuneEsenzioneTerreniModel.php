<?php

namespace Imu\Application\Models;

use Imu\Application\Entities\ComuneEsenzioneTerreni;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class ComuneEsenzioneTerreniModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return ComuneEsenzioneTerreni::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getPerComune(string $codiceCatastale): SismaCollection
    {
        return $this->getByCodiceCatastale($codiceCatastale);
    }
}
