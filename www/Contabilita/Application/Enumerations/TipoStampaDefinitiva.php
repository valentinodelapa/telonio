<?php

namespace Contabilita\Application\Enumerations;

/**
 * Libro o registro stampato in via definitiva con le pagine numerate.
 */
enum TipoStampaDefinitiva: string
{

    case giornale = 'G';
    case registroIva = 'R';

    public function getLabel(): string
    {
        return match ($this) {
            self::giornale => 'Libro giornale',
            self::registroIva => 'Registro IVA',
        };
    }

    /**
     * Norma della numerazione progressiva delle pagine.
     */
    public function getNorma(): string
    {
        return match ($this) {
            self::giornale => 'art. 2215 c.c.; art. 2219 c.c.',
            self::registroIva => 'art. 39 D.P.R. 633/1972',
        };
    }
}
