<?php

namespace Anagrafica\Application\Models;

use Anagrafica\Application\Entities\Recapito;
use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class RecapitoModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Recapito::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(Soggetto $soggetto): SismaCollection
    {
        return $this->getBySoggetto($soggetto, null, ['tipoRecapito' => 'ASC', 'principale' => 'DESC']);
    }
}
