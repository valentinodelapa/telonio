<?php

namespace Anagrafica\Application\Entities;

use Anagrafica\Application\Enumerations\TipoDocumento;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

class DocumentoIdentita extends BaseEntity
{

    protected int $id;
    protected Soggetto $soggetto;
    protected TipoDocumento $tipoDocumento;
    protected string $numero;
    protected ?string $rilasciatoDa = null;
    protected ?SismaDate $dataRilascio = null;
    protected ?SismaDate $dataScadenza = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function isScaduto(): bool
    {
        return ($this->dataScadenza !== null) && ($this->dataScadenza->format('Y-m-d') < (new SismaDate())->format('Y-m-d'));
    }
}
