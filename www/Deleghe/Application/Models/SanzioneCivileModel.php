<?php

namespace Deleghe\Application\Models;

use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\SanzioneCivile;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class SanzioneCivileModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return SanzioneCivile::class;
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
