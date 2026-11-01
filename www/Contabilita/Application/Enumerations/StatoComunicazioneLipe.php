<?php

namespace Contabilita\Application\Enumerations;

enum StatoComunicazioneLipe: string
{

    case bozza = 'B';
    case inviata = 'I';

    public function getLabel(): string
    {
        return match ($this) {
            self::bozza => 'Bozza',
            self::inviata => 'Inviata',
        };
    }

    public function getClasseBadge(): string
    {
        return match ($this) {
            self::bozza => 'text-bg-secondary',
            self::inviata => 'text-bg-success',
        };
    }
}
