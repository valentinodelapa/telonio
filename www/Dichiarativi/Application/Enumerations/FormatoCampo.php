<?php

namespace Dichiarativi\Application\Enumerations;

/**
 * Formati dei campi non posizionali delle forniture telematiche delle
 * dichiarazioni (prospetto delle configurazioni nel paragrafo "La struttura
 * dei dati" delle specifiche tecniche dell'Agenzia delle Entrate): ogni
 * campo-valore è lungo 16 caratteri.
 *
 * Nell'archivio i valori sono conservati in forma canonica: interi senza
 * separatori ("1234", "-1234"), decimali con il punto ("33.333"), date
 * "AAAA-MM-GG" (DT, DN), "AAAA" (DA), "MM-GG" (D4), "AAAA-MM" (D6),
 * caselle "1", testo così come inserito.
 */
enum FormatoCampo: string
{

    public const LUNGHEZZA = 16;

    case alfanumerico = 'AN';
    case casella = 'CB';
    case caselle = 'CB12';
    case codiceFiscale = 'CF';
    case codiceFiscaleNumerico = 'CN';
    case partitaIva = 'PI';
    case anno = 'DA';
    case data = 'DT';
    case dataRecente = 'DN';
    case giornoMese = 'D4';
    case meseAnno = 'D6';
    case positivo = 'NP';
    case centesimi = 'VP';
    case centesimiConSegno = 'VN';
    case numerico = 'NU';
    case percentuale = 'PC';
    case provinciaNascita = 'PN';
    case provincia = 'PR';
    case provinciaSede = 'PE';
    case quantita = 'QU';
    case cifre1 = 'N1';
    case cifre2 = 'N2';
    case cifre3 = 'N3';
    case cifre4 = 'N4';
    case cifre5 = 'N5';
    case cifre6 = 'N6';
    case cifre7 = 'N7';
    case cifre8 = 'N8';
    case cifre9 = 'N9';
    case cifre10 = 'N10';
    case cifre11 = 'N11';
    case cifre12 = 'N12';
    case cifre13 = 'N13';
    case cifre14 = 'N14';
    case cifre15 = 'N15';
    case cifre16 = 'N16';

    public function getLabel(): string
    {
        return match ($this) {
            self::alfanumerico => 'Testo',
            self::casella => 'Casella',
            self::caselle => 'Gruppo di 12 caselle (0/1)',
            self::codiceFiscale => 'Codice fiscale',
            self::codiceFiscaleNumerico, self::partitaIva => 'Codice fiscale numerico o partita IVA',
            self::anno => 'Anno',
            self::data, self::dataRecente => 'Data',
            self::giornoMese => 'Giorno e mese',
            self::meseAnno => 'Mese e anno',
            self::positivo => 'Importo positivo',
            self::centesimi => 'Importo positivo con i centesimi',
            self::centesimiConSegno => 'Importo con i centesimi',
            self::numerico => 'Importo',
            self::percentuale => 'Percentuale (fino a 3 decimali)',
            self::provinciaNascita, self::provincia, self::provinciaSede => 'Sigla della provincia',
            self::quantita => 'Numero (fino a 5 decimali)',
            default => 'Codice numerico di ' . $this->getCifre() . ($this->getCifre() === 1 ? ' cifra' : ' cifre'),
        };
    }

    /**
     * Numero di cifre dei formati Nx, null per gli altri.
     */
    public function getCifre(): ?int
    {
        return preg_match('/^N(\d+)$/', $this->value, $parti) === 1 ? (int) $parti[1] : null;
    }

    public function isImporto(): bool
    {
        return in_array($this, [self::positivo, self::numerico, self::centesimi, self::centesimiConSegno], true);
    }

    public function isCasella(): bool
    {
        return $this === self::casella;
    }

    /**
     * Forma canonica di un valore inserito dall'utente o calcolato; null se
     * il valore è vuoto (il campo non va trasmesso).
     *
     * @throws \InvalidArgumentException se il valore non rispetta il formato
     */
    public function normalizza(string|int|float|bool|null $valore): ?string
    {
        if (($valore === null) || ($valore === false) || (is_string($valore) && (trim($valore) === ''))) {
            return null;
        }
        $testo = is_string($valore) ? trim($valore) : (string) $valore;
        return match ($this) {
            self::alfanumerico => $testo,
            self::casella => in_array(mb_strtolower($testo), ['1', 'true', 'si', 'sì', 'x', 'on'], true) ? '1' : (in_array($testo, ['0'], true) ? null : throw self::errore($this, $testo)),
            self::caselle => preg_match('/^[01]{12}$/', $testo) === 1 ? (str_contains($testo, '1') ? $testo : null) : throw self::errore($this, $testo),
            self::codiceFiscale => preg_match('/^([A-Z0-9]{16}|\d{11})$/', mb_strtoupper($testo)) === 1 ? mb_strtoupper($testo) : throw self::errore($this, $testo),
            self::codiceFiscaleNumerico, self::partitaIva => preg_match('/^\d{11}$/', $testo) === 1 ? $testo : throw self::errore($this, $testo),
            self::anno => preg_match('/^\d{4}$/', $testo) === 1 ? $testo : throw self::errore($this, $testo),
            self::data, self::dataRecente => self::normalizzaData($this, $testo),
            self::giornoMese => preg_match('/^(\d{2})-?(\d{2})$/', $testo, $parti) === 1 ? $parti[1] . '-' . $parti[2] : throw self::errore($this, $testo),
            self::meseAnno => preg_match('/^(\d{4})-(\d{2})$/', $testo) === 1 ? $testo : throw self::errore($this, $testo),
            self::positivo, self::numerico => self::normalizzaImporto($this, $valore),
            self::centesimi, self::centesimiConSegno => self::normalizzaCentesimi($this, $valore),
            self::percentuale => self::normalizzaDecimale($this, $testo, 3),
            self::quantita => self::normalizzaDecimale($this, $testo, 5),
            self::provinciaNascita, self::provincia, self::provinciaSede => preg_match('/^[A-Z]{2}$/', mb_strtoupper($testo)) === 1 ? mb_strtoupper($testo) : throw self::errore($this, $testo),
            default => self::normalizzaCifre($this, $testo),
        };
    }

    /**
     * Campo-valore di 16 caratteri del record a struttura variabile: uno
     * per elemento; i testi più lunghi proseguono in elementi successivi
     * con lo stesso campo-codice e il primo carattere "+".
     *
     * @param string $valore valore in forma canonica
     * @return string[]
     */
    public function formatta(string $valore): array
    {
        $destra = fn(string $testo) => str_pad($testo, self::LUNGHEZZA, ' ', STR_PAD_LEFT);
        $sinistra = fn(string $testo) => str_pad($testo, self::LUNGHEZZA, ' ');
        return match ($this) {
            self::alfanumerico => self::formattaTesto($valore),
            self::casella => [$destra('1')],
            self::caselle => [$destra($valore)],
            self::codiceFiscale, self::codiceFiscaleNumerico, self::partitaIva, self::provinciaNascita, self::provincia, self::provinciaSede => [$sinistra($valore)],
            self::anno => [$destra($valore)],
            self::data, self::dataRecente => [$destra(substr($valore, 8, 2) . substr($valore, 5, 2) . substr($valore, 0, 4))],
            self::giornoMese => [$destra(str_replace('-', '', $valore))],
            self::meseAnno => [$destra(substr($valore, 5, 2) . substr($valore, 0, 4))],
            self::percentuale, self::quantita => [$destra(str_replace('.', ',', $valore))],
            self::positivo, self::numerico => [$destra($valore)],
            self::centesimi, self::centesimiConSegno => [$destra(number_format((float) $valore, 2, ',', ''))],
            default => [$destra(str_pad($valore, $this->getCifre(), '0', STR_PAD_LEFT))],
        };
    }

    /**
     * Valore canonico in forma leggibile (interfaccia e stampe).
     */
    public function mostra(?string $valore): string
    {
        if ($valore === null) {
            return '';
        }
        return match ($this) {
            self::positivo, self::numerico => number_format((int) $valore, 0, ',', '.'),
            self::centesimi, self::centesimiConSegno => number_format((float) $valore, 2, ',', '.'),
            self::casella => 'X',
            self::data, self::dataRecente => substr($valore, 8, 2) . '/' . substr($valore, 5, 2) . '/' . substr($valore, 0, 4),
            self::meseAnno => substr($valore, 5, 2) . '/' . substr($valore, 0, 4),
            self::giornoMese => str_replace('-', '/', $valore),
            self::percentuale, self::quantita => str_replace('.', ',', $valore),
            default => $valore,
        };
    }

    private static function normalizzaImporto(self $formato, string|int|float|bool $valore): ?string
    {
        if (is_string($valore)) {
            $testo = str_replace([' ', '€'], '', $valore);
            // formato italiano: punto per le migliaia, virgola per i decimali
            if (str_contains($testo, ',')) {
                $testo = str_replace(['.', ','], ['', '.'], $testo);
            } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $testo) === 1) {
                $testo = str_replace('.', '', $testo);
            }
            if (is_numeric($testo) === false) {
                throw self::errore($formato, $valore);
            }
            $valore = (float) $testo;
        }
        // importi in unità di euro, arrotondati per eccesso da 50 centesimi
        $intero = (int) round((float) $valore, 0, PHP_ROUND_HALF_UP);
        if (($formato === self::positivo) && ($intero < 0)) {
            throw new \InvalidArgumentException('Il campo ammette solo importi positivi.');
        }
        if (strlen((string) $intero) > self::LUNGHEZZA) {
            throw self::errore($formato, (string) $valore);
        }
        return $intero === 0 ? null : (string) $intero;
    }

    /**
     * Importi in centesimi di euro (modello IVA TR), arrotondati alla seconda
     * cifra decimale.
     */
    private static function normalizzaCentesimi(self $formato, string|int|float|bool $valore): ?string
    {
        if (is_string($valore)) {
            $testo = str_replace([' ', '€'], '', $valore);
            if (str_contains($testo, ',')) {
                $testo = str_replace(['.', ','], ['', '.'], $testo);
            } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $testo) === 1) {
                $testo = str_replace('.', '', $testo);
            }
            if (is_numeric($testo) === false) {
                throw self::errore($formato, $valore);
            }
            $valore = (float) $testo;
        }
        $importo = round((float) $valore, 2, PHP_ROUND_HALF_UP);
        if (($formato === self::centesimi) && ($importo < 0)) {
            throw new \InvalidArgumentException('Il campo ammette solo importi positivi.');
        }
        if (strlen(number_format(abs($importo), 2, ',', '')) > self::LUNGHEZZA - 1) {
            throw self::errore($formato, (string) $valore);
        }
        return $importo == 0 ? null : number_format($importo, 2, '.', '');
    }

    private static function normalizzaDecimale(self $formato, string $testo, int $decimali): ?string
    {
        $testo = str_replace(',', '.', str_replace(' ', '', $testo));
        if (preg_match('/^\d+(\.\d{1,' . $decimali . '})?$/', $testo) !== 1) {
            throw self::errore($formato, $testo);
        }
        $numero = rtrim(rtrim(number_format((float) $testo, $decimali, '.', ''), '0'), '.');
        return (float) $numero == 0.0 ? null : $numero;
    }

    private static function normalizzaCifre(self $formato, string $testo): ?string
    {
        if ((ctype_digit($testo) === false) || (strlen(ltrim($testo, '0')) > $formato->getCifre())) {
            throw self::errore($formato, $testo);
        }
        $numero = ltrim($testo, '0');
        return $numero === '' ? null : $numero;
    }

    private static function normalizzaData(self $formato, string $testo): string
    {
        $data = \DateTimeImmutable::createFromFormat('!Y-m-d', $testo) ?: \DateTimeImmutable::createFromFormat('!d/m/Y', $testo);
        if (($data === false) || (\DateTimeImmutable::getLastErrors() !== false)) {
            throw self::errore($formato, $testo);
        }
        return $data->format('Y-m-d');
    }

    /**
     * @return string[]
     */
    private static function formattaTesto(string $valore): array
    {
        // lettere accentate senza accento prima della traslitterazione, che dipende dalla piattaforma
        $testo = strtr(mb_strtoupper(trim($valore)), ['À' => 'A', 'Á' => 'A', 'È' => 'E', 'É' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Ò' => 'O', 'Ó' => 'O', 'Ù' => 'U', 'Ú' => 'U']);
        $traslitterato = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $testo);
        $testo = preg_replace('/[^\x20-\x7E]/', '', $traslitterato === false ? $testo : $traslitterato);
        $elementi = [str_pad(substr($testo, 0, self::LUNGHEZZA), self::LUNGHEZZA)];
        for ($inizio = self::LUNGHEZZA; $inizio < strlen($testo); $inizio += self::LUNGHEZZA - 1) {
            $elementi[] = str_pad('+' . substr($testo, $inizio, self::LUNGHEZZA - 1), self::LUNGHEZZA);
        }
        return $elementi;
    }

    private static function errore(self $formato, string $valore): \InvalidArgumentException
    {
        return new \InvalidArgumentException(sprintf('Valore "%s" non valido per il formato %s (%s).', $valore, $formato->value, $formato->getLabel()));
    }
}
