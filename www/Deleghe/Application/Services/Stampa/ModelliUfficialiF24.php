<?php

namespace Deleghe\Application\Services\Stampa;

use Deleghe\Application\Enumerations\ModelloF24;

/**
 * Sfondi della stampa: i modelli F24 in PDF pubblicati dall'Agenzia delle
 * Entrate, convertiti pagina per pagina in SVG (pdftocairo, pacchetto
 * poppler-utils) nella cartella Assets/svg del modulo.
 *
 * Il link al PDF è cercato nella scheda "Modello e istruzioni" di ogni
 * modello. Prima della conversione si verifica che alcune parole del
 * modello siano nella posizione attesa: le coordinate dei campi di
 * ModuloUfficialeF24 valgono per quella impaginazione e un nuovo modello
 * richiede di rilevarle di nuovo.
 */
class ModelliUfficialiF24
{

    private const SCHEDE = [
        ModelloF24::ordinario->value => ['https://www.agenziaentrate.gov.it/portale/schede/pagamenti/f24/modello-e-istruzioni-f24', 'Modello\+di\+versamento\+unificato'],
        ModelloF24::semplificato->value => ['https://www.agenziaentrate.gov.it/portale/schede/pagamenti/f24-semplificato/modello-e-istruzioni-f24-semplificato', 'Modello\+F24\+Semplificato'],
        ModelloF24::elide->value => ['https://www.agenziaentrate.gov.it/portale/schede/pagamenti/f24-elementi-identificativi-f24elide/modello-e-istruzioni-f24elide', 'Modello\+F24\+identificativi'],
        ModelloF24::accise->value => ['https://www.agenziaentrate.gov.it/portale/schede/pagamenti/f24-accise/modello-f24-accise-2012', 'Modello\+F24\+Accise'],
    ];

    /**
     * Parole di riferimento con la posizione attesa in punti, uguale in
     * tutte le pagine del modello (in alto, nella sezione IMU e accanto al
     * saldo finale).
     */
    private const RIFERIMENTI = [
        ModelloF24::ordinario->value => [['CONTRIBUENTE', 24.4, 95.1], ['IDENTIFICATIVO', 229.8, 495.8], ['EURO', 462.6, 710.1]],
        ModelloF24::semplificato->value => [['CONTRIBUENTE', 24.4, 81.8], ['IDENTIFICATIVO', 229.8, 182.6], ['EURO', 455.3, 326.5], ['CONTRIBUENTE', 24.4, 489.8]],
        ModelloF24::elide->value => [['CONTRIBUENTE', 26.4, 94.9], ['EURO', 385.2, 698.1]],
        ModelloF24::accise->value => [['CONTRIBUENTE', 24.5, 94.1], ['IDENTIFICATIVO', 229.8, 495.2], ['EURO', 462.6, 709.1]],
    ];
    private const TOLLERANZA = 1.0;

    private string $cartellaLavoro;

    public function __construct(private string $cartellaDestinazione)
    {
        $this->cartellaLavoro = sys_get_temp_dir() . '/modelli-f24-' . bin2hex(random_bytes(4));
    }

    /**
     * @param string|null $cartellaPdf cartella con i PDF già scaricati (<modello>.pdf)
     * @param callable(string): void $messaggio
     * @return string[] file SVG scritti
     */
    public function aggiorna(?string $cartellaPdf, callable $messaggio): array
    {
        $this->verificaStrumenti();
        if ((mkdir($this->cartellaLavoro, 0700, true) === false) || ((is_dir($this->cartellaDestinazione) === false) && (mkdir($this->cartellaDestinazione, 0775, true) === false))) {
            throw new \RuntimeException('Impossibile creare le cartelle di lavoro');
        }
        try {
            $sfondi = [];
            foreach (ModelloF24::cases() as $modello) {
                $nome = ModuloUfficialeF24::getNome($modello);
                $pdf = $cartellaPdf === null ? $this->scarica($modello, $messaggio) : rtrim($cartellaPdf, '/') . '/' . $nome . '.pdf';
                if (is_file($pdf) === false) {
                    throw new \RuntimeException('PDF del modello non trovato: ' . $pdf);
                }
                foreach (range(1, ModuloUfficialeF24::PAGINE[$modello->value]) as $pagina) {
                    $this->verificaImpaginazione($modello, $pdf, $pagina);
                    $sfondi[] = $this->converti($modello, $pdf, $pagina);
                }
                $messaggio(sprintf('  %s: %d pagine verificate e convertite', $modello->getLabel(), ModuloUfficialeF24::PAGINE[$modello->value]));
            }
            return $sfondi;
        } finally {
            array_map('unlink', glob($this->cartellaLavoro . '/*') ?: []);
            rmdir($this->cartellaLavoro);
        }
    }

    private function verificaStrumenti(): void
    {
        foreach (['pdftocairo', 'pdftotext'] as $comando) {
            if (trim((string) shell_exec('command -v ' . $comando)) === '') {
                throw new \RuntimeException($comando . ' non disponibile: installare poppler-utils (vedi Dockerfile)');
            }
        }
    }

    private function scarica(ModelloF24 $modello, callable $messaggio): string
    {
        [$scheda, $nomeFile] = self::SCHEDE[$modello->value];
        $html = $this->leggi($scheda);
        if (preg_match('~href="([^"]*/documents/[^"]*' . $nomeFile . '[^"]*\.pdf[^"]*)"~i', $html, $link) !== 1) {
            throw new \RuntimeException('Link al modello non trovato nella pagina ' . $scheda);
        }
        $url = html_entity_decode($link[1]);
        if (str_starts_with($url, '/')) {
            $url = 'https://www.agenziaentrate.gov.it' . $url;
        }
        $messaggio('  ' . $modello->getLabel() . ': ' . $url);
        $file = $this->cartellaLavoro . '/' . ModuloUfficialeF24::getNome($modello) . '.pdf';
        $contenuto = $this->leggi($url);
        if (str_starts_with($contenuto, '%PDF') === false) {
            throw new \RuntimeException('Il file scaricato non è un PDF: ' . $url);
        }
        file_put_contents($file, $contenuto);
        return $file;
    }

    private function leggi(string $url): string
    {
        $contesto = stream_context_create(['http' => ['timeout' => 60, 'user_agent' => 'Mozilla/5.0 (CommSuite modelli-f24)']]);
        $contenuto = @file_get_contents($url, false, $contesto);
        if ($contenuto === false) {
            throw new \RuntimeException('Download non riuscito: ' . $url);
        }
        return $contenuto;
    }

    private function verificaImpaginazione(ModelloF24 $modello, string $pdf, int $pagina): void
    {
        $file = $this->cartellaLavoro . '/parole.html';
        $this->esegui(sprintf('pdftotext -bbox -f %d -l %d %s %s', $pagina, $pagina, escapeshellarg($pdf), escapeshellarg($file)));
        $parole = file_get_contents($file);
        foreach (self::RIFERIMENTI[$modello->value] as [$parola, $x, $y]) {
            preg_match_all('~xMin="([\d.]+)" yMin="([\d.]+)"[^>]*>' . $parola . '<~', $parole, $posizioni, PREG_SET_ORDER);
            $trovata = array_filter($posizioni, fn(array $posizione) => (abs((float) $posizione[1] - $x) <= self::TOLLERANZA) && (abs((float) $posizione[2] - $y) <= self::TOLLERANZA));
            if (count($trovata) === 0) {
                throw new \RuntimeException(sprintf('%s, pagina %d: "%s" non è nella posizione attesa (%.1f, %.1f). Il modello è cambiato: rilevare le coordinate in ModuloUfficialeF24.',
                                $modello->getLabel(), $pagina, $parola, $x, $y));
            }
        }
    }

    private function converti(ModelloF24 $modello, string $pdf, int $pagina): string
    {
        $destinazione = $this->cartellaDestinazione . '/' . basename(ModuloUfficialeF24::getSfondo($modello, $pagina));
        $this->esegui(sprintf('pdftocairo -svg -f %d -l %d %s %s', $pagina, $pagina, escapeshellarg($pdf), escapeshellarg($destinazione)));
        return $destinazione;
    }

    private function esegui(string $comando): void
    {
        exec($comando . ' 2>&1', $output, $esito);
        if ($esito !== 0) {
            throw new \RuntimeException('Comando non riuscito: ' . $comando . ' ' . implode(' ', $output));
        }
    }
}
