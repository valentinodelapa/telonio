<?php

namespace Imu\Application\Enumerations;

enum StatoDichiarazione: string
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
