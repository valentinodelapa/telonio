<?php

namespace Anagrafica\Application\Entities;

use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Conto corrente intestato al soggetto, usato dagli altri moduli per gli
 * addebiti (es. deleghe F24). Un conto estinto resta come non attivo perché
 * può essere referenziato da documenti passati.
 */
class ContoCorrente extends ReferencedEntity
{

    protected int $id;
    protected Soggetto $soggetto;
    protected string $iban;
    protected ?string $descrizione = null;
    protected bool $predefinito = false;
    protected bool $attivo = true;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function isItaliano(): bool
    {
        return str_starts_with($this->iban, 'IT');
    }

    /**
     * IBAN a gruppi di quattro caratteri, per la lettura.
     */
    public function getIbanFormattato(): string
    {
        return trim(chunk_split($this->iban, 4, ' '));
    }

    public function getEtichetta(): string
    {
        return $this->getIbanFormattato() . ($this->descrizione !== null ? ' - ' . $this->descrizione : '');
    }
}
