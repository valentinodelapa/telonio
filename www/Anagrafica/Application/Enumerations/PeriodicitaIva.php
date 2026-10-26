<?php

namespace Anagrafica\Application\Enumerations;

/**
 * Periodicità delle liquidazioni e dei versamenti IVA: mensile (art. 1
 * D.P.R. 100/1998) o trimestrale per opzione entro le soglie del volume
 * d'affari (art. 7 D.P.R. 542/1999).
 */
enum PeriodicitaIva: string
{

    case mensile = 'M';
    case trimestrale = 'T';

    public function getLabel(): string
    {
        return match ($this) {
            self::mensile => 'Mensile',
            self::trimestrale => 'Trimestrale',
        };
    }
}
