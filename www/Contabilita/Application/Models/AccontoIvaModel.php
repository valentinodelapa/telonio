<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\AccontoIva;
use Contabilita\Application\Entities\GruppoIva;
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

    /**
     * Acconto del soggetto o, con il gruppo, quello del gruppo IVA.
     */
    public function getPerAnno(Soggetto $soggetto, int $anno, ?GruppoIva $gruppo = null): ?AccontoIva
    {
        return $this->getBySoggettoAndAnnoAndGruppoIva($soggetto, $anno, $gruppo)[0] ?? null;
    }
}
