<?php

namespace Deleghe\Application\Enumerations;

/**
 * Cadenza con cui si ripete un versamento (Services\CopiaVersamenti).
 */
enum Periodicita: string
{

    case mensile = 'M';
    case trimestrale = 'T';
    case semestrale = 'S';
    case annuale = 'A';

    public function getLabel(): string
    {
        return match ($this) {
            self::mensile => 'Ogni mese',
            self::trimestrale => 'Ogni tre mesi',
            self::semestrale => 'Ogni sei mesi',
            self::annuale => 'Ogni anno',
        };
    }

    public function getMesi(): int
    {
        return match ($this) {
            self::mensile => 1,
            self::trimestrale => 3,
            self::semestrale => 6,
            self::annuale => 12,
        };
    }
}
