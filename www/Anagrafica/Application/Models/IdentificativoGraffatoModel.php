<?php

namespace Anagrafica\Application\Models;

use Anagrafica\Application\Entities\IdentificativoGraffato;
use Anagrafica\Application\Entities\Immobile;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class IdentificativoGraffatoModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return IdentificativoGraffato::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(Immobile $immobile): SismaCollection
    {
        return $this->getByImmobile($immobile, null, ['foglio' => 'ASC', 'particella' => 'ASC', 'subalterno' => 'ASC']);
    }
}
