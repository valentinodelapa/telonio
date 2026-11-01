<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\AccontoIva;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class AccontoIvaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return AccontoIva::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getPerAnno(Soggetto $soggetto, int $anno): ?AccontoIva
    {
        return $this->getBySoggettoAndAnno($soggetto, $anno)[0] ?? null;
    }
}
