<?php

namespace Telonio\Application\Enumerations;

use Telonio\Application\Services\Aggiornamenti\Versione;

/**
 * Canale degli aggiornamenti: la stabilità minima delle versioni proposte.
 * Ogni canale accetta anche le versioni più stabili (il canale alpha riceve
 * tutte le versioni, quello stabile solo le definitive).
 */
enum CanaleAggiornamenti: string
{

    case alpha = 'alpha';
    case beta = 'beta';
    case rc = 'rc';
    case stabile = 'stabile';

    public function getLabel(): string
    {
        return match ($this) {
            self::alpha => 'Alpha',
            self::beta => 'Beta',
            self::rc => 'Release candidate',
            self::stabile => 'Stabile',
        };
    }

    public function getStabilita(): int
    {
        return match ($this) {
            self::alpha => 0,
            self::beta => 1,
            self::rc => 2,
            self::stabile => 3,
        };
    }

    public function accetta(Versione $versione): bool
    {
        return $versione->getCanale()->getStabilita() >= $this->getStabilita();
    }

    /**
     * Canale della versione installata, usato se non è configurato: chi usa
     * una versione alpha riceve le alpha, chi usa una stabile solo le stabili.
     */
    public static function perVersione(Versione $versione): self
    {
        return $versione->getCanale();
    }
}
