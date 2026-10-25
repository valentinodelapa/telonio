<?php

namespace CommSuite\Tests;

use SismaFramework\Orm\Adapters\AdapterMysql;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\BaseClasses\BaseResultSet;
use SismaFramework\Orm\ResultSets\ResultSetMysql;

/**
 * Adapter MySQL fittizio in memoria per gli unit test senza connessione PDO.
 */
class TestAdapter extends AdapterMysql
{

    /** @var array<int, ?BaseResultSet> */
    private array $codaSelect = [];

    /** @var null|\Closure(string, array, array): ?BaseResultSet */
    private ?\Closure $selectCallback = null;

    private bool $esitoExecute = true;
    private int $ultimoId = 100;

    /** @var array<int, array{cmd: string, bindValues: array, bindTypes: array}> */
    public array $queryEseguite = [];

    #[\Override]
    public function connect(array $options = []): void
    {
        $this->isConnected = true;
    }

    #[\Override]
    protected function ensureConnected(): void
    {
        $this->isConnected = true;
    }

    /**
     * Accoda uno o più risultati per le prossime query SELECT.
     *
     * @param array<int, BaseEntity>|BaseResultSet|null ...$risultati
     */
    public function accodaSelect(array|BaseResultSet|null ...$risultati): void
    {
        foreach ($risultati as $risultato) {
            if (is_array($risultato)) {
                $risultato = new TestResultSet($risultato);
            }
            $this->codaSelect[] = $risultato;
        }
    }

    public function impostaSelectCallback(?\Closure $callback): void
    {
        $this->selectCallback = $callback;
    }

    public function impostaEsitoExecute(bool $esito): void
    {
        $this->esitoExecute = $esito;
    }

    #[\Override]
    public function select(string $cmd, array $bindValues = [], array $bindTypes = []): ?BaseResultSet
    {
        $this->queryEseguite[] = ['cmd' => $cmd, 'bindValues' => $bindValues, 'bindTypes' => $bindTypes];
        if (!empty($this->codaSelect)) {
            return array_shift($this->codaSelect);
        }
        if ($this->selectCallback !== null) {
            return ($this->selectCallback)($cmd, $bindValues, $bindTypes);
        }
        return null;
    }

    #[\Override]
    protected function selectToDelegateAdapter(string $cmd, array $bindValues = [], array $bindTypes = []): ?ResultSetMysql
    {
        return null;
    }

    #[\Override]
    protected function executeToDelegateAdapter(string $cmd, array $bindValues = [], array $bindTypes = []): bool
    {
        $this->queryEseguite[] = ['cmd' => $cmd, 'bindValues' => $bindValues, 'bindTypes' => $bindTypes];
        return $this->esitoExecute;
    }

    #[\Override]
    protected function lastInsertIdToDelegateAdapter(): int
    {
        return ++$this->ultimoId;
    }

    #[\Override]
    protected function beginTransactionToDelegateAdapter(): bool
    {
        return true;
    }

    #[\Override]
    protected function commitTransactionToDelegateAdapter(): bool
    {
        return true;
    }

    #[\Override]
    protected function rollbackTransactionToDelegateAdapter(): bool
    {
        return true;
    }
}
