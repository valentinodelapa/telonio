<?php

namespace Successioni\Application\Entities;

use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Servizio di pubblicità immobiliare (ex conservatoria) dell'Agenzia delle
 * Entrate, per gli estremi di trascrizione del quadro EI: tabella delle
 * specifiche tecniche SUC13, comune a tutti gli studi.
 */
class UfficioTrascrizione extends ReferencedEntity
{

    protected int $id;
    protected string $codice;
    protected string $denominazione;

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
        return $this->codice . ' - ' . $this->denominazione;
    }
}
