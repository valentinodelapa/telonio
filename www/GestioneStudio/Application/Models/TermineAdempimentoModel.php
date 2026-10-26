<?php

namespace GestioneStudio\Application\Models;

use GestioneStudio\Application\Entities\Adempimento;
use GestioneStudio\Application\Entities\TermineAdempimento;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class TermineAdempimentoModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return TermineAdempimento::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * @return TermineAdempimento[] per ordine
     */
    public function getTermini(Adempimento $adempimento): array
    {
        return iterator_to_array($this->getByAdempimento($adempimento, null, ['ordine' => 'ASC', 'id' => 'ASC']), false);
    }

    public function getProssimoOrdine(Adempimento $adempimento): int
    {
        $ordini = array_map(fn(TermineAdempimento $termine) => $termine->ordine, $this->getTermini($adempimento));
        return count($ordini) === 0 ? 1 : max($ordini) + 1;
    }
}
