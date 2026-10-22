<?php

namespace Successioni\Application\Enumerations;

/**
 * Documento da cui risulta il valore di azioni e quote non quotate (quadro
 * EO; tabella TipoDocumento delle specifiche SUC13).
 */
enum TipoDocumentoTitolo: string
{

    case bilancio = '1';
    case inventario = '2';
    case altro = '3';

    public function getLabel(): string
    {
        return match ($this) {
            self::bilancio => 'Bilancio',
            self::inventario => 'Inventario',
            self::altro => 'Altro documento',
        };
    }
}
