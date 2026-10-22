<?php

namespace Successioni\Application\Models;

use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Entities\BeneficiarioSuccessione;
use Successioni\Application\Entities\DevoluzioneSuccessione;

class DevoluzioneSuccessioneModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return DevoluzioneSuccessione::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(BeneSuccessione $bene): SismaCollection
    {
        return $this->getByBeneSuccessione($bene, null, ['id' => 'ASC']);
    }

    public function contaPerBeneficiario(BeneficiarioSuccessione $beneficiario): int
    {
        return $this->countByBeneficiarioSuccessione($beneficiario);
    }
}
