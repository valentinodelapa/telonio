<?php

namespace Successioni\Application\Services;

use Successioni\Application\Entities\AllegatoSuccessione;

/**
 * Controllo del formato degli allegati (specifiche SUC13, paragrafo 2.3):
 * - PDF/A-1a o PDF/A-1b: si verifica la dichiarazione di conformità nei
 *   metadati XMP (pdfaid:part = 1, pdfaid:conformance = A o B);
 * - TIFF: bianco e nero (1 bit per campione), compressione CCITT Group IV
 *   (codice 4), risoluzione non superiore a 300 DPI;
 * - dimensione massima 5 MB per file.
 *
 * È un controllo preliminare: la conformità piena al PDF/A si verifica con
 * il servizio di validazione dell'Agenzia (area riservata, servizio CIVIS -
 * validazione e conversione file), che può anche convertire i documenti.
 */
class VerificaAllegato
{

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

    private function verificaTiff(string $contenuto): ?string
    {
        $littleEndian = $contenuto[0] === 'I';
        $leggi16 = fn(int $posizione) => unpack($littleEndian ? 'v' : 'n', substr($contenuto, $posizione, 2))[1];
        $leggi32 = fn(int $posizione) => unpack($littleEndian ? 'V' : 'N', substr($contenuto, $posizione, 4))[1];
        $directory = $leggi32(4);
        $voci = $leggi16($directory);
        $tag = [];
        for ($indice = 0; $indice < $voci; $indice++) {
            $posizione = $directory + 2 + $indice * 12;
            $codice = $leggi16($posizione);
            $tipo = $leggi16($posizione + 2);
            $valore = $tipo === 3 ? $leggi16($posizione + 8) : $leggi32($posizione + 8);
            if (in_array($codice, [282, 283], true)) {
                $offset = $leggi32($posizione + 8);
                $valore = $leggi32($offset) / max(1, $leggi32($offset + 4));
            }
            $tag[$codice] = $valore;
        }
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
