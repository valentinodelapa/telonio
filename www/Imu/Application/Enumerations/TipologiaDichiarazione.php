<?php

namespace Imu\Application\Enumerations;

/**
 * Tipologia della dichiarazione: la sostitutiva ripresenta integralmente
 * una dichiarazione già presentata per lo stesso anno, comune e
 * contribuente.
 */
enum TipologiaDichiarazione: string
{

    case nuova = 'N';
    case sostitutiva = 'S';

    public function getLabel(): string
    {
        return match ($this) {
            self::nuova => 'Nuova',
            self::sostitutiva => 'Sostitutiva',
        };
    }
}
