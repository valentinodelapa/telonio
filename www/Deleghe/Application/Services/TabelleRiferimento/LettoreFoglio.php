<?php

namespace Deleghe\Application\Services\TabelleRiferimento;

use Anagrafica\Application\Services\TabelleRiferimento\LettoreXlsx;

/**
 * Righe del primo foglio di un file xls o xlsx, con i valori normalizzati
 * (spazi, caratteri non validi delle tabelle dell'Agenzia).
 */
class LettoreFoglio
{

    public function __construct(
            private LettoreXlsx $lettoreXlsx = new LettoreXlsx(),
            private LettoreXls $lettoreXls = new LettoreXls(),
    ) {

    }

    /**
     * @return array<int, array<int, string>>
     */
    public function leggi(string $percorso): array
    {
        $righe = str_ends_with(strtolower($percorso), '.xlsx') ? $this->lettoreXlsx->leggiRighe($percorso) : $this->lettoreXls->leggiRighe($percorso);
        return array_map(fn(array $riga) => array_map([self::class, 'normalizza'], $riga), $righe);
    }

    public static function normalizza(?string $valore): string
    {
        $valore = self::ripara((string) $valore);
        return trim(preg_replace('/\s+/u', ' ', str_replace("\u{A0}", ' ', $valore)));
    }

    /**
     * Alcune descrizioni delle tabelle contengono testo corrotto da
     * conversioni successive (es. "dï¿‚ï¾’imposta" per "d’imposta"): ogni
     * byte non ASCII dell'originale è diventato un carattere a mezza
     * larghezza U+FF80-U+FFFF, a sua volta scritto in UTF-8 e riletto come
     * Windows-1252 ("ï¿‚" = EF BF 82 = U+FFC2 = byte C2). Si ricostruiscono
     * i byte originali e si decodificano (UTF-8 se valido, altrimenti
     * Windows-1252); i caratteri di controllo C1 rimasti si convertono
     * secondo Windows-1252 (U+0092 = ’).
     */
    private static function ripara(string $valore): string
    {
        if (preg_match('/ï[¾¿]|[\x{FF80}-\x{FFFF}]/u', $valore) !== 1) {
            return $valore;
        }
        $valore = preg_replace_callback('/(?:ï[¾¿].|[\x{FF80}-\x{FFFF}])+/u', function (array $sequenza): string {
            preg_match_all('/ï[¾¿].|[\x{FF80}-\x{FFFF}]/u', $sequenza[0], $caratteri);
            $byte = '';
            foreach ($caratteri[0] as $carattere) {
                $mezzaLarghezza = mb_strlen($carattere) === 3 ? mb_convert_encoding(mb_convert_encoding($carattere, 'Windows-1252', 'UTF-8'), 'UTF-8', 'UTF-8') : $carattere;
                $byte .= chr((mb_ord($mezzaLarghezza) ?: 0xFF3F) - 0xFF00);
            }
            return mb_check_encoding($byte, 'UTF-8') ? $byte : mb_convert_encoding($byte, 'UTF-8', 'Windows-1252');
        }, $valore);
        return preg_replace_callback('/[\x{0080}-\x{009F}]/u', fn(array $controllo) => mb_convert_encoding(chr(mb_ord($controllo[0])), 'UTF-8', 'Windows-1252'), $valore);
    }
}
