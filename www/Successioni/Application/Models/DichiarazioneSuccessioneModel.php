<?php

namespace Successioni\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Telonio\Application\Entities\Studio;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;
use Successioni\Application\Entities\DichiarazioneSuccessione;

class DichiarazioneSuccessioneModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return DichiarazioneSuccessione::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(Soggetto $defunto): SismaCollection
    {
        return $this->getByDefunto($defunto, null, ['id' => 'ASC']);
    }

    public function contaPerStudio(Studio $studio): int
    {
        return $this->countByStudio($studio);
    }
}
