<?php

namespace Anagrafica\Application\Enumerations;

enum TipoDocumento: string
{

    case cartaIdentita = 'CI';
    case passaporto = 'PA';
    case patente = 'PT';
    case permessoSoggiorno = 'PS';

    public function getLabel(): string
    {
        return match ($this) {
            self::cartaIdentita => "Carta d'identità",
            self::passaporto => 'Passaporto',
            self::patente => 'Patente di guida',
            self::permessoSoggiorno => 'Permesso di soggiorno',
        };
    }
}
