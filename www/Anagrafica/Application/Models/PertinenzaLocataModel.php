<?php

namespace Anagrafica\Application\Models;

use Anagrafica\Application\Entities\PertinenzaLocata;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class PertinenzaLocataModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return PertinenzaLocata::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }
}
