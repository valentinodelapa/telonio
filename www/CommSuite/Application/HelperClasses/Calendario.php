<?php

namespace CommSuite\Application\HelperClasses;

use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Giorni lavorativi ai fini dei termini fiscali, comuni a tutti i moduli
 * della suite: i termini che cadono di sabato o in un giorno festivo sono
 * prorogati al primo giorno lavorativo successivo (art. 7, c. 1, lett. h,
 * D.L. 70/2011; art. 6, c. 8, D.L. 330/1994).
 *
 * Festività nazionali: art. 2 L. 260/1949 e Lunedì dell'Angelo; il 4
 * ottobre (festa nazionale di San Francesco d'Assisi) dal 2026, L.
 * 151/2025.
 */
class Calendario
{

    private const FESTIVI_FISSI = ['01-01', '01-06', '04-25', '05-01', '06-02', '08-15', '11-01', '12-08', '12-25', '12-26'];
    private const FESTIVI_DAL = ['10-04' => 2026];

    public static function isLavorativo(\DateTimeInterface $data): bool
    {
        if ((int) $data->format('N') >= 6) {
            return false;
        }
        $giorno = $data->format('m-d');
        if (in_array($giorno, self::FESTIVI_FISSI, true)) {
            return false;
        }
        if (isset(self::FESTIVI_DAL[$giorno]) && ((int) $data->format('Y') >= self::FESTIVI_DAL[$giorno])) {
            return false;
        }
        return $data->format('Y-m-d') !== self::lunediDellAngelo((int) $data->format('Y'));
    }

    public static function primoGiornoLavorativo(\DateTimeInterface $data): SismaDate
    {
        $giorno = new \DateTimeImmutable($data->format('Y-m-d'));
        while (self::isLavorativo($giorno) === false) {
            $giorno = $giorno->modify('+1 day');
        }
        return new SismaDate($giorno->format('Y-m-d'));
    }

    /**
     * Termine effettivo di un adempimento fiscale o di un versamento
     * unitario: con $prorogaAgosto i termini dal 1° al 20 agosto sono
     * differiti al 20 agosto (art. 37, c. 11-bis, D.L. 223/2006), poi il
     * termine che cade di sabato o in un giorno festivo passa al primo
     * giorno lavorativo successivo.
     */
    public static function termineFiscale(\DateTimeInterface $data, bool $prorogaAgosto = true): SismaDate
    {
        $giorno = new \DateTimeImmutable($data->format('Y-m-d'));
        if ($prorogaAgosto && ($giorno->format('m') === '08') && ((int) $giorno->format('d') <= 20)) {
            $giorno = $giorno->setDate((int) $giorno->format('Y'), 8, 20);
        }
        return self::primoGiornoLavorativo($giorno);
    }

    /**
     * Lunedì dopo Pasqua (algoritmo di Gauss nella forma di Meeus/Jones/Butcher).
     */
    private static function lunediDellAngelo(int $anno): string
    {
        $a = $anno % 19;
        $b = intdiv($anno, 100);
        $c = $anno % 100;
        $h = (19 * $a + $b - intdiv($b, 4) - intdiv(8 * $b + 13, 25) + 15) % 30;
        $l = (32 + 2 * ($b % 4) + 2 * intdiv($c, 4) - $h - ($c % 4)) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mese = intdiv($h + $l - 7 * $m + 114, 31);
        $giorno = (($h + $l - 7 * $m + 114) % 31) + 1;
        return (new \DateTimeImmutable(sprintf('%d-%02d-%02d', $anno, $mese, $giorno)))->modify('+1 day')->format('Y-m-d');
    }

    /**
     * Giorni di calendario da $da (escluso) ad $a (incluso).
     */
    public static function giorniTra(\DateTimeInterface $da, \DateTimeInterface $a): int
    {
        return (int) (new \DateTimeImmutable($da->format('Y-m-d')))->diff(new \DateTimeImmutable($a->format('Y-m-d')))->format('%r%a');
    }
}
