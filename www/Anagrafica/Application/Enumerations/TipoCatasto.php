<?php

namespace Anagrafica\Application\Enumerations;

enum TipoCatasto: string
{

    case fabbricati = 'F';
    case terreni = 'T';

    public function getLabel(): string
    {
        return match ($this) {
            self::fabbricati => 'Fabbricato',
            self::terreni => 'Terreno',
        };
    }

    public function getIcona(): string
    {
        return match ($this) {
            self::fabbricati => 'bi-house-door',
            self::terreni => 'bi-tree',
        };
    }
}
