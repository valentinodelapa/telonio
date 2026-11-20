<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\ContrattoLeasing;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class ContrattoLeasingModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return ContrattoLeasing::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * @return ContrattoLeasing[]
     */
    public function getElenco(Soggetto $soggetto): array
    {
        return iterator_to_array($this->getBySoggetto($soggetto, null, ['dataInizio' => 'ASC', 'id' => 'ASC']), false);
    }
}
