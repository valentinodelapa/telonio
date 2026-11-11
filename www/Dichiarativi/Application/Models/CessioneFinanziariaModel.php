<?php

namespace Dichiarativi\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\Entities\CessioneFinanziaria;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class CessioneFinanziariaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return CessioneFinanziaria::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * @return CessioneFinanziaria[]
     */
    public function getPerAnno(Soggetto $soggetto, int $anno): array
    {
        return iterator_to_array($this->getBySoggettoAndAnnoImposta($soggetto, $anno, null, ['dataCessione' => 'ASC', 'id' => 'ASC']), false);
    }
}
