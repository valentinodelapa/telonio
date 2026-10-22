<?php

namespace Deleghe\Application\Models;

use Deleghe\Application\Entities\TassoRiferimento;
use SismaFramework\Orm\BaseClasses\BaseModel;
use SismaFramework\Orm\HelperClasses\Query;

class TassoRiferimentoModel extends BaseModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return TassoRiferimento::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * @return TassoRiferimento[] in ordine di decorrenza
     */
    public function getSerie(): array
    {
        return iterator_to_array($this->getEntityCollection(null, ['validoDal' => 'ASC']), false);
    }
}
