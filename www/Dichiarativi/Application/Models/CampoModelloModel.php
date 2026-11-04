<?php

namespace Dichiarativi\Application\Models;

use Dichiarativi\Application\Entities\CampoModello;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class CampoModelloModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return CampoModello::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Catalogo dei campi del modello per anno del modello.
     *
     * @return array<string, CampoModello> per codice, in ordine di codice
     */
    public function getCatalogo(ModelloDichiarativo $modello, int $anno): array
    {
        $catalogo = [];
        foreach ($this->getByModelloAndAnno($modello, $anno, null, ['codice' => 'ASC']) as $campo) {
            $catalogo[$campo->codice] = $campo;
        }
        return $catalogo;
    }

    public function contaPerModello(ModelloDichiarativo $modello, int $anno): int
    {
        return $this->countByModelloAndAnno($modello, $anno);
    }
}
