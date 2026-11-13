<?php

namespace Contabilita\Application\Enumerations;

use Contabilita\Application\HelperClasses\RiferimentiIva;

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
     * Norma della numerazione progressiva delle pagine per l'anno della
     * stampa (di default l'anno in corso).
     */
    public function getNorma(?int $anno = null): string
    {
        return match ($this) {
            self::giornale => 'art. 2215 c.c.; art. 2219 c.c.',
            self::registroIva => RiferimentiIva::cita('tenutaRegistri', $anno),
        };
    }
}
