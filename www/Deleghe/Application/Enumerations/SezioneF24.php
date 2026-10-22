<?php

namespace Deleghe\Application\Enumerations;

/**
 * Sezioni del modello F24 ordinario, con i campi di ciascun rigo e il
 * numero di righi stampati nel modello.
 */
enum SezioneF24: string
{

    case erario = 'ERA';
    case inps = 'INP';
    case regioni = 'REG';
    case tributiLocali = 'LOC';
    case inail = 'INA';
    case altriEnti = 'PRE';
    case elide = 'ELI';
    case accise = 'ACC';

    public function getLabel(): string
    {
        return match ($this) {
            self::erario => 'Erario',
            self::inps => 'INPS',
            self::regioni => 'Regioni',
            self::tributiLocali => 'IMU e altri tributi locali',
            self::inail => 'INAIL',
            self::altriEnti => 'Altri enti previdenziali e assicurativi',
            self::elide => 'Erario ed altro (elementi identificativi)',
            self::accise => 'Accise/monopoli e altri versamenti non ammessi in compensazione',
        };
    }

    public function getIcona(): string
    {
        return match ($this) {
            self::erario => 'bi-bank',
            self::inps, self::altriEnti => 'bi-shield-check',
            self::regioni => 'bi-map',
            self::tributiLocali => 'bi-house',
            self::inail => 'bi-cone-striped',
            self::elide => 'bi-card-list',
            self::accise => 'bi-droplet',
        };
    }

    /**
     * Righi disponibili nella sezione del modello: oltre questo numero la
     * delega va stampata su più modelli.
     */
    public function getRigheModello(): int
    {
        return match ($this) {
            self::erario => 6,
            self::inps, self::regioni, self::tributiLocali => 4,
            self::inail => 3,
            self::altriEnti => 2,
            self::elide => 28,
            self::accise => 7,
        };
    }

    /**
     * Sezione dell'elenco dei codici tributo dell'Agenzia da cui scegliere
     * il codice; null per le sezioni che usano le causali degli enti.
     */
    public function getSezioneCodici(): ?SezioneCodiceTributo
    {
        return match ($this) {
            self::erario => SezioneCodiceTributo::erario,
            self::regioni => SezioneCodiceTributo::regioni,
            self::tributiLocali => SezioneCodiceTributo::tributiLocali,
            self::elide => SezioneCodiceTributo::elementiIdentificativi,
            self::accise => SezioneCodiceTributo::accise,
            default => null,
        };
    }

    /**
     * Campi del rigo usati dalla sezione (oltre agli importi), nell'ordine
     * del modello.
     *
     * @return string[]
     */
    public function getCampi(): array
    {
        return match ($this) {
            self::erario => ['codiceTributo', 'numeroCertificazione', 'rateazione', 'annoRiferimento'],
            self::inps => ['codiceSede', 'causale', 'posizione', 'periodoDa', 'periodoA'],
            self::regioni => ['codiceEnte', 'codiceTributo', 'rateazione', 'annoRiferimento'],
            self::tributiLocali => ['codiceEnte', 'ravvedimento', 'immobiliVariati', 'acconto', 'saldo', 'numeroImmobili', 'codiceTributo', 'rateazione', 'annoRiferimento'],
            self::inail => ['codiceSede', 'posizione', 'cifraControllo', 'numeroRiferimento', 'causale'],
            self::altriEnti => ['codiceEnte', 'codiceSede', 'causale', 'posizione', 'periodoDa', 'periodoA'],
            self::elide => ['tipoElide', 'elementiIdentificativi', 'codiceTributo', 'annoRiferimento'],
            self::accise => ['codiceTributo', 'provincia', 'codiceIdentificativoEnte', 'rateazione', 'meseRiferimento', 'annoRiferimento'],
        };
    }

    public function usaCampo(string $campo): bool
    {
        return in_array($campo, $this->getCampi(), true);
    }
}
