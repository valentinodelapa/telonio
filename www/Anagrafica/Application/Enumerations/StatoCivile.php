<?php

namespace Anagrafica\Application\Enumerations;

enum StatoCivile: string
{

    case celibeNubile = 'CN';
    case coniugato = 'CG';
    case unioneCivile = 'UC';
    case separato = 'SE';
    case divorziato = 'DI';
    case vedovo = 'VE';

    public function getLabel(): string
    {
        return match ($this) {
            self::celibeNubile => 'Celibe / nubile',
            self::coniugato => 'Coniugato/a',
            self::unioneCivile => 'Unito/a civilmente',
            self::separato => 'Separato/a',
            self::divorziato => 'Divorziato/a',
            self::vedovo => 'Vedovo/a',
        };
    }
}
