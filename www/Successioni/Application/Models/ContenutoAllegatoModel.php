<?php

namespace Successioni\Application\Models;

use SismaFramework\Orm\BaseClasses\BaseModel;
use SismaFramework\Orm\HelperClasses\Query;
use Successioni\Application\Entities\ContenutoAllegato;

class ContenutoAllegatoModel extends BaseModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return ContenutoAllegato::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }
}
