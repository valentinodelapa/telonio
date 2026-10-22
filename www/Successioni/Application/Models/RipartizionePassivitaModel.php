<?php

namespace Successioni\Application\Models;

use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;
use Successioni\Application\Entities\BeneficiarioSuccessione;
use Successioni\Application\Entities\PassivitaSuccessione;
use Successioni\Application\Entities\RipartizionePassivita;

class RipartizionePassivitaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return RipartizionePassivita::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(PassivitaSuccessione $passivita): SismaCollection
    {
        return $this->getByPassivitaSuccessione($passivita, null, ['id' => 'ASC']);
    }

    public function contaPerBeneficiario(BeneficiarioSuccessione $beneficiario): int
    {
        return $this->countByBeneficiarioSuccessione($beneficiario);
    }
}
