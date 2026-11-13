<?php

namespace Contabilita\Application\Enumerations;

use Contabilita\Application\HelperClasses\RiferimentiIva;

/**
 * Registri IVA: fatture emesse (art. 23 D.P.R. 633/1972), corrispettivi
 * (art. 24) e acquisti (art. 25); dal 2027 artt. 86, 87 e 88 del Testo
 * unico IVA (D.Lgs. 10/2026).
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

    /**
     * Norma del registro per la data o l'anno indicati (di default oggi).
     */
    public function getNorma(\DateTimeInterface|int|null $quando = null): string
    {
        return RiferimentiIva::cita(match ($this) {
            self::vendite => 'registroVendite',
            self::acquisti => 'registroAcquisti',
            self::corrispettivi => 'registroCorrispettivi',
        }, $quando);
    }

    /**
     * L'IVA del registro è a debito (vendite e corrispettivi) o a credito.
     */
    public function isDebito(): bool
    {
        return $this !== self::acquisti;
    }
}
