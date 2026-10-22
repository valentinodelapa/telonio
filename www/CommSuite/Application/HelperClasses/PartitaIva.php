<?php

namespace CommSuite\Application\HelperClasses;

/**
 * Validazione della partita IVA italiana (e del codice fiscale numerico dei
 * soggetti diversi dalle persone fisiche, che usa lo stesso algoritmo).
 */
class PartitaIva
{

    public static function normalizza(string $partitaIva): string
    {
        $partitaIva = strtoupper(preg_replace('/\s+/', '', $partitaIva));
        return str_starts_with($partitaIva, 'IT') ? substr($partitaIva, 2) : $partitaIva;
    }

    public static function isValida(string $partitaIva): bool
    {
        $partitaIva = self::normalizza($partitaIva);
        if (preg_match('/^\d{11}$/', $partitaIva) !== 1) {
            return false;
        }
        return self::calcolaCarattereControllo(substr($partitaIva, 0, 10)) === (int) $partitaIva[10];
    }

    private static function calcolaCarattereControllo(string $primeDieciCifre): int
    {
        $somma = 0;
        for ($i = 0; $i < 10; $i++) {
            $cifra = (int) $primeDieciCifre[$i];
            if ($i % 2 === 1) {
                $cifra *= 2;
                if ($cifra > 9) {
                    $cifra -= 9;
                }
            }
            $somma += $cifra;
        }
        return (10 - ($somma % 10)) % 10;
    }
}
