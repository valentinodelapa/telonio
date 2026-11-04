<?php

namespace Dichiarativi\Application\Models;

use Dichiarativi\Application\Entities\QuadroModello;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class QuadroModelloModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return QuadroModello::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * @return array<string, string> sigla => denominazione
     */
    public function getDenominazioni(ModelloDichiarativo $modello, int $anno): array
    {
        $quadri = [];
        foreach ($this->getByModelloAndAnno($modello, $anno, null, ['quadro' => 'ASC']) as $quadro) {
            $quadri[$quadro->quadro] = $quadro->denominazione;
        }
        return $quadri;
    }
}
