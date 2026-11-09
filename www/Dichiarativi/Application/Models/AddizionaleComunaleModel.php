<?php

namespace Dichiarativi\Application\Models;

use Dichiarativi\Application\Entities\AddizionaleComunale;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class AddizionaleComunaleModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return AddizionaleComunale::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Aliquote del comune per l'anno d'imposta; null se il comune non è
     * nell'elenco (addizionale non deliberata).
     */
    public function getPerComune(string $codiceCatastale, int $anno): ?AddizionaleComunale
    {
        return $this->getByAnnoAndCodiceCatastale($anno, $codiceCatastale)[0] ?? null;
    }
}
