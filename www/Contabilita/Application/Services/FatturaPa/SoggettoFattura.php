<?php

namespace Contabilita\Application\Services\FatturaPa;

/**
 * Cedente/prestatore o cessionario/committente della fattura elettronica
 * (blocchi 1.2 e 1.4 delle specifiche tecniche).
 */
class SoggettoFattura
{

    public ?string $codicePaese = null;
    public ?string $partitaIva = null;
    public ?string $codiceFiscale = null;
    public ?string $denominazione = null;
    public ?string $nome = null;
    public ?string $cognome = null;
    public ?string $indirizzo = null;
    public ?string $numeroCivico = null;
    public ?string $cap = null;
    public ?string $comune = null;
    public ?string $provincia = null;
    public ?string $nazione = null;

    public function getDenominazione(): string
    {
        return $this->denominazione ?? trim(($this->cognome ?? '') . ' ' . ($this->nome ?? ''));
    }

    /**
     * Il soggetto ha la partita IVA o il codice fiscale indicati (la
     * partita IVA si confronta solo per i soggetti italiani).
     */
    public function corrisponde(?string $partitaIva, ?string $codiceFiscale): bool
    {
        $italiano = ($this->codicePaese === null) || ($this->codicePaese === 'IT');
        return ($italiano && ($partitaIva !== null) && ($this->partitaIva === $partitaIva))
                || (($codiceFiscale !== null) && ($this->codiceFiscale === $codiceFiscale))
                || ($italiano && ($codiceFiscale !== null) && ($this->partitaIva === $codiceFiscale));
    }
}
