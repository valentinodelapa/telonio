<?php

namespace Deleghe\Application\Models;

use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Ravvedimento;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class RavvedimentoModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Ravvedimento::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(Delega $delega): SismaCollection
    {
        return $this->getByDelega($delega, null, ['id' => 'ASC']);
    }
}
