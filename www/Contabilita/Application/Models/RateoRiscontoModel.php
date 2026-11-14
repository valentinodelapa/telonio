<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\RateoRisconto;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class RateoRiscontoModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return RateoRisconto::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Ratei e risconti dell'esercizio.
     *
     * @return RateoRisconto[]
     */
    public function getPerAnno(Soggetto $soggetto, int $anno): array
    {
        return iterator_to_array($this->getBySoggettoAndAnno($soggetto, $anno, null, ['competenzaDal' => 'ASC', 'id' => 'ASC']), false);
    }
}
