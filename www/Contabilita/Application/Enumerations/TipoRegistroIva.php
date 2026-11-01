<?php

namespace Contabilita\Application\Enumerations;

/**
 * Registri IVA: fatture emesse (art. 23 D.P.R. 633/1972), corrispettivi
 * (art. 24) e acquisti (art. 25).
 */
enum TipoRegistroIva: string
{

    case vendite = 'V';
    case acquisti = 'A';
    case corrispettivi = 'C';

    public function getLabel(): string
    {
        return match ($this) {
            self::vendite => 'Registro delle fatture emesse',
            self::acquisti => 'Registro degli acquisti',
            self::corrispettivi => 'Registro dei corrispettivi',
        };
    }

    public function getLabelBreve(): string
    {
        return match ($this) {
            self::vendite => 'Vendite',
            self::acquisti => 'Acquisti',
            self::corrispettivi => 'Corrispettivi',
        };
    }

    public function getNorma(): string
    {
        return match ($this) {
            self::vendite => 'art. 23 D.P.R. 633/1972',
            self::acquisti => 'art. 25 D.P.R. 633/1972',
            self::corrispettivi => 'art. 24 D.P.R. 633/1972',
        };
    }

    /**
     * L'IVA del registro è a debito (vendite e corrispettivi) o a credito.
     */
    public function isDebito(): bool
    {
        return $this !== self::acquisti;
    }
}
