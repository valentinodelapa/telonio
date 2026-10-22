<?php

namespace Deleghe\Application\Services\TabelleRiferimento;

/**
 * Lettura minimale di un foglio xls (Excel 97-2003, BIFF8 in un contenitore
 * OLE2) senza librerie esterne: valori delle celle per indice di colonna,
 * come LettoreXlsx::leggiRighe. Gestisce stringhe (anche condivise e
 * spezzate su record CONTINUE), numeri (NUMBER, RK, MULRK) e risultati delle
 * formule; ignora formattazione, date e celle vuote.
 */
class LettoreXls
{

    private const FIRMA_OLE = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
    private const FINE_CATENA = -2;
    private const BOF = 0x0809;
    private const EOF = 0x000A;
    private const BOUNDSHEET = 0x0085;
    private const SST = 0x00FC;
    private const CONTINUE = 0x003C;
    private const LABELSST = 0x00FD;
    private const LABEL = 0x0204;
    private const NUMBER = 0x0203;
    private const RK = 0x027E;
    private const MULRK = 0x00BD;
    private const FORMULA = 0x0006;
    private const STRINGA_FORMULA = 0x0207;

    private string $dati;
    /**
     * BIFF5 (Excel 95): stringhe a 8 bit senza byte di opzioni, nella
     * codifica del file (Windows-1252 per i file in uso).
     */
    private bool $biff5 = false;
    private int $dimensioneSettore;

    /**
     * @param string|null $nomeFoglio null = primo foglio
     * @return array<int, array<int, string>> righe per indice (0 = prima riga)
     */
    public function leggiRighe(string $percorso, ?string $nomeFoglio = null): array
    {
        $this->dati = file_get_contents($percorso) ?: throw new \RuntimeException('Impossibile aprire ' . $percorso);
        if (str_starts_with($this->dati, self::FIRMA_OLE) === false) {
            throw new \UnexpectedValueException(basename($percorso) . ' non è un file xls');
        }
        $libro = $this->leggiFlusso('Workbook') ?? $this->leggiFlusso('Book') ?? throw new \UnexpectedValueException('Flusso Workbook non trovato in ' . basename($percorso));
        $record = $this->leggiRecord($libro);
        $this->biff5 = ($record[0]['tipo'] === self::BOF) && (unpack('v', substr($record[0]['dati'], 0, 2))[1] === 0x0500);
        [$stringhe, $fogli] = $this->leggiGlobali($record);
        $posizione = $nomeFoglio === null ? reset($fogli) : ($fogli[$nomeFoglio] ?? false);
        if ($posizione === false) {
            throw new \UnexpectedValueException('Foglio "' . $nomeFoglio . '" non trovato in ' . basename($percorso));
        }
        return $this->leggiCelle($record, $posizione, $stringhe);
    }

    // ---------------------------------------------------------------
    // Contenitore OLE2 (Compound File Binary)
    // ---------------------------------------------------------------

    private function leggiFlusso(string $nome): ?string
    {
        $intestazione = unpack('vversione/vordine/vspostamento/vspostamentoMini', substr($this->dati, 0x1A, 8));
        $this->dimensioneSettore = 1 << $intestazione['spostamento'];
        $dimensioneMini = 1 << $intestazione['spostamentoMini'];
        $campi = unpack('VsettoriFat/VprimaDirectory/Vtransazione/VsogliaMini/VprimaMiniFat/VsettoriMiniFat/VprimoDifat/VsettoriDifat', substr($this->dati, 0x2C, 32));
        $fat = $this->leggiFat($campi);
        $directory = $this->leggiCatena($campi['primaDirectory'], $fat);
        $radice = null;
        for ($voce = 0; $voce * 128 < strlen($directory); $voce++) {
            $elemento = substr($directory, $voce * 128, 128);
            $lunghezzaNome = unpack('v', substr($elemento, 64, 2))[1];
            $tipo = ord($elemento[66]);
            $nomeVoce = $lunghezzaNome > 2 ? mb_convert_encoding(substr($elemento, 0, $lunghezzaNome - 2), 'UTF-8', 'UTF-16LE') : '';
            [$inizio, $dimensione] = array_values(unpack('Vinizio/Vdimensione', substr($elemento, 116, 8)));
            if ($tipo === 5) {
                $radice = ['inizio' => $inizio, 'dimensione' => $dimensione];
            } elseif (($tipo === 2) && ($nomeVoce === $nome)) {
                if ($dimensione >= $campi['sogliaMini']) {
                    return substr($this->leggiCatena($inizio, $fat), 0, $dimensione);
                }
                $miniFlusso = $this->leggiCatena($radice['inizio'], $fat);
                $miniFat = array_values(unpack('V*', $this->leggiCatena($campi['primaMiniFat'], $fat)));
                $contenuto = '';
                for ($settore = $inizio; $settore >= 0 && strlen($contenuto) < $dimensione; $settore = self::firmato($miniFat[$settore])) {
                    $contenuto .= substr($miniFlusso, $settore * $dimensioneMini, $dimensioneMini);
                }
                return substr($contenuto, 0, $dimensione);
            }
        }
        return null;
    }

    private function leggiFat(array $campi): array
    {
        $settoriFat = array_slice(array_values(unpack('V109', substr($this->dati, 0x4C, 436))), 0, $campi['settoriFat']);
        $settoreDifat = self::firmato($campi['primoDifat']);
        for ($numero = 0; $numero < $campi['settoriDifat'] && $settoreDifat >= 0; $numero++) {
            $voci = array_values(unpack('V*', $this->settore($settoreDifat)));
            $settoreDifat = self::firmato(array_pop($voci));
            array_push($settoriFat, ...$voci);
        }
        $fat = [];
        foreach (array_slice($settoriFat, 0, $campi['settoriFat']) as $settore) {
            array_push($fat, ...array_values(unpack('V*', $this->settore($settore))));
        }
        return $fat;
    }

    private function leggiCatena(int $inizio, array $fat): string
    {
        $contenuto = '';
        $visitati = 0;
        for ($settore = self::firmato($inizio); $settore >= 0; $settore = self::firmato($fat[$settore] ?? self::FINE_CATENA)) {
            if (++$visitati > count($fat)) {
                throw new \UnexpectedValueException('Catena di settori non valida');
            }
            $contenuto .= $this->settore($settore);
        }
        return $contenuto;
    }

    private function settore(int $numero): string
    {
        return substr($this->dati, ($numero + 1) * $this->dimensioneSettore, $this->dimensioneSettore);
    }

    private static function firmato(int $valore): int
    {
        return $valore >= 0x80000000 ? $valore - 0x100000000 : $valore;
    }

    // ---------------------------------------------------------------
    // Record BIFF8
    // ---------------------------------------------------------------

    /**
     * @return array<int, array{tipo: int, dati: string, posizione: int}>
     */
    private function leggiRecord(string $flusso): array
    {
        $record = [];
        for ($posizione = 0; $posizione + 4 <= strlen($flusso);) {
            [$tipo, $lunghezza] = array_values(unpack('vtipo/vlunghezza', substr($flusso, $posizione, 4)));
            $record[] = ['tipo' => $tipo, 'dati' => substr($flusso, $posizione + 4, $lunghezza), 'posizione' => $posizione];
            $posizione += 4 + $lunghezza;
        }
        return $record;
    }

    /**
     * Tabella delle stringhe condivise e posizione dei fogli (dal record
     * BOF del foglio), dalla parte globale del libro.
     */
    private function leggiGlobali(array $record): array
    {
        $stringhe = [];
        $fogli = [];
        foreach ($record as $indice => $voce) {
            if ($voce['tipo'] === self::BOUNDSHEET) {
                $posizione = unpack('V', substr($voce['dati'], 0, 4))[1];
                $fogli[$this->stringaBreve($voce['dati'], 6)] = $posizione;
            } elseif ($voce['tipo'] === self::SST) {
                $segmenti = [substr($voce['dati'], 8)];
                for ($successivo = $indice + 1; ($record[$successivo]['tipo'] ?? null) === self::CONTINUE; $successivo++) {
                    $segmenti[] = $record[$successivo]['dati'];
                }
                $stringhe = $this->leggiTabellaStringhe($segmenti, unpack('V', substr($voce['dati'], 4, 4))[1]);
            } elseif (($voce['tipo'] === self::EOF) && (count($fogli) > 0)) {
                break;
            }
        }
        return [$stringhe, $fogli];
    }

    /**
     * Le stringhe della SST possono essere spezzate tra il record SST e i
     * record CONTINUE: a ogni cambio di record dentro i caratteri segue un
     * nuovo byte di opzioni (compressione 8 o 16 bit).
     */
    private function leggiTabellaStringhe(array $segmenti, int $numero): array
    {
        $stringhe = [];
        $segmento = 0;
        $posizione = 0;
        $leggi = function (int $lunghezza) use (&$segmento, &$posizione, $segmenti): string {
            $risultato = '';
            while ($lunghezza > 0) {
                if ($posizione >= strlen($segmenti[$segmento])) {
                    $segmento++;
                    $posizione = 0;
                }
                $parte = substr($segmenti[$segmento], $posizione, $lunghezza);
                $posizione += strlen($parte);
                $lunghezza -= strlen($parte);
                $risultato .= $parte;
            }
            return $risultato;
        };
        for ($stringa = 0; $stringa < $numero; $stringa++) {
            $caratteri = unpack('v', $leggi(2))[1];
            $opzioni = ord($leggi(1));
            $sequenze = ($opzioni & 0x08) ? unpack('v', $leggi(2))[1] : 0;
            $estensione = ($opzioni & 0x04) ? unpack('V', $leggi(4))[1] : 0;
            $testo = '';
            $sedici = ($opzioni & 0x01) === 0x01;
            while ($caratteri > 0) {
                if ($posizione >= strlen($segmenti[$segmento])) {
                    $segmento++;
                    $posizione = 0;
                    $sedici = (ord($segmenti[$segmento][0]) & 0x01) === 0x01;
                    $posizione = 1;
                }
                $disponibili = intdiv(strlen($segmenti[$segmento]) - $posizione, $sedici ? 2 : 1);
                $quanti = min($caratteri, $disponibili);
                $testo .= self::decodifica(substr($segmenti[$segmento], $posizione, $quanti * ($sedici ? 2 : 1)), $sedici);
                $posizione += $quanti * ($sedici ? 2 : 1);
                $caratteri -= $quanti;
            }
            $leggi(4 * $sequenze + $estensione);
            $stringhe[] = $testo;
        }
        return $stringhe;
    }

    private function leggiCelle(array $record, int $posizioneFoglio, array $stringhe): array
    {
        $righe = [];
        $inizio = null;
        foreach ($record as $indice => $voce) {
            if ($inizio === null) {
                $inizio = ($voce['posizione'] === $posizioneFoglio) && ($voce['tipo'] === self::BOF) ? $indice : null;
                continue;
            }
            $dati = $voce['dati'];
            switch ($voce['tipo']) {
                case self::EOF:
                    break 2;
                case self::LABELSST:
                    [$riga, $colonna] = self::cella($dati);
                    $righe[$riga][$colonna] = $stringhe[unpack('V', substr($dati, 6, 4))[1]] ?? '';
                    break;
                case self::LABEL:
                    [$riga, $colonna] = self::cella($dati);
                    $righe[$riga][$colonna] = $this->stringa($dati, 6);
                    break;
                case self::NUMBER:
                    [$riga, $colonna] = self::cella($dati);
                    $righe[$riga][$colonna] = self::numero(unpack('e', substr($dati, 6, 8))[1]);
                    break;
                case self::RK:
                    [$riga, $colonna] = self::cella($dati);
                    $righe[$riga][$colonna] = self::numero(self::rk(unpack('V', substr($dati, 6, 4))[1]));
                    break;
                case self::MULRK:
                    [$riga, $colonna] = self::cella($dati);
                    for ($posizione = 4; $posizione + 6 <= strlen($dati) - 2; $posizione += 6, $colonna++) {
                        $righe[$riga][$colonna] = self::numero(self::rk(unpack('V', substr($dati, $posizione + 2, 4))[1]));
                    }
                    break;
                case self::FORMULA:
                    [$riga, $colonna] = self::cella($dati);
                    $risultato = substr($dati, 6, 8);
                    if (substr($risultato, 6, 2) !== "\xFF\xFF") {
                        $righe[$riga][$colonna] = self::numero(unpack('e', $risultato)[1]);
                    } elseif ((ord($risultato[0]) === 0) && (($record[$indice + 1]['tipo'] ?? null) === self::STRINGA_FORMULA)) {
                        $righe[$riga][$colonna] = $this->stringa($record[$indice + 1]['dati'], 0);
                    }
                    break;
            }
        }
        if (count($righe) === 0) {
            return [];
        }
        $risultato = [];
        for ($riga = 0; $riga <= max(array_keys($righe)); $riga++) {
            $celle = $righe[$riga] ?? [];
            ksort($celle);
            $risultato[] = $celle;
        }
        return $risultato;
    }

    private static function cella(string $dati): array
    {
        return array_values(unpack('vriga/vcolonna', substr($dati, 0, 4)));
    }

    private static function rk(int $valore): float
    {
        $numero = ($valore & 0x02)
                ? (float) (($valore >= 0x80000000 ? $valore - 0x100000000 : $valore) >> 2)
                : unpack('e', pack('V2', 0, $valore & 0xFFFFFFFC))[1];
        return ($valore & 0x01) ? $numero / 100 : $numero;
    }

    /**
     * I numeri interi (codici, anni) senza decimali; gli altri come stringa
     * con il punto decimale.
     */
    private static function numero(float $valore): string
    {
        return ((floor($valore) === $valore) && (abs($valore) < 1e15)) ? (string) (int) $valore : rtrim(rtrim(sprintf('%.10F', $valore), '0'), '.');
    }

    /**
     * Stringa con lunghezza a 16 bit (LABEL, STRING).
     */
    private function stringa(string $dati, int $posizione): string
    {
        $caratteri = unpack('v', substr($dati, $posizione, 2))[1];
        if ($this->biff5) {
            return mb_convert_encoding(substr($dati, $posizione + 2, $caratteri), 'UTF-8', 'Windows-1252');
        }
        $sedici = (ord($dati[$posizione + 2]) & 0x01) === 0x01;
        return self::decodifica(substr($dati, $posizione + 3, $caratteri * ($sedici ? 2 : 1)), $sedici);
    }

    private function stringaBreve(string $dati, int $posizione): string
    {
        $caratteri = ord($dati[$posizione]);
        if ($this->biff5) {
            return mb_convert_encoding(substr($dati, $posizione + 1, $caratteri), 'UTF-8', 'Windows-1252');
        }
        $sedici = (ord($dati[$posizione + 1]) & 0x01) === 0x01;
        return self::decodifica(substr($dati, $posizione + 2, $caratteri * ($sedici ? 2 : 1)), $sedici);
    }

    /**
     * Caratteri a 16 bit (UTF-16LE) o "compressi" (byte basso di UTF-16,
     * cioè ISO-8859-1).
     */
    private static function decodifica(string $byte, bool $sedici): string
    {
        return mb_convert_encoding($byte, 'UTF-8', $sedici ? 'UTF-16LE' : 'ISO-8859-1');
    }
}
