<?php

namespace Successioni\Application\Enumerations;

/**
 * Tipo di titolo del quadro EO (tabella Titoli_Type delle specifiche
 * SUC13).
 */
enum TipoTitolo: string
{

    case quotato = '1';
    case nonQuotato = '2';
    case esente = '3';
    case fondoComune = '4';

    public function getLabel(): string
    {
        return match ($this) {
            self::quotato => 'Titolo quotato in borsa o negoziato nel mercato ristretto',
            self::nonQuotato => 'Titolo non quotato in borsa',
            self::esente => 'Titolo esente compreso nell\'attivo (BOT, CCT, BTP, buoni postali, ...)',
            self::fondoComune => 'Fondo comune d\'investimento',
        };
    }

    /**
     * Il valore va indicato come "valore esente" (titoli di Stato e
     * assimilati, art. 12 TUS).
     */
    public function isEsente(): bool
    {
        return $this === self::esente;
    }
}
