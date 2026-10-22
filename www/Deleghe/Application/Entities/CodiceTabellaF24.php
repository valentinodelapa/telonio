<?php

namespace Deleghe\Application\Entities;

use Deleghe\Application\Enumerations\TabellaF24;
use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Voce di una tabella di decodifica del modello F24 (regioni, sedi INPS e
 * INAIL, altri enti, tipi ELIDE, uffici, ...), importata con il comando
 * tabelle-f24.
 */
class CodiceTabellaF24 extends BaseEntity
{

    protected int $id;
    protected TabellaF24 $tabella;
    protected string $codice;
    protected string $descrizione;
    protected ?string $dati = null;
    protected bool $inUso = true;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function getEtichetta(): string
    {
        return $this->codice . ' - ' . $this->descrizione;
    }
}
