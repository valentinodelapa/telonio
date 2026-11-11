<?php

namespace Anagrafica\Application\Models;

use Anagrafica\Application\Entities\ContrattoLocazione;
use Anagrafica\Application\Entities\CanoneNonPercepito;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class CanoneNonPercepitoModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return CanoneNonPercepito::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(ContrattoLocazione $contratto): SismaCollection
    {
        return $this->getByContrattoLocazione($contratto, null, ['anno' => 'ASC']);
    }
}
