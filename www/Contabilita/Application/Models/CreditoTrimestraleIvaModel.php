<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\CreditoTrimestraleIva;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class CreditoTrimestraleIvaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return CreditoTrimestraleIva::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getPerTrimestre(Soggetto $soggetto, int $anno, int $trimestre): ?CreditoTrimestraleIva
    {
        return $this->getBySoggettoAndAnnoAndTrimestre($soggetto, $anno, $trimestre)[0] ?? null;
    }
}
