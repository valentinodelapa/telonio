<?php

namespace Successioni\Application\Enumerations;

/**
 * Stato della dichiarazione nello studio.
 */
enum StatoDichiarazioneSuccessione: string
{

    case bozza = 'B';
    case presentata = 'P';

    public function getLabel(): string
    {
        return match ($this) {
            self::bozza => 'Bozza',
            self::presentata => 'Presentata',
        };
    }

    public function getClasseBadge(): string
    {
        return match ($this) {
            self::bozza => 'text-bg-secondary',
            self::presentata => 'text-bg-success',
        };
    }
}
