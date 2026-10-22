<?php

namespace Successioni\Application\Enumerations;

/**
 * Residenza estera del defunto (tabella ResidenzaEstera delle specifiche
 * SUC13).
 */
enum ResidenzaEsteraDefunto: string
{

    case maiInItalia = '1';
    case inParteInItalia = '2';

    public function getLabel(): string
    {
        return match ($this) {
            self::maiInItalia => 'Mai residente in Italia o residenza ignota',
            self::inParteInItalia => 'Residente in parte in Italia e in parte all\'estero',
        };
    }
}
