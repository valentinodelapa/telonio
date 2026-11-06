<?php

namespace Dichiarativi\Application\Enumerations;

/**
 * Forme della garanzia dei rimborsi IVA (art. 38-bis, c. 5, D.P.R. 633/1972).
 */
enum TipoGaranzia: string
{

    case titoliStato = 'T';
    case fideiussioneBancaria = 'B';
    case fideiussioneImpresa = 'I';
    case polizzaAssicurativa = 'A';
    case consorzioFidi = 'C';
    case obbligazioneCapogruppo = 'G';

    public function getLabel(): string
    {
        return match ($this) {
            self::titoliStato => 'Cauzione in titoli di Stato o garantiti dallo Stato',
            self::fideiussioneBancaria => 'Fideiussione di una banca',
            self::fideiussioneImpresa => 'Fideiussione di un\'impresa commerciale',
            self::polizzaAssicurativa => 'Polizza fideiussoria di un\'impresa di assicurazione',
            self::consorzioFidi => 'Fideiussione di un consorzio o cooperativa di garanzia fidi',
            self::obbligazioneCapogruppo => 'Obbligazione della capogruppo o controllante',
        };
    }
}
