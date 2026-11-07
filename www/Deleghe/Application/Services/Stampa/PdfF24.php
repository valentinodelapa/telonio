<?php

namespace Deleghe\Application\Services\Stampa;

use Anagrafica\Application\Entities\Soggetto;
use Deleghe\Application\Entities\Delega;
use setasign\Fpdi\Fpdi;

/**
 * PDF della delega da scaricare o allegare alla mail: le pagine della stampa
 * (ModuloUfficialeF24::getPagine) con lo sfondo vettoriale del PDF ufficiale
 * dell'Agenzia (Assets/pdf, scritto da `sisma modelli-f24`) e i campi negli
 * stessi punti della stampa.
 *
 * Il testo usa i caratteri standard del PDF in grassetto (Courier per caselle
 * e importi, Helvetica per i testi), in codifica Windows-1252. La linea di
 * base riproduce quella della stampa HTML: testo centrato in verticale
 * nell'altezza del campo con le metriche di Courier New e Arial.
 */
class PdfF24
{

    /** Distanza della linea di base dal centro del campo, in em. */
    private const BASE_MONOSPAZIO = 0.266;
    private const BASE_TESTO = 0.347;

    public function __construct(private string $cartellaSfondi = ModuloUfficialeF24::CARTELLA_PDF, private bool $compressione = true)
    {

    }

    /**
     * Nome del file: F24_<data>_<codice fiscale>.pdf.
     */
    public static function getNomeFile(Delega $delega, Soggetto $soggetto): string
    {
        return sprintf('F24_%s_%s.pdf', $delega->scadenza->format('Y-m-d'), preg_replace('/[^A-Z0-9]/', '', strtoupper($soggetto->codiceFiscale)));
    }

    /**
     * @param array<int, array{modello: \Deleghe\Application\Enumerations\ModelloF24, pagina: int, campi: array}> $pagine
     */
    public function genera(array $pagine, string $titolo = ''): string
    {
        $pdf = new Fpdi('P', 'pt', [ModuloUfficialeF24::LARGHEZZA, ModuloUfficialeF24::ALTEZZA]);
        $pdf->SetCompression($this->compressione);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetCreator('Telonio', true);
        $pdf->SetTitle($titolo, true);
        $pdf->SetTextColor(0, 0, 0);
        foreach ($pagine as $pagina) {
            $sfondo = ModuloUfficialeF24::getSfondoPdf($pagina['modello'], $this->cartellaSfondi);
            if (is_file($sfondo) === false) {
                throw new \RuntimeException('Sfondo PDF del modello non trovato: ' . basename($sfondo) . ' (sisma modelli-f24)');
            }
            $pdf->setSourceFile($sfondo);
            $modello = $pdf->importPage($pagina['pagina']);
            $pdf->AddPage();
            $pdf->useTemplate($modello, 0, 0, ModuloUfficialeF24::LARGHEZZA, ModuloUfficialeF24::ALTEZZA);
            foreach ($pagina['campi'] as $campo) {
                $this->scrivi($pdf, $campo);
            }
        }
        return $pdf->Output('S');
    }

    /**
     * @param array{x: float, y: float, larghezza: float, altezza: float, testo: string, cella: float|null, dimensione: float, destra?: bool} $campo
     */
    private function scrivi(Fpdi $pdf, array $campo): void
    {
        $monospazio = ($campo['cella'] !== null) || ($campo['destra'] ?? false);
        $pdf->SetFont($monospazio ? 'Courier' : 'Helvetica', 'B', $campo['dimensione']);
        $base = $campo['y'] + ($campo['altezza'] / 2) + (($monospazio ? self::BASE_MONOSPAZIO : self::BASE_TESTO) * $campo['dimensione']);
        $testo = self::codifica($campo['testo']);
        if ($campo['cella'] !== null) {
            foreach (str_split($testo) as $indice => $carattere) {
                $pdf->Text($campo['x'] + ($indice * $campo['cella']) + (($campo['cella'] - $pdf->GetStringWidth($carattere)) / 2), $base, $carattere);
            }
        } elseif ($campo['destra'] ?? false) {
            $pdf->Text($campo['x'] + $campo['larghezza'] - $pdf->GetStringWidth($testo), $base, $testo);
        } else {
            $pdf->Text($campo['x'], $base, self::accorcia($pdf, $testo, $campo['larghezza']));
        }
    }

    /**
     * Testo oltre la larghezza del campo troncato con i puntini (come
     * text-overflow: ellipsis nella stampa).
     */
    private static function accorcia(Fpdi $pdf, string $testo, float $larghezza): string
    {
        if ($pdf->GetStringWidth($testo) <= $larghezza) {
            return $testo;
        }
        $puntini = "\x85";
        while (($testo !== '') && ($pdf->GetStringWidth($testo . $puntini) > $larghezza)) {
            $testo = substr($testo, 0, -1);
        }
        return rtrim($testo) . $puntini;
    }

    private static function codifica(string $testo): string
    {
        return mb_convert_encoding($testo, 'Windows-1252', 'UTF-8');
    }
}
