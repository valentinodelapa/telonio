<?php

namespace Telonio\Application\HelperClasses;

/**
 * Validazione, calcolo e decodifica del codice fiscale delle persone fisiche
 * (DM 23/12/1976), omocodie comprese.
 */
class CodiceFiscale
{

    private const CODICI_MESE = 'ABCDEHLMPRST';
    private const SOSTITUZIONI_OMOCODIA = 'LMNPQRSTUV';
    private const POSIZIONI_NUMERICHE = [6, 7, 9, 10, 12, 13, 14];
    private const VALORI_DISPARI = [
        '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9, '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
        'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9, 'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
        'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11, 'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
        'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
    ];

    public static function normalizza(string $codiceFiscale): string
    {
        return strtoupper(preg_replace('/\s+/', '', $codiceFiscale));
    }

    /**
     * Codice fiscale alfanumerico di persona fisica.
     */
    public static function isValidoPersonaFisica(string $codiceFiscale): bool
    {
        $codiceFiscale = self::normalizza($codiceFiscale);
        if (preg_match('/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/', $codiceFiscale) !== 1) {
            return false;
        }
        return self::calcolaCarattereControllo(substr($codiceFiscale, 0, 15)) === $codiceFiscale[15];
    }

    /**
     * Codice fiscale numerico (11 cifre) di società, enti e ditte.
     */
    public static function isValidoNumerico(string $codiceFiscale): bool
    {
        return PartitaIva::isValida(self::normalizza($codiceFiscale));
    }

    public static function calcola(string $cognome, string $nome, string $sesso, \DateTimeInterface $dataNascita, string $codiceCatastale): string
    {
        $codice = self::codificaCognome($cognome)
                . self::codificaNome($nome)
                . $dataNascita->format('y')
                . self::CODICI_MESE[(int) $dataNascita->format('n') - 1]
                . str_pad((string) ((int) $dataNascita->format('j') + ($sesso === 'F' ? 40 : 0)), 2, '0', STR_PAD_LEFT)
                . strtoupper($codiceCatastale);
        return $codice . self::calcolaCarattereControllo($codice);
    }

    public static function estraiSesso(string $codiceFiscale): string
    {
        return self::estraiGiorno($codiceFiscale) > 40 ? 'F' : 'M';
    }

    public static function estraiDataNascita(string $codiceFiscale, ?\DateTimeInterface $riferimento = null): \DateTimeImmutable
    {
        $codiceFiscale = self::decodificaOmocodia(self::normalizza($codiceFiscale));
        $riferimento ??= new \DateTimeImmutable();
        $anno = 2000 + (int) substr($codiceFiscale, 6, 2);
        if ($anno > (int) $riferimento->format('Y')) {
            $anno -= 100;
        }
        $mese = strpos(self::CODICI_MESE, $codiceFiscale[8]) + 1;
        $giorno = self::estraiGiorno($codiceFiscale) % 40;
        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $anno, $mese, $giorno));
    }

    public static function estraiCodiceCatastale(string $codiceFiscale): string
    {
        return substr(self::decodificaOmocodia(self::normalizza($codiceFiscale)), 11, 4);
    }

    private static function estraiGiorno(string $codiceFiscale): int
    {
        return (int) substr(self::decodificaOmocodia(self::normalizza($codiceFiscale)), 9, 2);
    }

    private static function decodificaOmocodia(string $codiceFiscale): string
    {
        foreach (self::POSIZIONI_NUMERICHE as $posizione) {
            $sostituzione = strpos(self::SOSTITUZIONI_OMOCODIA, $codiceFiscale[$posizione]);
            if ($sostituzione !== false) {
                $codiceFiscale[$posizione] = (string) $sostituzione;
            }
        }
        return $codiceFiscale;
    }

    private static function calcolaCarattereControllo(string $primiQuindiciCaratteri): string
    {
        $somma = 0;
        for ($i = 0; $i < 15; $i++) {
            $carattere = $primiQuindiciCaratteri[$i];
            if ($i % 2 === 0) {
                $somma += self::VALORI_DISPARI[$carattere];
            } else {
                $somma += ctype_digit($carattere) ? (int) $carattere : ord($carattere) - ord('A');
            }
        }
        return chr(ord('A') + ($somma % 26));
    }

    private static function codificaCognome(string $cognome): string
    {
        [$consonanti, $vocali] = self::separaLettere($cognome);
        return substr($consonanti . $vocali . 'XXX', 0, 3);
    }

    private static function codificaNome(string $nome): string
    {
        [$consonanti, $vocali] = self::separaLettere($nome);
        if (strlen($consonanti) >= 4) {
            return $consonanti[0] . $consonanti[2] . $consonanti[3];
        }
        return substr($consonanti . $vocali . 'XXX', 0, 3);
    }

    private static function separaLettere(string $testo): array
    {
        $traslitterato = iconv('UTF-8', 'ASCII//TRANSLIT', $testo);
        $lettere = preg_replace('/[^A-Z]/', '', strtoupper($traslitterato === false ? $testo : $traslitterato));
        return [
            preg_replace('/[AEIOU]/', '', $lettere),
            preg_replace('/[^AEIOU]/', '', $lettere),
        ];
    }
}
