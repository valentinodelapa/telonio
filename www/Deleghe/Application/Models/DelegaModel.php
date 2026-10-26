<?php

namespace Deleghe\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Telonio\Application\Entities\Studio;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Enumerations\StatoDelega;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\Enumerations\ComparisonOperator;
use SismaFramework\Orm\Enumerations\DataType;
use SismaFramework\Orm\Enumerations\Placeholder;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class DelegaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Delega::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {
        $query->appendCondition('descrizione', ComparisonOperator::like, Placeholder::placeholder);
        $bindValues[] = '%' . $searchKey . '%';
        $bindTypes[] = DataType::typeString;
    }

    /**
     * Deleghe dello studio con scadenza nel periodo indicato (estremi
     * inclusi, null = aperto), dalla scadenza più recente.
     */
    public function cerca(Studio $studio, ?Soggetto $soggetto, ?StatoDelega $stato, ?SismaDate $dal, ?SismaDate $al, int $offset, int $limite): SismaCollection
    {
        $bindValues = $bindTypes = [];
        $query = $this->generaQuery($studio, $soggetto, $stato, $dal, $al, $bindValues, $bindTypes);
        $query->setOrderBy(['scadenza' => 'DESC', 'id' => 'DESC']);
        $query->setOffset($offset);
        $query->setLimit($limite);
        $query->close();
        return $this->dataMapper->find($this->entityName, $query, $bindValues, $bindTypes);
    }

    public function contaRisultati(Studio $studio, ?Soggetto $soggetto, ?StatoDelega $stato, ?SismaDate $dal, ?SismaDate $al): int
    {
        $bindValues = $bindTypes = [];
        $query = $this->generaQuery($studio, $soggetto, $stato, $dal, $al, $bindValues, $bindTypes);
        $query->close();
        return $this->dataMapper->getCount($query, $bindValues, $bindTypes);
    }

    private function generaQuery(Studio $studio, ?Soggetto $soggetto, ?StatoDelega $stato, ?SismaDate $dal, ?SismaDate $al, array &$bindValues, array &$bindTypes): Query
    {
        $filtri = ['studio' => $studio];
        if ($soggetto !== null) {
            $filtri['soggetto'] = $soggetto;
        }
        if ($stato !== null) {
            $filtri['stato'] = $stato;
        }
        $query = $this->initQuery();
        $query->setWhere();
        $this->buildPropertiesConditions($query, $filtri, $bindValues, $bindTypes);
        foreach ([[$dal, ComparisonOperator::greaterOrEqual], [$al, ComparisonOperator::lessOrEqual]] as [$data, $operatore]) {
            if ($data !== null) {
                $query->appendAnd()->appendCondition('scadenza', $operatore, Placeholder::placeholder);
                $bindValues[] = $data;
                $bindTypes[] = DataType::typeDate;
            }
        }
        return $query;
    }

    /**
     * Deleghe del soggetto per la scadenza (di norma una sola; più di una se
     * lo studio le ha divise, es. per addebitarle su conti diversi).
     */
    public function getPerScadenza(Soggetto $soggetto, SismaDate $scadenza): SismaCollection
    {
        return $this->getBySoggettoAndScadenza($soggetto, $scadenza, null, ['id' => 'ASC']);
    }

    /**
     * Deleghe dello studio ancora da versare con scadenza entro la data.
     */
    public function contaDaVersareEntro(Studio $studio, SismaDate $data): int
    {
        $totale = 0;
        foreach ([StatoDelega::bozza, StatoDelega::confermata] as $stato) {
            $totale += $this->contaRisultati($studio, null, $stato, null, $data);
        }
        return $totale;
    }
}
