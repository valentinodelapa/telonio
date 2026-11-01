<?php

namespace Contabilita\Application\Models;

use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Entities\PianoConti;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Enumerations\LivelloConto;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\Enumerations\ComparisonOperator;
use SismaFramework\Orm\Enumerations\DataType;
use SismaFramework\Orm\Enumerations\Placeholder;
use SismaFramework\Orm\ExtendedClasses\SelfDependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class ContoModel extends SelfDependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Conto::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {
        $query->appendOpenBlock()
                ->appendCondition('codice', ComparisonOperator::like, Placeholder::placeholder)
                ->appendOr()
                ->appendCondition('descrizione', ComparisonOperator::like, Placeholder::placeholder)
                ->appendCloseBlock();
        array_push($bindValues, $searchKey . '%', '%' . $searchKey . '%');
        array_push($bindTypes, DataType::typeString, DataType::typeString);
    }

    /**
     * Conti del piano in ordine di codice (mastri, conti e sottoconti
     * intercalati come nella stampa del piano).
     */
    public function getElenco(PianoConti $piano): SismaCollection
    {
        return $this->getByPianoConti($piano, null, ['codice' => 'ASC']);
    }

    /**
     * @return array<string, Conto> conti del piano per codice
     */
    public function getMappa(PianoConti $piano): array
    {
        $mappa = [];
        foreach ($this->getElenco($piano) as $conto) {
            $mappa[$conto->codice] = $conto;
        }
        return $mappa;
    }

    public function getPerCodice(PianoConti $piano, string $codice): ?Conto
    {
        return $this->getByPianoContiAndCodice($piano, $codice)[0] ?? null;
    }

    public function getPerFunzione(PianoConti $piano, FunzioneConto $funzione): ?Conto
    {
        return $this->getByPianoContiAndFunzione($piano, $funzione)[0] ?? null;
    }

    /**
     * Sottoconti attivi del piano che corrispondono al testo (codice
     * iniziale o descrizione), per l'autocompletamento.
     */
    public function cercaMovimentabili(PianoConti $piano, string $testo, int $limite = 20): SismaCollection
    {
        return $this->getByPianoContiAndLivelloAndAttivo($piano, LivelloConto::sottoconto, true, trim($testo), ['codice' => 'ASC'], 0, $limite);
    }

    /**
     * Sottoconti attivi del piano, per le selezioni.
     */
    public function getMovimentabili(PianoConti $piano): SismaCollection
    {
        return $this->getByPianoContiAndLivelloAndAttivo($piano, LivelloConto::sottoconto, true, null, ['codice' => 'ASC']);
    }

    /**
     * Primo codice libero tra i sottoconti del conto (NN.NN.NNNN).
     */
    public function getProssimoCodice(Conto $conto): string
    {
        $massimo = 0;
        foreach ($this->getEntityCollectionByParent($conto) as $figlio) {
            $massimo = max($massimo, (int) substr($figlio->codice, strrpos($figlio->codice, '.') + 1));
        }
        return $conto->codice . '.' . str_pad((string) ($massimo + 1), 4, '0', STR_PAD_LEFT);
    }

    public function contaFigli(Conto $conto): int
    {
        return $this->countEntityCollectionByParent($conto);
    }
}
