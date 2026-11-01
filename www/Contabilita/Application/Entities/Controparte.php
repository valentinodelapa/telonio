<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Nazione;
use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;
use Telonio\Application\Entities\Studio;

/**
 * Cliente o fornitore dell'azienda (non è un soggetto dello studio: le
 * controparti sono separate per azienda). Ha un sottoconto del partitario
 * per ruolo, sotto i conti con funzione clienti e fornitori del piano, e
 * facoltativamente la contropartita e il codice IVA proposti nelle
 * registrazioni.
 */
class Controparte extends ReferencedEntity
{

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected string $denominazione;
    protected ?string $cognome = null;
    protected ?string $nome = null;
    protected ?string $codiceFiscale = null;
    protected ?string $partitaIva = null;
    protected ?Nazione $nazione = null;
    protected ?string $indirizzo = null;
    protected ?string $cap = null;
    protected ?string $comune = null;
    protected ?string $provincia = null;
    protected ?string $pec = null;
    protected ?string $codiceDestinatario = null;
    protected bool $cliente = false;
    protected bool $fornitore = false;
    protected bool $pubblicaAmministrazione = false;
    protected ?Conto $contoCliente = null;
    protected ?Conto $contoFornitore = null;
    protected ?Conto $contoContropartita = null;
    protected ?CodiceIva $codiceIva = null;
    protected ?string $note = null;

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
        $identificativo = $this->partitaIva ?? $this->codiceFiscale;
        return $this->denominazione . ($identificativo === null ? '' : ' - ' . $identificativo);
    }

    /**
     * Sottoconto del partitario per il ruolo (cliente o fornitore).
     */
    public function getConto(bool $cliente): ?Conto
    {
        return $cliente ? $this->contoCliente : $this->contoFornitore;
    }
}
