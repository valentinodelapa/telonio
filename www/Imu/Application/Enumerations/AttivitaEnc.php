<?php

namespace Imu\Application\Enumerations;

/**
 * Attività degli enti non commerciali per le quali gli immobili utilizzati
 * con modalità non commerciali sono esenti (art. 1, comma 759, lett. g, L.
 * 160/2019; art. 7, c. 1, lett. i, D.Lgs. 504/1992), nell'ordine delle
 * caselle del quadro B della dichiarazione IMU ENC.
 */
enum AttivitaEnc: string
{

    case assistenziali = 'AS';
    case previdenziali = 'PR';
    case sanitarie = 'SA';
    case didattiche = 'DI';
    case ricettive = 'RI';
    case culturali = 'CU';
    case ricreative = 'RC';
    case sportive = 'SP';
    case religione = 'RE';
    case ricerca = 'RS';

    public function getLabel(): string
    {
        return match ($this) {
            self::assistenziali => 'Assistenziali',
            self::previdenziali => 'Previdenziali',
            self::sanitarie => 'Sanitarie',
            self::didattiche => 'Didattiche',
            self::ricettive => 'Ricettive',
            self::culturali => 'Culturali',
            self::ricreative => 'Ricreative',
            self::sportive => 'Sportive',
            self::religione => 'Di religione e di culto',
            self::ricerca => 'Di ricerca scientifica',
        };
    }

    /**
     * @return self[]
     */
    public static function daCodici(string $codici): array
    {
        return array_values(array_filter(array_map(fn(string $codice) => self::tryFrom(trim($codice)), explode(',', $codici))));
    }
}
