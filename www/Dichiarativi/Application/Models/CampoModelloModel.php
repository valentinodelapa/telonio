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
     * Catalogo dei campi del modello per anno del modello (per i modelli
     * ISA, dell'ISA indicato).
     *
     * @return array<string, CampoModello> per codice, in ordine di codice
     */
    public function getCatalogo(ModelloDichiarativo $modello, int $anno, ?string $isa = null): array
    {
        $catalogo = [];
        $campi = $modello === ModelloDichiarativo::isa ? $this->getByModelloAndAnnoAndIsa($modello, $anno, (string) $isa, null, ['codice' => 'ASC'])
                : $this->getByModelloAndAnno($modello, $anno, null, ['codice' => 'ASC']);
        foreach ($campi as $campo) {
            $catalogo[$campo->codice] = $campo;
        }
        return $catalogo;
    }

    public function contaPerModello(ModelloDichiarativo $modello, int $anno): int
    {
        return $this->countByModelloAndAnno($modello, $anno);
    }
}
