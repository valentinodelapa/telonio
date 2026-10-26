<?php

namespace Deleghe\Application\Entities;

use Anagrafica\Application\Entities\ContoCorrente;
use Anagrafica\Application\Entities\Soggetto;
use Telonio\Application\Entities\Studio;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Intermediario abilitato a Entratel che trasmette le deleghe dello studio
 * (fornitore del flusso telematico). I dati anagrafici sono quelli del
 * soggetto in anagrafica; il conto serve per gli invii con addebito unico
 * sul conto dell'intermediario.
 */
class Intermediario extends ReferencedEntity
{

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected ?string $telefono = null;
    protected ?string $email = null;
    protected ?ContoCorrente $contoAddebito = null;
    protected bool $predefinito = false;

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
        return $this->soggetto->nominativo . ' - ' . $this->soggetto->codiceFiscale;
    }
}
