<?php

namespace Contabilita\Application\Models;

use Contabilita\Application\Entities\CorrispettivoGiornaliero;
use Contabilita\Application\Entities\RegistrazioneContabile;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class CorrispettivoGiornalieroModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return CorrispettivoGiornaliero::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getPerRegistrazione(RegistrazioneContabile $registrazione): SismaCollection
    {
        return $this->getByRegistrazioneContabile($registrazione, null, ['data' => 'ASC', 'id' => 'ASC']);
    }
}
