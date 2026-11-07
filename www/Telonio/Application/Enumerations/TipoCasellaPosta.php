<?php

namespace Telonio\Application\Enumerations;

/**
 * Caselle da cui lo studio invia i documenti ai clienti: posta elettronica
 * ordinaria o certificata (PEC, con valore di raccomandata con ricevuta di
 * ritorno: d.P.R. 68/2005). Ognuna ha la sua configurazione di invio
 * (CasellaPosta); la scelta si fa a ogni invio.
 */
enum TipoCasellaPosta: string
{

    case email = 'E';
    case pec = 'P';

    public function getLabel(): string
    {
        return match ($this) {
            self::email => 'E-mail',
            self::pec => 'PEC',
        };
    }

    public function getIcona(): string
    {
        return match ($this) {
            self::email => 'bi-envelope',
            self::pec => 'bi-envelope-check',
        };
    }
}
