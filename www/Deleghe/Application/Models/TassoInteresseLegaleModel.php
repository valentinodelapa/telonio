<?php

namespace Deleghe\Application\Models;

use Deleghe\Application\Entities\TassoInteresseLegale;
use SismaFramework\Orm\BaseClasses\BaseModel;
use SismaFramework\Orm\HelperClasses\Query;

class TassoInteresseLegaleModel extends BaseModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return TassoInteresseLegale::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Tassi in ordine di decorrenza.
     *
     * @return TassoInteresseLegale[]
     */
    public function getSerie(): array
    {
        return iterator_to_array($this->getEntityCollection(null, ['validoDal' => 'ASC']), false);
    }
}
