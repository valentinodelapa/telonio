<?php

namespace Successioni\Application\Enumerations;

use Anagrafica\Application\Enumerations\StatoCivile;

/**
 * Stato civile del defunto (tabella StatoCivile delle specifiche SUC13):
 * per i coniugati distingue il regime patrimoniale, che l'Anagrafica non
 * registra.
 */
enum StatoCivileDefunto: string
{

    case celibeNubile = '1';
    case coniugatoComunione = '2';
    case coniugatoSeparazione = '3';
    case vedovo = '4';
    case separato = '5';
    case divorziato = '6';
    case unioneCivile = '7';

    public function getLabel(): string
    {
        return match ($this) {
            self::celibeNubile => 'Celibe / nubile',
            self::coniugatoComunione => 'Coniugato/a in regime di comunione dei beni',
            self::coniugatoSeparazione => 'Coniugato/a in regime di separazione dei beni',
            self::vedovo => 'Vedovo/a',
            self::separato => 'Separato/a',
            self::divorziato => 'Divorziato/a - scioglimento dell\'unione civile',
            self::unioneCivile => 'Parte dell\'unione civile',
        };
    }

    /**
     * Il coniuge (o la parte dell'unione civile) può essere beneficiario
     * (grado di parentela 01).
     */
    public function ammetteConiuge(): bool
    {
        return in_array($this, [self::coniugatoComunione, self::coniugatoSeparazione, self::separato, self::unioneCivile], true);
    }

    /**
     * Proposta dallo stato civile dell'Anagrafica (per i coniugati la
     * comunione, regime legale: va confermato).
     */
    public static function daAnagrafica(?StatoCivile $statoCivile): ?self
    {
        return match ($statoCivile) {
            StatoCivile::celibeNubile => self::celibeNubile,
            StatoCivile::coniugato => self::coniugatoComunione,
            StatoCivile::unioneCivile => self::unioneCivile,
            StatoCivile::separato => self::separato,
            StatoCivile::divorziato => self::divorziato,
            StatoCivile::vedovo => self::vedovo,
            null => null,
        };
    }
}
