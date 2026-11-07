<?php

namespace Deleghe\Application\Enumerations;

use Telonio\Application\Enumerations\TipoCasellaPosta;

/**
 * Come la delega confermata è stata consegnata al cliente, che la paga da
 * sé (invece della trasmissione telematica dello studio). Le deleghe
 * inviate da Telonio hanno il canale della casella usata (e-mail o PEC).
 *
 * Fino a Deleghe 0.6 "E" comprendeva anche la PEC.
 */
enum CanaleConsegna: string
{

    case aMano = 'M';
    case email = 'E';
    case pec = 'P';
    case areaClienti = 'A';
    case altro = 'X';

    public function getLabel(): string
    {
        return match ($this) {
            self::aMano => 'A mano',
            self::email => 'E-mail',
            self::pec => 'PEC',
            self::areaClienti => 'Area clienti',
            self::altro => 'Altro',
        };
    }

    public static function daCasella(TipoCasellaPosta $tipo): self
    {
        return match ($tipo) {
            TipoCasellaPosta::email => self::email,
            TipoCasellaPosta::pec => self::pec,
        };
    }
}
