<?php

namespace Anagrafica\Application\Models;

use Anagrafica\Application\Entities\ContrattoLocazione;
use Anagrafica\Application\Entities\VariazioneCanone;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class VariazioneCanoneModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return VariazioneCanone::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(ContrattoLocazione $contratto): SismaCollection
    {
        return $this->getByContrattoLocazione($contratto, null, ['validoDal' => 'ASC']);
    }
}
