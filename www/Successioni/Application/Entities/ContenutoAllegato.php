<?php

namespace Successioni\Application\Entities;

use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Contenuto di un allegato in base64 (come va nel file telematico), in una
 * tabella separata: si carica solo per scaricare l'allegato o generare il
 * file, non negli elenchi.
 */
class ContenutoAllegato extends ReferencedEntity
{

    protected int $id;
    protected string $contenuto;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
