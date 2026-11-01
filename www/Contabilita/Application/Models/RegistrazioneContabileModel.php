<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\EsercizioContabile;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RegistroIva;
use Contabilita\Application\Enumerations\CausaleContabile;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\Enumerations\ComparisonOperator;
use SismaFramework\Orm\Enumerations\DataType;
use SismaFramework\Orm\Enumerations\Placeholder;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class RegistrazioneContabileModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return RegistrazioneContabile::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {
        $query->appendOpenBlock()
                ->appendCondition('descrizione', ComparisonOperator::like, Placeholder::placeholder)
                ->appendOr()
                ->appendCondition('numeroDocumento', ComparisonOperator::like, Placeholder::placeholder)
                ->appendCloseBlock();
        array_push($bindValues, '%' . $searchKey . '%', $searchKey . '%');
        array_push($bindTypes, DataType::typeString, DataType::typeString);
    }

    /**
     * Prima nota dell'esercizio, dalla registrazione più recente, con i
     * filtri facoltativi.
     */
    public function cerca(EsercizioContabile $esercizio, ?CausaleContabile $causale, string $testo, ?SismaDate $dal, ?SismaDate $al, int $offset, int $limite): SismaCollection
    {
        $bindValues = $bindTypes = [];
        $query = $this->generaQuery($esercizio, $causale, $testo, $dal, $al, $bindValues, $bindTypes);
        $query->setOrderBy(['dataRegistrazione' => 'DESC', 'numero' => 'DESC']);
        $query->setOffset($offset);
        $query->setLimit($limite);
        $query->close();
        return $this->dataMapper->find($this->entityName, $query, $bindValues, $bindTypes);
    }

    public function conta(EsercizioContabile $esercizio, ?CausaleContabile $causale, string $testo, ?SismaDate $dal, ?SismaDate $al): int
    {
        $bindValues = $bindTypes = [];
        $query = $this->generaQuery($esercizio, $causale, $testo, $dal, $al, $bindValues, $bindTypes);
        $query->close();
        return $this->dataMapper->getCount($query, $bindValues, $bindTypes);
    }

    private function generaQuery(EsercizioContabile $esercizio, ?CausaleContabile $causale, string $testo, ?SismaDate $dal, ?SismaDate $al, array &$bindValues, array &$bindTypes): Query
    {
        $filtri = ['esercizioContabile' => $esercizio];
        if ($causale !== null) {
            $filtri['causale'] = $causale;
        }
        $query = $this->initQuery();
        $query->setWhere();
        $this->buildPropertiesConditions($query, $filtri, $bindValues, $bindTypes);
        self::aggiungiPeriodo($query, 'dataRegistrazione', $dal, $al, $bindValues, $bindTypes);
        if (trim($testo) !== '') {
            $query->appendAnd();
            $this->appendSearchCondition($query, trim($testo), $bindValues, $bindTypes);
        }
        return $query;
    }

    private static function aggiungiPeriodo(Query $query, string $colonna, ?SismaDate $dal, ?SismaDate $al, array &$bindValues, array &$bindTypes): void
    {
        foreach ([[$dal, ComparisonOperator::greaterOrEqual], [$al, ComparisonOperator::lessOrEqual]] as [$data, $operatore]) {
            if ($data !== null) {
                $query->appendAnd()->appendCondition($colonna, $operatore, Placeholder::placeholder);
                $bindValues[] = $data;
                $bindTypes[] = DataType::typeDate;
            }
        }
    }

    /**
     * Registrazioni dell'azienda nel periodo, in ordine di giornale (data,
     * numero).
     */
    public function getPerPeriodo(Soggetto $soggetto, SismaDate $dal, SismaDate $al): SismaCollection
    {
        $bindValues = [$soggetto];
        $bindTypes = [DataType::typeEntity];
        $query = $this->initQuery();
        $query->setWhere()->appendCondition('soggetto', ComparisonOperator::equal, Placeholder::placeholder, true);
        self::aggiungiPeriodo($query, 'dataRegistrazione', $dal, $al, $bindValues, $bindTypes);
        $query->setOrderBy(['dataRegistrazione' => 'ASC', 'numero' => 'ASC']);
        $query->close();
        return $this->dataMapper->find($this->entityName, $query, $bindValues, $bindTypes);
    }

    /**
     * Documenti del registro IVA con data di competenza nel periodo, in
     * ordine di protocollo.
     *
     * @return array<int, RegistrazioneContabile> per id
     */
    public function getPerRegistroIva(RegistroIva $registro, SismaDate $dal, SismaDate $al): array
    {
        $bindValues = [$registro];
        $bindTypes = [DataType::typeEntity];
        $query = $this->initQuery();
        $query->setWhere()->appendCondition('registroIva', ComparisonOperator::equal, Placeholder::placeholder, true);
        self::aggiungiPeriodo($query, 'dataCompetenzaIva', $dal, $al, $bindValues, $bindTypes);
        $query->setOrderBy(['protocolloIva' => 'ASC', 'id' => 'ASC']);
        $query->close();
        $registrazioni = [];
        foreach ($this->dataMapper->find($this->entityName, $query, $bindValues, $bindTypes) as $registrazione) {
            $registrazioni[$registrazione->id] = $registrazione;
        }
        return $registrazioni;
    }

    public function getProssimoNumero(EsercizioContabile $esercizio): int
    {
        $ultima = $this->getByEsercizioContabile($esercizio, null, ['numero' => 'DESC'], 0, 1)[0] ?? null;
        return $ultima === null ? 1 : $ultima->numero + 1;
    }

    /**
     * Prossimo protocollo del registro nell'anno solare (art. 23 e 25
     * D.P.R. 633/1972: numerazione progressiva per anno solare).
     */
    public function getProssimoProtocollo(RegistroIva $registro, int $anno): int
    {
        $bindValues = [$registro];
        $bindTypes = [DataType::typeEntity];
        $query = $this->initQuery();
        $query->setWhere()->appendCondition('registroIva', ComparisonOperator::equal, Placeholder::placeholder, true);
        self::aggiungiPeriodo($query, 'dataRegistrazione', new SismaDate($anno . '-01-01'), new SismaDate($anno . '-12-31'), $bindValues, $bindTypes);
        $query->setOrderBy(['protocolloIva' => 'DESC']);
        $query->setLimit(1);
        $query->close();
        $ultima = $this->dataMapper->findFirst($this->entityName, $query, $bindValues, $bindTypes);
        return ($ultima === null) || ($ultima->protocolloIva === null) ? 1 : $ultima->protocolloIva + 1;
    }

    public function getGenerata(Soggetto $soggetto, string $origine, string $riferimento): ?RegistrazioneContabile
    {
        return $this->getBySoggettoAndOrigineAndRiferimentoOrigine($soggetto, $origine, $riferimento)[0] ?? null;
    }

    public function contaPerEsercizio(EsercizioContabile $esercizio): int
    {
        return $this->countByEsercizioContabile($esercizio);
    }
}
