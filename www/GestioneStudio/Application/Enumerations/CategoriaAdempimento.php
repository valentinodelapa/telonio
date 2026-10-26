<?php

namespace GestioneStudio\Application\Enumerations;

enum CategoriaAdempimento: string
{

    case versamento = 'VER';
    case dichiarazione = 'DIC';
    case comunicazione = 'COM';
    case altro = 'ALT';

    public function getLabel(): string
    {
        return match ($this) {
            self::versamento => 'Versamento',
            self::dichiarazione => 'Dichiarazione',
            self::comunicazione => 'Comunicazione',
            self::altro => 'Altro',
        };
    }

    public function getIcona(): string
    {
        return match ($this) {
            self::versamento => 'bi-cash-coin',
            self::dichiarazione => 'bi-file-earmark-text',
            self::comunicazione => 'bi-send',
            self::altro => 'bi-check2-square',
        };
    }
}
