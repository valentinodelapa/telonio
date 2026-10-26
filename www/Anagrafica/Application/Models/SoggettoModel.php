<?php

namespace Anagrafica\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Telonio\Application\Entities\Studio;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\Enumerations\ComparisonOperator;
use SismaFramework\Orm\Enumerations\DataType;
use SismaFramework\Orm\Enumerations\Placeholder;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class SoggettoModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Soggetto::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {
        $query->appendOpenBlock();
        foreach (['nominativo', 'codiceFiscale', 'partitaIva', 'codice'] as $indice => $colonna) {
            if ($indice > 0) {
                $query->appendOr();
            }
            $query->appendCondition($colonna, ComparisonOperator::like, Placeholder::placeholder);
            $bindValues[] = '%' . $searchKey . '%';
            $bindTypes[] = DataType::typeString;
        }
        $query->appendCloseBlock();
    }

    public function cerca(Studio $studio, ?string $testo, ?TipoSoggetto $tipoSoggetto, ?TipoAttivita $tipoAttivita, ?bool $soloClienti, int $offset, int $limite): SismaCollection
    {
        return $this->getEntityCollectionByProperties($this->generaFiltri($studio, $tipoSoggetto, $tipoAttivita, $soloClienti), $this->normalizzaTesto($testo), ['nominativo' => 'ASC'], $offset, $limite);
    }

    public function contaRisultati(Studio $studio, ?string $testo, ?TipoSoggetto $tipoSoggetto, ?TipoAttivita $tipoAttivita, ?bool $soloClienti): int
    {
        return $this->countEntityCollectionByProperties($this->generaFiltri($studio, $tipoSoggetto, $tipoAttivita, $soloClienti), $this->normalizzaTesto($testo));
    }

    /**
     * Persone fisiche dello studio con la data di decesso, dal decesso più
     * recente (successioni).
     */
    public function cercaDeceduti(Studio $studio, ?string $testo, int $offset, int $limite): SismaCollection
    {
        $bindValues = $bindTypes = [];
        $query = $this->generaQueryDeceduti($studio, $this->normalizzaTesto($testo), $bindValues, $bindTypes);
        $query->setOrderBy(['dataDecesso' => 'DESC', 'nominativo' => 'ASC']);
        $query->setOffset($offset);
        $query->setLimit($limite);
        $query->close();
        return $this->dataMapper->find($this->entityName, $query, $bindValues, $bindTypes);
    }

    public function contaDeceduti(Studio $studio, ?string $testo): int
    {
        $bindValues = $bindTypes = [];
        $query = $this->generaQueryDeceduti($studio, $this->normalizzaTesto($testo), $bindValues, $bindTypes);
        $query->close();
        return $this->dataMapper->getCount($query, $bindValues, $bindTypes);
    }

    private function generaQueryDeceduti(Studio $studio, ?string $testo, array &$bindValues, array &$bindTypes): Query
    {
        $query = $this->initQuery();
        $query->setWhere();
        $this->buildPropertiesConditions($query, ['studio' => $studio, 'tipoSoggetto' => TipoSoggetto::personaFisica], $bindValues, $bindTypes);
        $query->appendAnd()->appendCondition('dataDecesso', ComparisonOperator::isNotNull, '');
        if ($testo !== null) {
            $query->appendAnd();
            $this->appendSearchCondition($query, $testo, $bindValues, $bindTypes);
        }
        return $query;
    }

    private function generaFiltri(Studio $studio, ?TipoSoggetto $tipoSoggetto, ?TipoAttivita $tipoAttivita, ?bool $soloClienti): array
    {
        $filtri = ['studio' => $studio];
        if ($tipoSoggetto !== null) {
            $filtri['tipoSoggetto'] = $tipoSoggetto;
        }
        if ($tipoAttivita !== null) {
            $filtri['tipoAttivita'] = $tipoAttivita;
        }
        if ($soloClienti === true) {
            $filtri['clienteStudio'] = true;
        }
        return $filtri;
    }

    private function normalizzaTesto(?string $testo): ?string
    {
        $testo = trim((string) $testo);
        return $testo === '' ? null : $testo;
    }

    public function esisteCodiceFiscale(Studio $studio, string $codiceFiscale, ?Soggetto $escluso = null): bool
    {
        foreach ($this->getByStudioAndCodiceFiscale($studio, $codiceFiscale) as $soggetto) {
            if (($escluso === null) || (isset($escluso->id) === false) || ($soggetto->id !== $escluso->id)) {
                return true;
            }
        }
        return false;
    }
}
