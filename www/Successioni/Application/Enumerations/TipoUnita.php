<?php

namespace Successioni\Application\Enumerations;

/**
 * Tipo di unità da diporto del quadro EQ (tabella TipoUnita_Type delle
 * specifiche SUC13): per la vela si indica la lunghezza in centimetri, per
 * il motore la stazza in tonnellate.
 */
enum TipoUnita: string
{

    case vela = '1';
    case motore = '2';

    public function getLabel(): string
    {
        return match ($this) {
            self::vela => 'Unità a vela',
            self::motore => 'Unità a motore',
        };
    }

    public function getDimensione(): string
    {
        return match ($this) {
            self::vela => 'Lunghezza (cm)',
            self::motore => 'Stazza (tonnellate)',
        };
    }
}
