<?php

namespace Contabilita\Application\Enumerations;

/**
 * Stato della fattura elettronica importata.
 */
enum StatoFatturaElettronica: string
{

    case daRegistrare = 'D';
    case registrata = 'R';
    case scartata = 'S';

    public function getLabel(): string
    {
        return match ($this) {
            self::daRegistrare => 'Da registrare',
            self::registrata => 'Registrata',
            self::scartata => 'Scartata',
        };
    }

    public function getColore(): string
    {
        return match ($this) {
            self::daRegistrare => 'warning',
            self::registrata => 'success',
            self::scartata => 'secondary',
        };
    }
}
