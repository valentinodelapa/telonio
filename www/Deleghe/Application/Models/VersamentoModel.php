<?php

namespace Deleghe\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Telonio\Application\Entities\Studio;
use Deleghe\Application\Entities\Credito;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneF24;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\Enumerations\ComparisonOperator;
use SismaFramework\Orm\Enumerations\DataType;
use SismaFramework\Orm\Enumerations\Placeholder;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class VersamentoModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Versamento::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Versamenti della delega nell'ordine del modello: per sezione, poi per
     * ordine di inserimento.
     *
     * @return array<string, Versamento[]> versamenti indicizzati per valore di SezioneF24
     */
    public function getPerSezione(Delega $delega): array
    {
        $righe = array_fill_keys(array_map(fn(SezioneF24 $sezione) => $sezione->value, SezioneF24::cases()), []);
        foreach ($this->getByDelega($delega, null, ['ordine' => 'ASC', 'id' => 'ASC']) as $riga) {
            $righe[$riga->sezione->value][] = $riga;
        }
        return $righe;
    }

    public function getElenco(Delega $delega): SismaCollection
    {
        return $this->getByDelega($delega, null, ['ordine' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * Totali a debito e a credito della delega, in centesimi.
     *
     * @return array{debito: int, credito: int}
     */
    public function getTotali(Delega $delega): array
    {
        $totali = ['debito' => 0, 'credito' => 0];
        foreach ($this->getByDelega($delega) as $riga) {
            $totali['debito'] += (int) round($riga->importoDebito * 100);
            $totali['credito'] += (int) round($riga->importoCredito * 100);
        }
        return $totali;
    }

    public function getProssimoOrdine(Delega $delega, SezioneF24 $sezione): int
    {
        $ordine = 0;
        foreach ($this->getByDelegaAndSezione($delega, $sezione) as $riga) {
            $ordine = max($ordine, $riga->ordine);
        }
        return $ordine + 1;
    }

    /**
     * Versamenti non ancora in una delega, per scadenza, esclusi quelli
     * omessi già regolarizzati con un ravvedimento.
     *
     * @return Versamento[]
     */
    public function getDaVersare(Soggetto $soggetto, ?SismaDate $scadenza = null): array
    {
        $versamenti = $scadenza === null ? $this->getBySoggettoAndDelega($soggetto, null, null, ['scadenza' => 'ASC', 'sezione' => 'ASC', 'id' => 'ASC'])
                : $this->getBySoggettoAndScadenzaAndDelega($soggetto, $scadenza, null, null, ['sezione' => 'ASC', 'id' => 'ASC']);
        $ravvedimentoModel = new RavvedimentoModel($this->dataMapper);
        return array_values(array_filter(iterator_to_array($versamenti, false), fn(Versamento $versamento) => $ravvedimentoModel->countByVersamentoOmesso($versamento) === 0));
    }

    /**
     * Versamenti registrati da un modulo con origine e riferimento, in
     * qualunque delega o da versare.
     */
    public function getGenerati(Soggetto $soggetto, string $origine, string $riferimento): SismaCollection
    {
        return $this->getBySoggettoAndOrigineAndRiferimentoOrigine($soggetto, $origine, $riferimento, null, ['scadenza' => 'ASC', 'id' => 'ASC']);
    }

    public function getUtilizzi(Credito $credito): SismaCollection
    {
        return $this->getByCredito($credito, null, ['scadenza' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * Versamenti dello studio (o del soggetto) con scadenza nel periodo,
     * estremi inclusi, in qualunque delega o da versare.
     *
     * @return Versamento[] per scadenza
     */
    public function getPerPeriodo(Studio $studio, SismaDate $dal, SismaDate $al, ?Soggetto $soggetto = null): array
    {
        $query = $this->initQuery();
        $query->setWhere()
                ->appendCondition($soggetto === null ? 'studio' : 'soggetto', ComparisonOperator::equal, Placeholder::placeholder, true)
                ->appendAnd()
                ->appendCondition('scadenza', ComparisonOperator::greaterOrEqual, Placeholder::placeholder)
                ->appendAnd()
                ->appendCondition('scadenza', ComparisonOperator::lessOrEqual, Placeholder::placeholder);
        $query->setOrderBy(['scadenza' => 'ASC', 'id' => 'ASC']);
        $query->close();
        return iterator_to_array($this->dataMapper->find($this->entityName, $query, [$soggetto ?? $studio, $dal, $al], [DataType::typeEntity, DataType::typeDate, DataType::typeDate]), false);
    }
}
