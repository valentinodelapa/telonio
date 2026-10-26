<?php

namespace GestioneStudio\Application\Enumerations;

/**
 * Stato di lavorazione di una scadenza nello studio. Le scadenze senza
 * stato registrato sono da fare; quelle che il modulo di origine considera
 * eseguite risultano completate.
 */
enum StatoLavorazione: string
{

    case daFare = 'D';
    case inLavorazione = 'L';
    case completata = 'C';
    case nonDovuta = 'N';

    public function getLabel(): string
    {
        return match ($this) {
            self::daFare => 'Da fare',
            self::inLavorazione => 'In lavorazione',
            self::completata => 'Completata',
            self::nonDovuta => 'Non dovuta',
        };
    }

    public function getClasseBadge(): string
    {
        return match ($this) {
            self::daFare => 'text-bg-secondary',
            self::inLavorazione => 'text-bg-warning',
            self::completata => 'text-bg-success',
            self::nonDovuta => 'text-bg-light border',
        };
    }

    /**
     * Ancora da eseguire: compare tra le scadenze aperte e, oltre il
     * termine, tra le scadute.
     */
    public function isAperta(): bool
    {
        return ($this === self::daFare) || ($this === self::inLavorazione);
    }

    /**
     * @return StatoLavorazione[]
     */
    public static function aperti(): array
    {
        return [self::daFare, self::inLavorazione];
    }
}
