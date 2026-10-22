<?php

namespace Anagrafica\Application\Entities;

use Anagrafica\Application\Enumerations\TipoRecapito;
use SismaFramework\Orm\BaseClasses\BaseEntity;

class Recapito extends BaseEntity
{

    protected int $id;
    protected Soggetto $soggetto;
    protected TipoRecapito $tipoRecapito;
    protected string $valore;
    protected bool $principale = false;
    protected ?string $note = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
