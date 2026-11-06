<?php

namespace Imu\Application\Services;

use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;

/**
 * Dati dell'immobile e del possessore con cui si verificano le condizioni
 * delle aliquote differenziate (CompatibilitaDifferenziate). Ente e
 * inagibile valgono true quando il dato non è noto: non escludono nulla.
 */
final readonly class ContestoAliquota
{

    public function __construct(
            public ?CategoriaCatastale $categoria = null,
            public ?UtilizzoImmobile $utilizzo = null,
            public bool $ente = true,
            public bool $inagibile = true,
            public bool $comodatoParenti = false,
            public bool $pensionatoEstero = false,
            public bool $alloggioIacp = false)
    {

    }
}
