<?php

namespace Imu\Application\Enumerations;

/**
 * Modelli della dichiarazione IMU (D.M. 24 aprile 2024): persone fisiche
 * ed enti commerciali, da presentare quando si verificano variazioni non
 * conoscibili dal comune; enti non commerciali, ogni anno (comma 768-bis,
 * L. 160/2019).
 */
enum ModelloDichiarazione: string
{

    case ordinario = 'EC';
    case entiNonCommerciali = 'NC';

    public function getLabel(): string
    {
        return match ($this) {
            self::ordinario => 'IMU/IMPi persone fisiche ed enti commerciali',
            self::entiNonCommerciali => 'IMU enti non commerciali',
        };
    }

    /**
     * Codice fornitura del record di testa.
     */
    public function getCodiceFornitura(): string
    {
        return match ($this) {
            self::ordinario => 'TAT00',
            self::entiNonCommerciali => 'TAS00',
        };
    }
}
