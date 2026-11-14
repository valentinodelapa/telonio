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
    case registroBeni = 'B';

    public function getLabel(): string
    {
        return match ($this) {
            self::giornale => 'Libro giornale',
            self::registroIva => 'Registro IVA',
            self::registroBeni => 'Registro dei beni ammortizzabili',
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
            // compilato entro il termine della dichiarazione (dal 2027 testo unico adempimenti e accertamento)
            self::registroBeni => (($anno ?? (int) date('Y')) >= 2027) ? 'art. 22 D.Lgs. 141/2026' : 'art. 16 D.P.R. 600/1973',
        };
    }
}
