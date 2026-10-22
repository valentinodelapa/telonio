<?php

namespace Successioni\Application\Enumerations;

/**
 * Tipo di cespite del quadro ER (tabella TipoCespite delle specifiche
 * SUC13).
 */
enum TipoCespite: string
{

    case beniInventariati = 'BI';
    case crediti = 'CR';
    case denaroGioielliMobilia = 'DN';
    case altriBeni = 'AB';
    case cosaGenerica = 'GD';

    public function getLabel(): string
    {
        return match ($this) {
            self::beniInventariati => 'Beni inventariati',
            self::crediti => 'Crediti, rimborsi fiscali, rendite, pensioni e somme depositate in conto corrente',
            self::denaroGioielliMobilia => 'Denaro, gioielli e mobilia non inventariati',
            self::altriBeni => 'Altri beni',
            self::cosaGenerica => 'Cosa genericamente determinata (art. 653 c.c.)',
        };
    }
}
