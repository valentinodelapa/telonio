<?php

namespace Dichiarativi\Application\Services\Irap;

/**
 * Aliquote IRAP delle regioni e province autonome per il modello IRAP
 * dell'anno (quadro IR, colonne 6 e 7): tabella del Dipartimento delle
 * Finanze ("Fiscalità regionale e locale - IRAP", www.finanze.gov.it) con
 * cui la procedura di controllo dell'Agenzia delle Entrate verifica codice
 * e aliquota, in Database/sorgenti/aliquote-IRA-<anno modello>.csv
 * (regione con il codice del modello, da 01 a 21; codice dell'aliquota;
 * aliquota; descrizione).
 *
 * Il codice 1 è in ogni regione l'aliquota ordinaria o generalizzata
 * (art. 16, comma 1, D.Lgs. 446/1997 con le variazioni regionali); le
 * altre sono aliquote agevolate o maggiorate per settore o categoria.
 */
class AliquoteIrap
{

    public const CARTELLA = __DIR__ . '/../../../Database/sorgenti/';
    public const CODICE_ORDINARIA = '1';

    /** @var array<int, array<string, array<string, array{aliquota: float, descrizione: string}>>> per anno del modello, regione e codice */
    private static array $tabelle = [];

    public function __construct(private string $cartella = self::CARTELLA)
    {

    }

    /**
     * Aliquota per regione e codice; null se il codice non è nella tabella
     * della regione o la tabella dell'anno manca.
     */
    public function getAliquota(int $annoModello, string $regione, string $codice): ?float
    {
        return $this->getTabella($annoModello)[self::normalizzaRegione($regione)][self::normalizzaCodice($codice)]['aliquota'] ?? null;
    }

    public function getDescrizione(int $annoModello, string $regione, string $codice): ?string
    {
        return $this->getTabella($annoModello)[self::normalizzaRegione($regione)][self::normalizzaCodice($codice)]['descrizione'] ?? null;
    }

    public function isDisponibile(int $annoModello): bool
    {
        return count($this->getTabella($annoModello)) > 0;
    }

    /**
     * @return array<string, array<string, array{aliquota: float, descrizione: string}>>
     */
    private function getTabella(int $annoModello): array
    {
        $chiave = $this->cartella . $annoModello;
        if (isset(self::$tabelle[$chiave])) {
            return self::$tabelle[$chiave];
        }
        $tabella = [];
        $file = $this->cartella . 'aliquote-IRA-' . $annoModello . '.csv';
        if (is_file($file) && (($gestore = fopen($file, 'r')) !== false)) {
            $intestazione = fgetcsv($gestore, separator: ';', escape: '');
            if ($intestazione !== ['regione', 'codice', 'aliquota', 'descrizione']) {
                fclose($gestore);
                throw new \UnexpectedValueException(basename($file) . ': intestazione non valida, attesa regione;codice;aliquota;descrizione');
            }
            while (($riga = fgetcsv($gestore, separator: ';', escape: '')) !== false) {
                if ((count($riga) < 3) || (is_numeric($riga[2]) === false)) {
                    continue;
                }
                $tabella[self::normalizzaRegione($riga[0])][self::normalizzaCodice($riga[1])] = ['aliquota' => (float) $riga[2], 'descrizione' => (string) ($riga[3] ?? '')];
            }
            fclose($gestore);
        }
        return self::$tabelle[$chiave] = $tabella;
    }

    private static function normalizzaRegione(string $regione): string
    {
        return str_pad(ltrim(trim($regione), '0'), 2, '0', STR_PAD_LEFT);
    }

    private static function normalizzaCodice(string $codice): string
    {
        $codice = strtoupper(trim($codice));
        return ctype_digit($codice) ? (ltrim($codice, '0') ?: '0') : $codice;
    }
}
