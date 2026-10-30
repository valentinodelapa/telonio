<?php

namespace Deleghe\Application\Enumerations;

/**
 * Come la delega confermata è stata consegnata al cliente, che la paga da
 * sé (invece della trasmissione telematica dello studio).
 */
enum CanaleConsegna: string
{

    case aMano = 'M';
    case email = 'E';
    case areaClienti = 'A';
    case altro = 'X';

    public function getLabel(): string
    {
        return match ($this) {
            self::aMano => 'A mano',
            self::email => 'Email / PEC',
            self::areaClienti => 'Area clienti',
            self::altro => 'Altro',
        };
    }
}
