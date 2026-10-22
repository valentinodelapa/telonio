<?php

namespace CommSuite\Application\Entities;

use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Studio professionale titolare dei dati (tenant della suite).
 *
 * Ogni record applicativo dei moduli è legato, direttamente o tramite
 * il soggetto di appartenenza, allo studio che lo gestisce.
 */
class Studio extends ReferencedEntity
{

    protected int $id;
    protected string $denominazione;
    protected string $codiceFiscale;
    protected ?string $partitaIva = null;
    protected bool $attivo = true;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
