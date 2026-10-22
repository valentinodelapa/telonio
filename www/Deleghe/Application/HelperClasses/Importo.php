<?php

namespace Deleghe\Application\HelperClasses;

/**
 * Importi in euro: normalizzazione di quanto digitato nei form e
 * formattazione per le viste.
 */
class Importo
{

    /**
     * "1.050,25"; stringa vuota per zero se richiesto (come nel modello F24,
     * dove gli importi nulli non si scrivono).
     */
    public static function euro(float $importo, bool $vuotoSeZero = false): string
    {
        return ($vuotoSeZero && round($importo, 2) == 0) ? '' : number_format($importo, 2, ',', '.');
    }

    /**
     * Accetta sia "1.050,25" sia "1050.25"; il valore vuoto resta vuoto.
     */
    public static function normalizza(string $valore): string
    {
        $valore = str_replace([' ', '€'], '', trim($valore));
        return str_contains($valore, ',') ? str_replace(['.', ','], ['', '.'], $valore) : $valore;
    }
}
