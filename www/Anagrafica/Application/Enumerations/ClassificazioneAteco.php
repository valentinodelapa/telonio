<?php

namespace Anagrafica\Application\Enumerations;

/**
 * Classificazioni ATECO gestite, con il periodo in cui sono in vigore ai
 * fini fiscali (adozione da parte dell'Agenzia delle Entrate).
 *
 * Per le decorrenze anteriori all'aggiornamento 2022 si usa comunque la
 * classificazione 2022, che ne conserva quasi tutti i codici.
 */
enum ClassificazioneAteco: string
{

    case ateco2022 = '2022';
    case ateco2025 = '2025';

    public function getLabel(): string
    {
        return match ($this) {
            self::ateco2022 => 'ATECO 2007 agg. 2022',
            self::ateco2025 => 'ATECO 2025',
        };
    }

    public function getInVigoreDal(): string
    {
        return match ($this) {
            self::ateco2022 => '2022-04-01',
            self::ateco2025 => '2025-04-01',
        };
    }

    public static function vigenteAl(\DateTimeInterface $data): self
    {
        return $data->format('Y-m-d') >= self::ateco2025->getInVigoreDal() ? self::ateco2025 : self::ateco2022;
    }
}
