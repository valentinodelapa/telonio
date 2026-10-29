<?php

namespace Successioni\Application\Services;

use Successioni\Application\Entities\AllegatoSuccessione;

/**
 * Controllo del formato degli allegati (specifiche SUC13, paragrafo 2.3):
 * - PDF/A-1a o PDF/A-1b: si verifica la dichiarazione di conformità nei
 *   metadati XMP (pdfaid:part = 1, pdfaid:conformance = A o B);
 * - TIFF: bianco e nero (1 bit per campione), compressione CCITT Group IV
 *   (codice 4), risoluzione non superiore a 300 DPI, su tutte le pagine;
 * - dimensione massima 5 MB per file.
 *
 * È un controllo preliminare: la conformità piena al PDF/A si verifica con
 * il servizio di validazione dell'Agenzia (area riservata, servizio CIVIS -
 * validazione e conversione file), che può anche convertire i documenti.
 */
class VerificaAllegato
{

    public const MASSIMO_PAGINE_TIFF = 1000;

    /**
     * @return array{tipoMime: ?string, errore: ?string}
     */
    public function verifica(string $contenuto, string $nomeFile): array
    {
        if (strlen($contenuto) > AllegatoSuccessione::DIMENSIONE_MASSIMA) {
            return ['tipoMime' => null, 'errore' => 'Il file supera 5 MB: va suddiviso in più file.'];
        }
        if (str_starts_with($contenuto, '%PDF-')) {
            return ['tipoMime' => 'application/pdf', 'errore' => $this->verificaPdfA($contenuto)];
        }
        if (str_starts_with($contenuto, "II*\0") || str_starts_with($contenuto, "MM\0*")) {
            return ['tipoMime' => str_ends_with(strtolower($nomeFile), '.tif') ? 'image/tif' : 'image/tiff', 'errore' => $this->verificaTiff($contenuto)];
        }
        return ['tipoMime' => null, 'errore' => 'Formato non ammesso: sono accettati solo PDF/A e TIFF.'];
    }

    private function verificaPdfA(string $contenuto): ?string
    {
        if (preg_match('/pdfaid:part\s*(?:=\s*["\']|>)\s*(\d)/', $contenuto, $parte) !== 1) {
            return 'Il PDF non dichiara la conformità PDF/A: salvarlo come PDF/A-1a o PDF/A-1b (o convertirlo con il servizio dell\'Agenzia).';
        }
        preg_match('/pdfaid:conformance\s*(?:=\s*["\']|>)\s*([A-Za-z])/', $contenuto, $conformita);
        if (($parte[1] !== '1') || (in_array(strtoupper($conformita[1] ?? ''), ['A', 'B'], true) === false)) {
            return 'Il PDF è PDF/A-' . $parte[1] . strtolower($conformita[1] ?? '') . ': sono ammessi solo PDF/A-1a e PDF/A-1b.';
        }
        return null;
    }

    /**
     * Controlla tutte le pagine (directory IFD) del TIFF; un file troncato o
     * con riferimenti fuori dal file è rifiutato come non leggibile.
     */
    private function verificaTiff(string $contenuto): ?string
    {
        $littleEndian = $contenuto[0] === 'I';
        $lunghezza = strlen($contenuto);
        $leggi = function (int $posizione, int $byte) use ($contenuto, $lunghezza, $littleEndian): int {
            if (($posizione < 0) || ($posizione + $byte > $lunghezza)) {
                throw new \OutOfBoundsException();
            }
            $formato = $byte === 2 ? ($littleEndian ? 'v' : 'n') : ($littleEndian ? 'V' : 'N');
            return unpack($formato, substr($contenuto, $posizione, $byte))[1];
        };
        try {
            $directory = $leggi(4, 4);
            $visitate = [];
            $pagina = 0;
            while ($directory !== 0) {
                if (isset($visitate[$directory]) || (count($visitate) >= self::MASSIMO_PAGINE_TIFF)) {
                    return 'Il TIFF non è leggibile (struttura delle pagine non valida).';
                }
                $visitate[$directory] = true;
                $pagina++;
                $tag = [];
                $voci = $leggi($directory, 2);
                for ($indice = 0; $indice < $voci; $indice++) {
                    $posizione = $directory + 2 + $indice * 12;
                    $codice = $leggi($posizione, 2);
                    $tipo = $leggi($posizione + 2, 2);
                    $valore = $tipo === 3 ? $leggi($posizione + 8, 2) : $leggi($posizione + 8, 4);
                    if (in_array($codice, [282, 283], true)) {
                        $offset = $leggi($posizione + 8, 4);
                        $valore = $leggi($offset, 4) / max(1, $leggi($offset + 4, 4));
                    }
                    $tag[$codice] = $valore;
                }
                $errore = $this->verificaPaginaTiff($tag);
                if ($errore !== null) {
                    return $pagina > 1 ? rtrim($errore, '.') . ' (pagina ' . $pagina . ').' : $errore;
                }
                $directory = $leggi($directory + 2 + $voci * 12, 4);
            }
        } catch (\OutOfBoundsException) {
            return 'Il TIFF non è leggibile (file troncato o danneggiato).';
        }
        return null;
    }

    /**
     * @param array<int, int|float> $tag
     */
    private function verificaPaginaTiff(array $tag): ?string
    {
        if (($tag[259] ?? null) !== 4) {
            return 'Il TIFF deve avere compressione CCITT Group IV (modalità fax).';
        }
        if (($tag[258] ?? 1) !== 1) {
            return 'Il TIFF deve essere in bianco e nero (1 bit).';
        }
        $unita = $tag[296] ?? 2;
        foreach ([282, 283] as $risoluzione) {
            $dpi = isset($tag[$risoluzione]) ? ($unita === 3 ? $tag[$risoluzione] * 2.54 : $tag[$risoluzione]) : 0;
            if ($dpi > 300) {
                return 'Il TIFF supera la risoluzione massima di 300 DPI.';
            }
        }
        return null;
    }
}
