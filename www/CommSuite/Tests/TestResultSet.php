<?php

namespace CommSuite\Tests;

use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\BaseClasses\BaseResultSet;
use SismaFramework\Orm\ExtendedClasses\StandardEntity;

/**
 * ResultSet in memoria per gli unit test senza database attivo.
 */
class TestResultSet extends BaseResultSet
{

    /**
     * @param array<int, BaseEntity|StandardEntity|\stdClass> $records
     */
    public function __construct(private array $records = [])
    {
        $this->records = array_values($this->records);
        parent::__construct();
    }

    public static function conteggio(int $numeroRighe): self
    {
        $riga = new StandardEntity();
        $riga->_numrows = $numeroRighe;
        return new self([$riga]);
    }

    #[\Override]
    public function numRows(): int
    {
        return count($this->records);
    }

    #[\Override]
    public function fetch(bool $autoNext = true): null|StandardEntity|BaseEntity
    {
        if (!$this->valid()) {
            return null;
        }
        $record = $this->records[$this->currentRecord];
        if ($autoNext) {
            $this->next();
        }
        if ($record instanceof BaseEntity) {
            if (!isset($record->id)) {
                $record->id = $this->currentRecord + 1;
            }
            return $record;
        }
        if ($record instanceof StandardEntity) {
            return $record;
        }
        return $this->hydrate($record);
    }
}
