<?php

namespace Imu\Application\Enumerations;

/**
 * Caratteristica dell'immobile nel quadro descrittivo della dichiarazione
 * (campo 1). Il modello ENC prevede solo le prime quattro.
 */
enum CaratteristicaDichiarata: string
{

    case terreno = '1';
    case areaFabbricabile = '2';
    case fabbricatoRendita = '3';
    case fabbricatoContabile = '4';
    case abitazionePrincipale = '5';
    case pertinenza = '6';
    case beneMerce = '7';

    public function getLabel(): string
    {
        return $this->value . ' - ' . match ($this) {
            self::terreno => 'Terreno',
            self::areaFabbricabile => 'Area fabbricabile',
            self::fabbricatoRendita => 'Fabbricato con valore dalla rendita catastale',
            self::fabbricatoContabile => 'Fabbricato con valore dalle scritture contabili',
            self::abitazionePrincipale => 'Abitazione principale',
            self::pertinenza => 'Pertinenza',
            self::beneMerce => 'Bene merce',
        };
    }

    public function isAmmessaEnc(): bool
    {
        return in_array($this, [self::terreno, self::areaFabbricabile, self::fabbricatoRendita, self::fabbricatoContabile], true);
    }
}
