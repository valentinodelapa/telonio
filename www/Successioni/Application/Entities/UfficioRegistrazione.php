<?php

namespace Successioni\Application\Entities;

use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Ufficio di registrazione dell'Agenzia delle Entrate (testamenti, atti di
 * rinuncia, atti di acquisto): tabella delle specifiche tecniche SUC13,
 * comune a tutti gli studi e aggiornata con "sisma specifiche-successioni".
 */
class UfficioRegistrazione extends ReferencedEntity
{

    protected int $id;
    protected string $codice;
    protected string $denominazione;
    protected ?string $periodo = null;

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
        return $this->codice . ' - ' . $this->denominazione . ($this->periodo !== null ? ' (' . $this->periodo . ')' : '');
    }
}
