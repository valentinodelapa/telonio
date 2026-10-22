<?php

namespace Successioni\Application\Enumerations;

/**
 * Tipologia del terreno del quadro EB (tabella TerrenoEdificabile_Type
 * delle specifiche).
 */
enum TipologiaTerreno: string
{

    case edificabile = '1';
    case parzialmenteEdificabile = '2';
    case nonEdificabile = '3';

    public function getLabel(): string
    {
        return match ($this) {
            self::edificabile => 'Edificabile',
            self::parzialmenteEdificabile => 'Parzialmente edificabile',
            self::nonEdificabile => 'Non edificabile',
        };
    }
}
