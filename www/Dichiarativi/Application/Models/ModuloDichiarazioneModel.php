<?php

namespace Dichiarativi\Application\Models;

use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Entities\ModuloDichiarazione;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class ModuloDichiarazioneModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return ModuloDichiarazione::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * @return ModuloDichiarazione[]
     */
    public function getModuli(Dichiarazione $dichiarazione): array
    {
        return iterator_to_array($this->getByDichiarazione($dichiarazione, null, ['numero' => 'ASC']), false);
    }
}
