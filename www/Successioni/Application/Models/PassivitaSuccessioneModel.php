<?php

namespace Successioni\Application\Models;

use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;
use Successioni\Application\Entities\DichiarazioneSuccessione;
use Successioni\Application\Entities\PassivitaSuccessione;

class PassivitaSuccessioneModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return PassivitaSuccessione::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(DichiarazioneSuccessione $dichiarazione): SismaCollection
    {
        return $this->getByDichiarazioneSuccessione($dichiarazione, null, ['ordine' => 'ASC', 'id' => 'ASC']);
    }

    public function getProssimoOrdine(DichiarazioneSuccessione $dichiarazione): int
    {
        $ordine = 0;
        foreach ($this->getElenco($dichiarazione) as $passivita) {
            $ordine = max($ordine, $passivita->ordine);
        }
        return $ordine + 1;
    }
}
