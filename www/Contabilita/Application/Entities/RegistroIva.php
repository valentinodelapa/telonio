<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;
use Telonio\Application\Entities\Studio;

/**
 * Registro IVA dell'azienda (vendite, acquisti, corrispettivi) o un suo
 * sezionale: la numerazione del protocollo è separata per registro e
 * anno solare. Con le attività separate (art. 36) il registro appartiene
 * a un'attività; un registro degli acquisti senza attività raccoglie gli
 * acquisti promiscui, ripartiti tra le attività riga per riga.
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
    protected ?Indirizzo $unitaLocale = null;
    protected ?AttivitaIva $attivitaIva = null;

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
