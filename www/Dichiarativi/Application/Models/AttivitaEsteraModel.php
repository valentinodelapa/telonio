<?php

namespace Dichiarativi\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\Entities\AttivitaEstera;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class AttivitaEsteraModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return AttivitaEstera::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * @return AttivitaEstera[]
     */
    public function getPerAnno(Soggetto $soggetto, int $anno): array
    {
        return iterator_to_array($this->getBySoggettoAndAnnoImposta($soggetto, $anno, null, ['codiceInvestimento' => 'ASC', 'id' => 'ASC']), false);
    }
}
