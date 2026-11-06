<?php

namespace Imu\Application\Enumerations;

/**
 * Imposte immobiliari proprie dei territori in cui non si applica l'IMU
 * (art. 1, comma 739, L. 160/2019): il modulo non le calcola.
 */
enum ImpostaTerritoriale: string
{

    case imis = 'IMIS';
    case imi = 'IMI';
    case ilia = 'ILIA';

    private const PROVINCE_FRIULI = ['GO', 'PN', 'TS', 'UD'];
    private const PRIMO_ANNO_ILIA = 2023;

    /**
     * Imposta applicata nella provincia per l'anno, null se vale l'IMU.
     */
    public static function perProvincia(?string $siglaProvincia, int $anno): ?self
    {
        return match (true) {
            $siglaProvincia === 'TN' => self::imis,
            $siglaProvincia === 'BZ' => self::imi,
            in_array($siglaProvincia, self::PROVINCE_FRIULI, true) && ($anno >= self::PRIMO_ANNO_ILIA) => self::ilia,
            default => null,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::imis => 'IMIS della Provincia autonoma di Trento (L.P. 14/2014)',
            self::imi => 'IMI della Provincia autonoma di Bolzano (L.P. 3/2014)',
            self::ilia => 'ILIA della Regione Friuli Venezia Giulia (L.R. 17/2022)',
        };
    }
}
