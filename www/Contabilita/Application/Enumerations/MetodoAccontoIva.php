<?php

namespace Contabilita\Application\Enumerations;

/**
 * Metodo di calcolo dell'acconto IVA (art. 6, c. 2, L. 405/1990):
 * storico (88% del versamento dell'ultimo periodo dell'anno precedente),
 * previsionale (88% di quello presunto dell'anno in corso) o analitico
 * (liquidazione delle operazioni fino al 20 dicembre).
 */
enum MetodoAccontoIva: string
{

    case storico = 'S';
    case previsionale = 'P';
    case analitico = 'A';

    public const PERCENTUALE = 88.0;

    public function getLabel(): string
    {
        return match ($this) {
            self::storico => 'Storico (88% dell\'anno precedente)',
            self::previsionale => 'Previsionale (88% del dovuto presunto)',
            self::analitico => 'Analitico (operazioni fino al 20 dicembre)',
        };
    }

    public function getImporto(float $base): float
    {
        return $this === self::analitico ? round(max(0.0, $base), 2) : round(max(0.0, $base) * self::PERCENTUALE / 100, 2);
    }
}
