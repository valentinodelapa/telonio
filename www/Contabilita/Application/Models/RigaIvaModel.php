<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RegistroIva;
use Contabilita\Application\Entities\RigaIva;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\Enumerations\ComparisonOperator;
use SismaFramework\Orm\Enumerations\DataType;
use SismaFramework\Orm\Enumerations\Placeholder;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class RigaIvaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return RigaIva::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getRighe(RegistrazioneContabile $registrazione): SismaCollection
    {
        return $this->getByRegistrazioneContabile($registrazione, null, ['ordine' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * Righe IVA dell'azienda (o del solo registro) con data di competenza
     * nel periodo, estremi inclusi.
     */
    public function getPerPeriodo(Soggetto|RegistroIva $titolare, SismaDate $dal, SismaDate $al): SismaCollection
    {
        $query = $this->initQuery();
        $query->setWhere()
                ->appendCondition($titolare instanceof Soggetto ? 'soggetto' : 'registroIva', ComparisonOperator::equal, Placeholder::placeholder, true)
                ->appendAnd()
                ->appendCondition('dataCompetenzaIva', ComparisonOperator::greaterOrEqual, Placeholder::placeholder)
                ->appendAnd()
                ->appendCondition('dataCompetenzaIva', ComparisonOperator::lessOrEqual, Placeholder::placeholder);
        $query->setOrderBy(['dataCompetenzaIva' => 'ASC', 'id' => 'ASC']);
        $query->close();
        return $this->dataMapper->find($this->entityName, $query, [$titolare, $dal, $al], [DataType::typeEntity, DataType::typeDate, DataType::typeDate]);
    }
}
