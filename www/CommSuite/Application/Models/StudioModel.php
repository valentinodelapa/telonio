<?php

namespace CommSuite\Application\Models;

use CommSuite\Application\Entities\Studio;
use SismaFramework\Orm\BaseClasses\BaseModel;
use SismaFramework\Orm\HelperClasses\Query;

class StudioModel extends BaseModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Studio::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }
}
