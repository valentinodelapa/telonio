<?php

namespace Dichiarativi\Application\Services\Stampa;

use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\FormatoCampo;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use setasign\Fpdi\Fpdi;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Dichiarazione stampata sul modello ufficiale: le pagine dei fascicoli in
 * PDF dell'Agenzia delle Entrate (Assets/modelli, scritti da
 * `sisma modelli-dichiarativi`) con i valori nelle caselle
 * (Assets/modelli/<modello>-<anno>.csv):
 *
 * - le pagine del frontespizio (posizioni con i codici "#", valori di
 *   DatiFrontespizioStampa), sempre;
 * - le pagine dei quadri con almeno un valore, una copia per modulo, con il
 *   codice fiscale e il numero del modulo in testa;
 * - in fondo l'elenco dei campi compilati senza una posizione nel modello,
 *   perché nessun dato resti fuori dalla stampa.
 *
 * Importi allineati a destra prima del ",00" stampato, caselle barrate con
 * una X, un carattere per cella nelle caselle divise in celle (codici
 * fiscali, partite IVA, date), codici e testi a sinistra; i caratteri
 * standard del PDF in codifica Windows-1252.
 */
class PdfDichiarazione
{

    private const CORPO = 8.0;
    private const CORPO_MINIMO = 5.0;

    public function __construct(private DataMapper $dataMapper = new DataMapper(), private string $cartellaModelli = ModelliUfficialiDichiarativi::CARTELLA_MODELLI)
    {

    }

    public static function getNomeFile(Dichiarazione $dichiarazione): string
    {
        return sprintf('%s_%s_%d.pdf', $dichiarazione->modello->value, preg_replace('/[^A-Z0-9]/', '', strtoupper($dichiarazione->soggetto->codiceFiscale)), $dichiarazione->anno);
    }

    public function isDisponibile(Dichiarazione $dichiarazione): bool
    {
        return is_file($this->getFilePosizioni($dichiarazione));
    }

    public function genera(Dichiarazione $dichiarazione, ?QuadriDichiarazione $quadri = null): string
    {
        $quadri ??= new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $posizioni = $this->leggiPosizioni($dichiarazione);
        $frontespizio = (new DatiFrontespizioStampa($this->dataMapper))->getValori($dichiarazione, $quadri);
        $codiceFiscale = strtoupper((string) $dichiarazione->soggetto->codiceFiscale);
        $pdf = new Fpdi('P', 'pt', [LettoreModelloPdf::LARGHEZZA, LettoreModelloPdf::ALTEZZA]);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetCreator('Telonio', true);
        $pdf->SetTitle(self::codifica($dichiarazione->modello->getLabel() . ' ' . $dichiarazione->getAnnoModello() . ' - ' . $dichiarazione->soggetto->nominativo), false);
        $pdf->SetTextColor(0, 0, 0);
        $collocati = [];
        foreach ($posizioni as $chiave => $campi) {
            [$fascicolo, $pagina] = explode('/', $chiave);
            $frontespizioPagina = count(array_filter($campi, fn(array $c) => str_starts_with($c['codice'], '#') && !in_array($c['codice'], ['#CF', '#MODULO'], true))) > 0;
            $moduli = $frontespizioPagina ? [1] : $quadri->getModuli();
            foreach ($moduli as $modulo) {
                $valori = [];
                foreach ($campi as $campo) {
                    $codice = $campo['codice'];
                    $valore = match (true) {
                        $codice === '#CF' => $codiceFiscale,
                        $codice === '#MODULO' => null,
                        str_starts_with($codice, '#') => $frontespizio[$codice] ?? null,
                        default => $this->mostra($quadri, $codice, $modulo),
                    };
                    if (($valore !== null) && ($valore !== '')) {
                        // importi e numeri allineati a destra anche dove il modello non stampa il ",00"
                        if ((str_starts_with($codice, '#') === false) && self::isNumerico($quadri->getCampo($codice)->formato)) {
                            $campo['destra'] = true;
                        }
                        $valori[] = [$campo, $valore];
                        if (str_starts_with($codice, '#') === false) {
                            $collocati[$modulo][$codice] = true;
                        }
                    }
                }
                $compilata = count(array_filter($valori, fn(array $v) => !str_starts_with($v[0]['codice'], '#'))) > 0;
                if (($frontespizioPagina === false) && ($compilata === false)) {
                    continue;
                }
                if (($frontespizioPagina === false) && (count($quadri->getModuli()) > 1)) {
                    foreach ($campi as $campo) {
                        if ($campo['codice'] === '#MODULO') {
                            $valori[] = [$campo, (string) $modulo];
                        }
                    }
                }
                $this->aggiungiPagina($pdf, $dichiarazione, (int) $fascicolo, (int) $pagina, $valori);
            }
        }
        $this->aggiungiElenco($pdf, $dichiarazione, $quadri, $collocati);
        return $pdf->Output('S');
    }

    /**
     * @return array<string, list<array>> posizioni per "<fascicolo>/<pagina>", nell'ordine del modello
     */
    private function leggiPosizioni(Dichiarazione $dichiarazione): array
    {
        $file = $this->getFilePosizioni($dichiarazione);
        $handle = @fopen($file, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Modello ufficiale non disponibile per la stampa: ' . basename($file) . ' (sisma modelli-dichiarativi)');
        }
        $colonne = fgetcsv($handle, null, ';', '"', '');
        $posizioni = [];
        while (($riga = fgetcsv($handle, null, ';', '"', '')) !== false) {
            if (count($riga) !== count($colonne)) {
                continue;
            }
            $campo = array_combine($colonne, $riga);
            $posizioni[$campo['fascicolo'] . '/' . $campo['pagina']][] = ['codice' => $campo['codice'], 'x' => (float) $campo['x'], 'y' => (float) $campo['y'],
                'larghezza' => (float) $campo['larghezza'], 'altezza' => (float) $campo['altezza'],
                'celle' => $campo['celle'] === '' ? [] : array_map('floatval', explode(' ', $campo['celle'])), 'destra' => $campo['destra'] === '1',
                'virgola' => (float) ($campo['virgola'] ?? 0)];
        }
        fclose($handle);
        uksort($posizioni, fn(string $a, string $b) => array_map('intval', explode('/', $a)) <=> array_map('intval', explode('/', $b)));
        return $posizioni;
    }

    private function getFilePosizioni(Dichiarazione $dichiarazione): string
    {
        return $this->cartellaModelli . $dichiarazione->modello->value . '-' . $dichiarazione->getAnnoModello() . '.csv';
    }

    private function mostra(QuadriDichiarazione $quadri, string $codice, int $modulo): ?string
    {
        $valore = $quadri->get($codice, $modulo);
        if ($valore === null) {
            return null;
        }
        $formato = $quadri->getCampo($codice)->formato;
        return $formato === null ? $valore : $formato->mostra($valore);
    }

    private function aggiungiPagina(Fpdi $pdf, Dichiarazione $dichiarazione, int $fascicolo, int $pagina, array $valori): void
    {
        $sfondo = $this->cartellaModelli . $dichiarazione->modello->value . '-' . $dichiarazione->getAnnoModello() . '-' . $fascicolo . '.pdf';
        if (is_file($sfondo) === false) {
            throw new \RuntimeException('Fascicolo del modello non trovato: ' . basename($sfondo) . ' (sisma modelli-dichiarativi)');
        }
        $pdf->setSourceFile($sfondo);
        $modello = $pdf->importPage($pagina);
        // pagina delle dimensioni del modello, senza ridimensionare: le posizioni sono nelle sue coordinate (le pagine del
        // modello IVA TR sono più larghe di un A4)
        $dimensioni = $pdf->getTemplateSize($modello);
        $pdf->AddPage($dimensioni['orientation'], [$dimensioni['width'], $dimensioni['height']]);
        $pdf->useTemplate($modello);
        foreach ($valori as [$campo, $valore]) {
            $this->scrivi($pdf, $campo, $valore);
        }
    }

    private function scrivi(Fpdi $pdf, array $campo, string $valore): void
    {
        $testo = self::codifica($valore);
        // caselle barrate (e scelte nei riquadri della scheda): X al centro
        $casella = $testo === 'X';
        if ($campo['virgola'] > 0) {
            $this->scriviCentesimi($pdf, $campo, $testo);
            return;
        }
        $celle = count($campo['celle']) - 1;
        $numerico = $campo['destra'] || ($celle > 0) || $casella;
        $corpo = min(self::CORPO, max(self::CORPO_MINIMO, $campo['altezza'] * 0.7));
        $pdf->SetFont($numerico ? 'Courier' : 'Helvetica', 'B', $corpo);
        if ($celle > 0) {
            // una cifra o lettera per cella (codici fiscali, partite IVA, codici, date): senza i separatori se non ci stanno,
            // gli importi senza i punti delle migliaia e allineati a destra
            $caratteri = $campo['destra'] ? str_replace('.', '', $testo) : $testo;
            if (strlen($caratteri) > $celle) {
                $caratteri = (string) preg_replace('~[^A-Za-z0-9-]~', '', $caratteri);
            }
            if (strlen($caratteri) <= $celle) {
                $base = $campo['y'] + ($campo['altezza'] / 2) + ($corpo * 0.35);
                $primaCella = $campo['destra'] ? $celle - strlen($caratteri) : 0;
                foreach (str_split($caratteri) as $indice => $carattere) {
                    [$sinistra, $destra] = [$campo['celle'][$primaCella + $indice], $campo['celle'][$primaCella + $indice + 1]];
                    $pdf->Text($sinistra + (($destra - $sinistra - $pdf->GetStringWidth($carattere)) / 2), $base, $carattere);
                }
                return;
            }
        }
        // linea di base nella parte bassa della casella, dove il modello stampa il ",00"
        $base = $campo['y'] + min($campo['altezza'] - 2.5, ($campo['altezza'] / 2) + ($corpo * 0.35) + 1.5);
        while (($pdf->GetStringWidth($testo) > $campo['larghezza'] - 3) && ($corpo > self::CORPO_MINIMO)) {
            $corpo -= 0.5;
            $pdf->SetFontSize($corpo);
        }
        if ($casella) {
            $pdf->Text($campo['x'] + (($campo['larghezza'] - $pdf->GetStringWidth($testo)) / 2), $campo['y'] + ($campo['altezza'] / 2) + ($corpo * 0.35), $testo);
        } elseif ($campo['destra']) {
            $pdf->Text($campo['x'] + $campo['larghezza'] - $pdf->GetStringWidth($testo) - 1.5, $base, $testo);
        } else {
            $pdf->Text($campo['x'] + 6, $base, self::accorcia($pdf, $testo, $campo['larghezza'] - 7));
        }
    }

    /**
     * Importi con i centesimi (modello IVA TR): la parte intera allineata
     * alla virgola stampata, i centesimi nelle due celle dopo.
     */
    private function scriviCentesimi(Fpdi $pdf, array $campo, string $testo): void
    {
        [$intero, $centesimi] = array_pad(explode(',', $testo, 2), 2, '');
        $corpo = min(self::CORPO, max(self::CORPO_MINIMO, $campo['altezza'] * 0.7));
        $pdf->SetFont('Courier', 'B', $corpo);
        $base = $campo['y'] + min($campo['altezza'] - 2.5, ($campo['altezza'] / 2) + ($corpo * 0.35) + 1.5);
        while (($pdf->GetStringWidth($intero) > $campo['virgola'] - $campo['x'] - 3) && ($corpo > self::CORPO_MINIMO)) {
            $corpo -= 0.5;
            $pdf->SetFontSize($corpo);
        }
        $pdf->Text($campo['virgola'] - 1 - $pdf->GetStringWidth($intero), $base, $intero);
        if (strlen($centesimi) === count($campo['celle']) - 1) {
            foreach (str_split($centesimi) as $indice => $cifra) {
                $pdf->Text($campo['celle'][$indice] + (($campo['celle'][$indice + 1] - $campo['celle'][$indice] - $pdf->GetStringWidth($cifra)) / 2), $base, $cifra);
            }
        } elseif ($centesimi !== '') {
            $pdf->Text($campo['virgola'] + 3, $base, $centesimi);
        }
    }

    /**
     * Pagina finale con i campi compilati che non hanno una posizione nel
     * modello (righi con strutture particolari): codice, descrizione e
     * valore, per modulo.
     */
    private function aggiungiElenco(Fpdi $pdf, Dichiarazione $dichiarazione, QuadriDichiarazione $quadri, array $collocati): void
    {
        $righe = [];
        foreach ($quadri->getValori() as $modulo => $campi) {
            foreach ($campi as $codice => $dati) {
                if (isset($collocati[$modulo][$codice]) || ($dati['valore'] === null) || ($dati['valore'] === '')) {
                    continue;
                }
                $campo = $quadri->getCampo($codice);
                $righe[] = [$modulo, $codice, $campo->descrizione, $campo->formato instanceof FormatoCampo ? $campo->formato->mostra((string) $dati['valore']) : (string) $dati['valore']];
            }
        }
        if (count($righe) === 0) {
            return;
        }
        $y = 0.0;
        foreach ($righe as $indice => [$modulo, $codice, $descrizione, $valore]) {
            if (($indice === 0) || ($y > LettoreModelloPdf::ALTEZZA - 50)) {
                $pdf->AddPage();
                $pdf->SetFont('Helvetica', 'B', 11);
                $pdf->Text(40, 50, self::codifica('Campi compilati senza una casella nel modello ufficiale'));
                $pdf->SetFont('Helvetica', '', 8);
                $pdf->Text(40, 64, self::codifica($dichiarazione->modello->getLabel() . ' ' . $dichiarazione->getAnnoModello() . ' - ' . $dichiarazione->soggetto->nominativo . ' - codice fiscale ' . $dichiarazione->soggetto->codiceFiscale));
                $y = 84.0;
            }
            $pdf->SetFont('Courier', 'B', 8);
            $pdf->Text(40, $y, $codice . ($modulo > 1 ? ' mod. ' . $modulo : ''));
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->Text(125, $y, self::accorcia($pdf, self::codifica($descrizione), 300));
            $pdf->SetFont('Courier', 'B', 8);
            $testo = self::codifica($valore);
            $pdf->Text(555 - $pdf->GetStringWidth($testo), $y, $testo);
            $y += 13;
        }
    }

    private static function isNumerico(?FormatoCampo $formato): bool
    {
        return in_array($formato, [FormatoCampo::positivo, FormatoCampo::numerico, FormatoCampo::centesimi, FormatoCampo::centesimiConSegno, FormatoCampo::percentuale, FormatoCampo::quantita], true);
    }

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
