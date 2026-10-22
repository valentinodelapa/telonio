<?php

namespace Deleghe\Application\Enumerations;

/**
 * Colonna "Modalità di utilizzo" delle tabelle dell'Agenzia: il codice si
 * usa solo per i versamenti, solo per i crediti in compensazione o per
 * entrambi.
 */
enum ModalitaUtilizzo: string
{

    case debito = 'D';
    case credito = 'R';
    case entrambi = 'E';

    public function ammetteDebito(): bool
    {
        return $this !== self::credito;
    }

    public function ammetteCredito(): bool
    {
        return $this !== self::debito;
    }
}
