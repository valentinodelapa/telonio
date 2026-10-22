<?php

namespace Successioni\Application\Enumerations;

/**
 * Riduzione dell'imposta per beni pervenuti al defunto da una successione o
 * donazione nei cinque anni precedenti (art. 25, c. 1, TUS; tabella
 * CodiciRiduzioni25comma1 delle specifiche SUC13).
 */
enum RiduzioneSuccessioniPrecedenti: string
{

    case unAnno = '1';
    case dueAnni = '2';
    case treAnni = '3';
    case quattroAnni = '4';
    case cinqueAnni = '5';

    public function getLabel(): string
    {
        return match ($this) {
            self::unAnno => 'Entro un anno',
            self::dueAnni => 'Entro due anni',
            self::treAnni => 'Entro tre anni',
            self::quattroAnni => 'Entro quattro anni',
            self::cinqueAnni => 'Entro cinque anni',
        };
    }

    /**
     * Quota dell'imposta riferibile ai beni che si riduce: 50% entro un
     * anno, poi 10 punti in meno per ogni anno.
     */
    public function getRiduzione(): float
    {
        return (6 - (int) $this->value) / 10;
    }
}
