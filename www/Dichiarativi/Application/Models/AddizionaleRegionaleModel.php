<?php

namespace Dichiarativi\Application\Models;

use Dichiarativi\Application\Entities\AddizionaleRegionale;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class AddizionaleRegionaleModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return AddizionaleRegionale::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getPerRegione(string $codice, int $anno): ?AddizionaleRegionale
    {
        return $this->getByAnnoAndCodice($anno, $codice)[0] ?? null;
    }
}
