<?php

namespace Successioni\Application\Models;

use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Entities\EstremoVoltura;

class EstremoVolturaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return EstremoVoltura::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(BeneSuccessione $bene): SismaCollection
    {
        return $this->getByBeneSuccessione($bene, null, ['tipo' => 'DESC', 'id' => 'ASC']);
    }
}
