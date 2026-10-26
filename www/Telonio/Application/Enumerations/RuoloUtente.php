<?php

namespace Telonio\Application\Enumerations;

/**
 * Ruolo dell'operatore all'interno dello studio.
 */
enum RuoloUtente: string
{

    case amministratore = 'amministratore';
    case operatore = 'operatore';

    public function getLabel(): string
    {
        return match ($this) {
            self::amministratore => 'Amministratore',
            self::operatore => 'Operatore',
        };
    }

    public function getDescrizione(): string
    {
        return match ($this) {
            self::amministratore => 'Gestisce i dati dello studio, gli utenti e opera su tutti gli applicativi',
            self::operatore => 'Opera su tutti gli applicativi dello studio',
        };
    }
}
