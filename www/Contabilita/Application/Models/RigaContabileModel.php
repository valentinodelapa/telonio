<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RigaContabile;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\Enumerations\ComparisonOperator;
use SismaFramework\Orm\Enumerations\DataType;
use SismaFramework\Orm\Enumerations\Placeholder;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class RigaContabileModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return RigaContabile::class;
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
     * Movimenti dell'azienda (o del solo conto) con data di registrazione
     * nel periodo (estremi inclusi, null = aperto), in ordine di data.
     */
    public function getMovimenti(Soggetto $soggetto, ?Conto $conto, ?SismaDate $dal, ?SismaDate $al): SismaCollection
    {
        $bindValues = [$conto ?? $soggetto];
        $bindTypes = [DataType::typeEntity];
        $query = $this->initQuery();
        $query->setWhere()->appendCondition($conto === null ? 'soggetto' : 'conto', ComparisonOperator::equal, Placeholder::placeholder, true);
        foreach ([[$dal, ComparisonOperator::greaterOrEqual], [$al, ComparisonOperator::lessOrEqual]] as [$data, $operatore]) {
            if ($data !== null) {
                $query->appendAnd()->appendCondition('dataRegistrazione', $operatore, Placeholder::placeholder);
                $bindValues[] = $data;
                $bindTypes[] = DataType::typeDate;
            }
        }
        $query->setOrderBy(['dataRegistrazione' => 'ASC', 'id' => 'ASC']);
        $query->close();
        return $this->dataMapper->find($this->entityName, $query, $bindValues, $bindTypes);
    }

    public function contaPerConto(Conto $conto): int
    {
        return $this->countByConto($conto);
    }
}
