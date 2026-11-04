<?php

namespace Dichiarativi\Application\Models;

use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Entities\SottoscrizioneControllo;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class SottoscrizioneControlloModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return SottoscrizioneControllo::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * @return SottoscrizioneControllo[]
     */
    public function getSottoscrizioni(Dichiarazione $dichiarazione): array
    {
        return iterator_to_array($this->getByDichiarazione($dichiarazione, null, ['ordine' => 'ASC']), false);
    }
}
