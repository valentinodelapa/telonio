<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Enumerations\PeriodicitaIva;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Application\HelperClasses\Calendario;

/**
 * Periodi delle liquidazioni IVA e regole dei versamenti:
 *
 * - mensili (art. 1, c. 1 e 4, D.P.R. 100/1998): versamento entro il 16
 *   del mese successivo, codici 6001-6012; il versamento di dicembre entro
 *   il 16 gennaio;
 * - trimestrali per opzione (art. 7, c. 1, lett. a, e c. 3, D.P.R.
 *   542/1999): primi tre trimestri entro il 16 del secondo mese
 *   successivo, codici 6031-6033, con gli interessi dell'1%; il quarto
 *   trimestre si versa con il saldo della dichiarazione annuale entro il
 *   16 marzo (codice 6099), sempre con l'1%;
 * - importo non superiore a 100 euro (dalle liquidazioni del 2024, D.Lgs.
 *   1/2024): si versa con il periodo successivo e comunque entro il 16
 *   dicembre (mensili, mesi gennaio-novembre) o il 16 novembre
 *   (trimestrali, primi tre trimestri). Il rinvio non si applica quindi
 *   a novembre e al terzo trimestre, che scadono a quelle date, né a
 *   dicembre e al quarto trimestre; prima del 2024 la soglia era 25,82
 *   euro (lire cinquantamila);
 * - termini che cadono di sabato o festivo al primo giorno lavorativo,
 *   quelli dal 1° al 20 agosto al 20 agosto (art. 3-quater D.L. 16/2012).
 */
final class PeriodiIva
{

    public const SOGLIA_VERSAMENTO = 100.0;
    public const SOGLIA_VERSAMENTO_ANTE_2024 = 25.82;
    public const INTERESSI_TRIMESTRALI = 1.0;

    public static function getNumeroPeriodi(PeriodicitaIva $periodicita): int
    {
        return $periodicita === PeriodicitaIva::trimestrale ? 4 : 12;
    }

    public static function getInizio(PeriodicitaIva $periodicita, int $anno, int $periodo): SismaDate
    {
        $mese = $periodicita === PeriodicitaIva::trimestrale ? ($periodo - 1) * 3 + 1 : $periodo;
        return new SismaDate(sprintf('%04d-%02d-01', $anno, $mese));
    }

    public static function getFine(PeriodicitaIva $periodicita, int $anno, int $periodo): SismaDate
    {
        $mese = $periodicita === PeriodicitaIva::trimestrale ? $periodo * 3 : $periodo;
        return new SismaDate((new \DateTimeImmutable(sprintf('%04d-%02d-01', $anno, $mese)))->format('Y-m-t'));
    }

    /**
     * Periodo che comprende la data.
     */
    public static function getPeriodo(PeriodicitaIva $periodicita, \DateTimeInterface $data): int
    {
        $mese = (int) $data->format('n');
        return $periodicita === PeriodicitaIva::trimestrale ? intdiv($mese - 1, 3) + 1 : $mese;
    }

    /**
     * Termine di versamento del periodo, con gli slittamenti.
     */
    public static function getScadenza(PeriodicitaIva $periodicita, int $anno, int $periodo): SismaDate
    {
        if ($periodicita === PeriodicitaIva::trimestrale) {
            $data = $periodo === 4 ? sprintf('%04d-03-16', $anno + 1) : sprintf('%04d-%02d-16', $anno, $periodo * 3 + 2);
        } else {
            $data = $periodo === 12 ? sprintf('%04d-01-16', $anno + 1) : sprintf('%04d-%02d-16', $anno, $periodo + 1);
        }
        return Calendario::termineFiscale(new \DateTimeImmutable($data));
    }

    /**
     * Codice tributo del versamento del periodo.
     */
    public static function getCodiceTributo(PeriodicitaIva $periodicita, int $periodo): string
    {
        if ($periodicita === PeriodicitaIva::trimestrale) {
            return $periodo === 4 ? '6099' : '603' . $periodo;
        }
        return sprintf('60%02d', $periodo);
    }

    public static function getCodiceTributoAcconto(PeriodicitaIva $periodicita): string
    {
        return $periodicita === PeriodicitaIva::trimestrale ? '6035' : '6013';
    }

    /**
     * Termine dell'acconto: 27 dicembre (art. 6, c. 2, L. 405/1990), al
     * primo giorno lavorativo se festivo.
     */
    public static function getScadenzaAcconto(int $anno): SismaDate
    {
        return Calendario::termineFiscale(new \DateTimeImmutable($anno . '-12-27'), false);
    }

    /**
     * Termine della comunicazione delle liquidazioni periodiche del
     * trimestre: ultimo giorno del secondo mese successivo, 30 settembre
     * per il secondo e fine di febbraio per il quarto (art. 21-bis, c. 1,
     * D.L. 78/2010), al primo giorno lavorativo se festivo.
     */
    public static function getScadenzaLipe(int $anno, int $trimestre): SismaDate
    {
        $mese = match ($trimestre) {
            2 => 9,
            4 => 2,
            default => $trimestre * 3 + 2,
        };
        $mese = new \DateTimeImmutable(sprintf('%04d-%02d-01', $trimestre === 4 ? $anno + 1 : $anno, $mese));
        return Calendario::termineFiscale($mese->modify('last day of this month'));
    }

    /**
     * Soglia sotto cui (o alla quale) il versamento si rinvia al periodo
     * successivo.
     */
    public static function getSoglia(int $anno): float
    {
        return $anno >= 2024 ? self::SOGLIA_VERSAMENTO : self::SOGLIA_VERSAMENTO_ANTE_2024;
    }

    /**
     * Il debito del periodo non superiore alla soglia si può rinviare al
     * periodo successivo (non per l'ultimo periodo utile dell'anno).
     */
    public static function isRinviabile(PeriodicitaIva $periodicita, int $periodo, float $importo, int $anno): bool
    {
        $ultimoRinviabile = $periodicita === PeriodicitaIva::trimestrale ? 2 : 10;
        return ($periodo <= $ultimoRinviabile) && ($importo > 0) && ($importo <= self::getSoglia($anno));
    }

    /**
     * Ultimo periodo dell'anno, da cui si scomputa l'acconto.
     */
    public static function isUltimoPeriodo(PeriodicitaIva $periodicita, int $periodo): bool
    {
        return $periodo === self::getNumeroPeriodi($periodicita);
    }
}
