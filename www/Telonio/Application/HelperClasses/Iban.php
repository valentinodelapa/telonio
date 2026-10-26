<?php

namespace Telonio\Application\HelperClasses;

/**
 * Controllo formale dell'IBAN (ISO 13616): struttura e cifre di controllo
 * modulo 97; per i conti italiani anche la lunghezza (27 caratteri) e il CIN
 * del BBAN.
 */
class Iban
{

    private const LUNGHEZZA_ITALIA = 27;
    /**
     * Valori per le posizioni dispari nel calcolo del CIN (stessa tabella del
     * carattere di controllo del codice fiscale), per cifra 0-9 o lettera A-Z.
     */
    private const DISPARI = [1, 0, 5, 7, 9, 13, 15, 17, 19, 21, 2, 4, 18, 20, 11, 3, 6, 8, 12, 14, 16, 10, 22, 25, 24, 23];

    /**
     * CIN del BBAN italiano per ABI, CAB e numero di conto (22 caratteri).
     */
    public static function calcolaCin(string $abiCabConto): string
    {
        $somma = 0;
        foreach (str_split(strtoupper($abiCabConto)) as $indice => $carattere) {
            $valore = ctype_digit($carattere) ? (int) $carattere : ord($carattere) - 65;
            $somma += ($indice % 2 === 0) ? self::DISPARI[$valore] : $valore;
        }
        return chr(65 + $somma % 26);
    }

    public static function normalizza(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban));
    }

    public static function isValido(string $iban): bool
    {
        if (preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban) !== 1) {
            return false;
        }
        if (str_starts_with($iban, 'IT') && ((strlen($iban) !== self::LUNGHEZZA_ITALIA) || (preg_match('/^IT\d{2}[A-Z]\d{10}[A-Z0-9]{12}$/', $iban) !== 1) || (self::calcolaCin(substr($iban, 5)) !== $iban[4]))) {
            return false;
        }
        $numerico = '';
        foreach (str_split(substr($iban, 4) . substr($iban, 0, 4)) as $carattere) {
            $numerico .= ctype_alpha($carattere) ? (string) (ord($carattere) - 55) : $carattere;
        }
        $resto = 0;
        foreach (str_split($numerico, 7) as $blocco) {
            $resto = (int) ($resto . $blocco) % 97;
        }
        return $resto === 1;
    }
}
