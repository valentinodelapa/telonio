<?php

namespace Dichiarativi\Application\Enumerations;

/**
 * Tipo della dichiarazione nel frontespizio: ordinaria, correttiva entro
 * il termine di presentazione, integrativa dopo il termine (art. 8, c. 6-bis,
 * D.P.R. 322/1998).
 */
enum TipologiaDichiarazione: string
{

    case ordinaria = 'O';
    case correttiva = 'C';
    case integrativa = 'I';

    public function getLabel(): string
    {
        return match ($this) {
            self::ordinaria => 'Ordinaria',
            self::correttiva => 'Correttiva nei termini',
            self::integrativa => 'Integrativa',
        };
    }
}
