<?php

namespace Deleghe\Application\Enumerations;

/**
 * Indicazione di un campo richiesta da un codice tributo o da una causale
 * ("SI", "FA" o assente nelle tabelle dell'Agenzia).
 */
enum ObbligoCampo: string
{

    case obbligatorio = 'O';
    case facoltativo = 'F';
    case nonPrevisto = 'N';

    public function isAmmesso(): bool
    {
        return $this !== self::nonPrevisto;
    }
}
