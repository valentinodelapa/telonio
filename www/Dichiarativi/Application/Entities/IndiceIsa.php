<?php

namespace Dichiarativi\Application\Entities;

use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Indice sintetico di affidabilità fiscale in vigore per anno del modello,
 * con le attività (codici ATECO 2025) a cui si applica: tabella 1 delle
 * istruzioni ISA "Parte generale".
 */
class IndiceIsa extends BaseEntity
{

    protected int $id;
    protected int $anno;
    protected string $codice;
    protected string $descrizione;
    protected string $attivita;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    /**
     * @return string[] codici ATECO (es. "47.11.02")
     */
    public function getCodiciAttivita(): array
    {
        return preg_split('/\s+/', trim($this->attivita), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
