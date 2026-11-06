<?php

namespace Dichiarativi\Application\Models;

use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Entities\RettificaDetrazione;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class RettificaDetrazioneModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return RettificaDetrazione::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Righi del prospetto del modulo, per numero di rigo.
     *
     * @return array<int, RettificaDetrazione>
     */
    public function getRighi(Dichiarazione $dichiarazione, int $modulo): array
    {
        $righi = [];
        foreach ($this->getByDichiarazione($dichiarazione) as $rettifica) {
            if ($rettifica->modulo === $modulo) {
                $righi[$rettifica->rigo] = $rettifica;
            }
        }
        return $righi;
    }
}
