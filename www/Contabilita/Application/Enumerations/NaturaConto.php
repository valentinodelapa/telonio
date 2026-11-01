<?php

namespace Contabilita\Application\Enumerations;

/**
 * Natura del conto: patrimoniale (attività; passività e patrimonio netto),
 * economico (costi, ricavi) o di sistema (aperture e chiusure). Determina
 * la sezione del bilancio di verifica; i fondi rettificativi (es. fondi
 * ammortamento) hanno la natura dei beni che rettificano.
 */
enum NaturaConto: string
{

    case attivita = 'A';
    case passivita = 'P';
    case costo = 'C';
    case ricavo = 'R';
    case sistema = 'S';

    public function getLabel(): string
    {
        return match ($this) {
            self::attivita => 'Attività',
            self::passivita => 'Passività e patrimonio netto',
            self::costo => 'Costi',
            self::ricavo => 'Ricavi',
            self::sistema => 'Conti di sistema',
        };
    }

    public function isPatrimoniale(): bool
    {
        return ($this === self::attivita) || ($this === self::passivita);
    }

    public function isEconomico(): bool
    {
        return ($this === self::costo) || ($this === self::ricavo);
    }

    /**
     * Sezione in cui il saldo è "normale": dare per attività e costi.
     */
    public function isSaldoDare(): bool
    {
        return ($this === self::attivita) || ($this === self::costo);
    }
}
