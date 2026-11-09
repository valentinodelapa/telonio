<?php

namespace Dichiarativi\Application\Services\Redditi;

/**
 * Coefficienti di redditività del regime forfetario per codice ATECO
 * 2007/2022 (allegato 4 alla L. 190/2014 come sostituito dalla L.
 * 145/2018, tabella delle istruzioni del quadro LM del modello Redditi PF
 * 2026, fascicolo 3): fino ai nuovi coefficienti per ATECO 2025 si usa il
 * codice ATECO 2007 corrispondente (art. 1, D.Lgs. 81/2025).
 */
final class CoefficientiForfetari
{

    /** @var list<array{0: string, 1: string, 2: int}> [da, a, coefficiente] su codici a quattro cifre (es. "47.82") */
    private const GRUPPI = [
        ['10.00', '11.99', 40],
        ['45.00', '45.99', 40],
        ['46.20', '46.99', 40],
        ['47.10', '47.79', 40],
        ['47.81', '47.81', 40],
        ['47.82', '47.89', 54],
        ['47.90', '47.99', 40],
        ['41.00', '43.99', 86],
        ['68.00', '68.99', 86],
        ['46.10', '46.19', 62],
        ['55.00', '56.99', 40],
        ['64.00', '66.99', 78],
        ['69.00', '75.99', 78],
        ['85.00', '85.99', 78],
        ['86.00', '88.99', 78],
    ];

    /** Divisioni delle altre attività economiche (67%) */
    private const ALTRE = ['01', '02', '03', '05', '06', '07', '08', '09', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27',
        '28', '29', '30', '31', '32', '33', '35', '36', '37', '38', '39', '49', '50', '51', '52', '53', '58', '59', '60', '61', '62', '63', '77', '78', '79', '80', '81', '82',
        '84', '90', '91', '92', '93', '94', '95', '96', '97', '98', '99'];

    /**
     * Coefficiente in percentuale per il codice ATECO 2007/2022 (es.
     * "69.20.11"), null se il codice non è nella tabella.
     */
    public static function getCoefficiente(string $codice): ?int
    {
        $quattro = str_pad(substr($codice, 0, 5), 5, '0');
        foreach (self::GRUPPI as [$da, $a, $coefficiente]) {
            if (($quattro >= $da) && ($quattro <= $a)) {
                return $coefficiente;
            }
        }
        return in_array(substr($codice, 0, 2), self::ALTRE, true) ? 67 : null;
    }
}
