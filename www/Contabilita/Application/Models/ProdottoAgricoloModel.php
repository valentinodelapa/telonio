<?php

namespace Contabilita\Application\Models;

use Contabilita\Application\Entities\ProdottoAgricolo;
use SismaFramework\Orm\BaseClasses\BaseModel;
use SismaFramework\Orm\HelperClasses\Query;

class ProdottoAgricoloModel extends BaseModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return ProdottoAgricolo::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Prodotti validi nell'anno, in ordine alfabetico.
     *
     * @return ProdottoAgricolo[]
     */
    public function getPerAnno(int $anno): array
    {
        return array_values(array_filter(iterator_to_array($this->getEntityCollection(null, ['descrizione' => 'ASC']), false), fn(ProdottoAgricolo $prodotto) => $prodotto->isValidoNel($anno)));
    }
}
