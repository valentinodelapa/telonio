<?php

namespace Dichiarativi\Application\Models;

use Dichiarativi\Application\Entities\IndiceIsa;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class IndiceIsaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return IndiceIsa::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * ISA che comprende l'attività (codice ATECO 2025, con o senza punti)
     * per anno del modello; null se l'attività non ha un ISA.
     */
    public function getPerAttivita(int $anno, string $codiceAteco): ?IndiceIsa
    {
        $codice = preg_replace('/^(\d{2})\.?(\d{2})\.?(\d{2})$/', '$1.$2.$3', trim($codiceAteco));
        foreach ($this->getByAnno($anno) as $indice) {
            if (in_array($codice, $indice->getCodiciAttivita(), true)) {
                return $indice;
            }
        }
        return null;
    }
}
