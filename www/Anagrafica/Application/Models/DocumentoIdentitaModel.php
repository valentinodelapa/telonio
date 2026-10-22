<?php

namespace Anagrafica\Application\Models;

use Anagrafica\Application\Entities\DocumentoIdentita;
use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class DocumentoIdentitaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return DocumentoIdentita::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(Soggetto $soggetto): SismaCollection
    {
        return $this->getBySoggetto($soggetto, null, ['dataScadenza' => 'DESC']);
    }
}
