<?php

namespace Deleghe\Application\Models;

use CommSuite\Application\Entities\Studio;
use Deleghe\Application\Entities\InvioTelematico;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class InvioTelematicoModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return InvioTelematico::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(Studio $studio, int $limite = 50): SismaCollection
    {
        return $this->getByStudio($studio, null, ['dataCreazione' => 'DESC'], 0, $limite);
    }
}
