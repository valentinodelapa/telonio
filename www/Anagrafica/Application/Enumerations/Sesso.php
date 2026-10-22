<?php

namespace Anagrafica\Application\Enumerations;

enum Sesso: string
{

    case maschio = 'M';
    case femmina = 'F';

    public function getLabel(): string
    {
        return match ($this) {
            self::maschio => 'Maschio',
            self::femmina => 'Femmina',
        };
    }
}
