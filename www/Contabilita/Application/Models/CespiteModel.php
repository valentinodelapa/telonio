<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\Cespite;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class CespiteModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Cespite::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Cespiti dell'azienda in ordine di codice.
     *
     * @return Cespite[]
     */
    public function getElenco(Soggetto $soggetto): array
    {
        return iterator_to_array($this->getBySoggetto($soggetto, null, ['codice' => 'ASC', 'id' => 'ASC']), false);
    }
}
