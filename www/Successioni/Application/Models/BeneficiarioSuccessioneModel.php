<?php

namespace Successioni\Application\Models;

use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;
use Successioni\Application\Entities\BeneficiarioSuccessione;
use Successioni\Application\Entities\DichiarazioneSuccessione;

class BeneficiarioSuccessioneModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return BeneficiarioSuccessione::class;
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
        foreach ($this->getElenco($dichiarazione) as $beneficiario) {
            $ordine = max($ordine, $beneficiario->ordine);
        }
        return $ordine + 1;
    }
}
