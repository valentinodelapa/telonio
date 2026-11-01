<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;
use Telonio\Application\Entities\Studio;

/**
 * Registro IVA dell'azienda (vendite, acquisti, corrispettivi) o un suo
 * sezionale: la numerazione del protocollo è separata per registro e
 * anno solare.
 */
class RegistroIva extends ReferencedEntity
{

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected TipoRegistroIva $tipo;
    protected string $sezionale = '';
    protected string $descrizione;
    protected bool $attivo = true;

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
        return $this->descrizione . ($this->sezionale === '' ? '' : ' (sezionale ' . $this->sezionale . ')');
    }
}
