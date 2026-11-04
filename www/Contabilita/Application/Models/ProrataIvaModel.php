<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\AttivitaIva;
use Contabilita\Application\Entities\ProrataIva;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class ProrataIvaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return ProrataIva::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Pro-rata dell'azienda o, con le attività separate, dell'attività.
     */
    public function getPerAnno(Soggetto $soggetto, int $anno, ?AttivitaIva $attivita = null): ?ProrataIva
    {
        return $this->getBySoggettoAndAnnoAndAttivitaIva($soggetto, $anno, $attivita)[0] ?? null;
    }
}
