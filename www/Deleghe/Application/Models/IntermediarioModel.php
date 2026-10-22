<?php

namespace Deleghe\Application\Models;

use CommSuite\Application\Entities\Studio;
use Deleghe\Application\Entities\Intermediario;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class IntermediarioModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Intermediario::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(Studio $studio): SismaCollection
    {
        return $this->getByStudio($studio, null, ['predefinito' => 'DESC', 'id' => 'ASC']);
    }

    public function getPredefinito(Studio $studio): ?Intermediario
    {
        return $this->getElenco($studio)[0] ?? null;
    }
}
