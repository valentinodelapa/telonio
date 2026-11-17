<?php

namespace Dichiarativi\Application\Services\Stampa;

use Dichiarativi\Application\Services\TabelleRiferimento\TabelleDichiarativi;

/**
 * Sfondi e posizioni dei campi della stampa sul modello ufficiale: per ogni
 * modello e anno di Database/sorgenti/modelli.csv (fascicoli in PDF
 * pubblicati dall'Agenzia delle Entrate, con il numero di pagine atteso)
 * scarica i fascicoli, li riscrive senza object stream (qpdf: il lettore
 * libero di FPDI non legge i PDF 1.5+ compressi) in
 * Application/Assets/modelli/<modello>-<anno>-<fascicolo>.pdf, legge le
 * posizioni dei campi del catalogo (LettoreModelloPdf) e scrive
 * Application/Assets/modelli/<modello>-<anno>.csv.
 *
 * Le posizioni che la lettura non trova o sbaglia, e quelle del
 * frontespizio (codici che iniziano con "#", valorizzati da
 * DatiFrontespizioStampa), sono in
 * Database/sorgenti/posizioni-<modello>-<anno>.csv: sostituiscono quelle
 * lette; una riga con il fascicolo "-" toglie il campo; la colonna "celle"
 * vi indica il numero di celle uguali della casella (0 per nessuna). Tutte
 * le posizioni passano poi dalla rifinitura sulla pagina
 * (LettoreModelloPdf::rifinisci: importi al ",00" stampato, celle dalle
 * tacche verticali). Nel file scritto la colonna "celle" ha i bordi delle
 * celle, separati da spazi, e la colonna "virgola" la posizione della
 * virgola stampata negli importi con i centesimi.
 */
class ModelliUfficialiDichiarativi
{

    public const CARTELLA_MODELLI = __DIR__ . '/../../Assets/modelli/';
    public const COLONNE_POSIZIONI = ['codice', 'fascicolo', 'pagina', 'x', 'y', 'larghezza', 'altezza', 'celle', 'destra', 'virgola'];

    public function __construct(private string $cartellaSorgenti = TabelleDichiarativi::CARTELLA, private string $cartellaModelli = self::CARTELLA_MODELLI)
    {

    }

    /**
     * @param string|null $cartellaPdf cartella con i fascicoli già scaricati (<modello>-<anno>-<fascicolo>.pdf)
     * @param callable(string): void $messaggio
     * @return array<string, array{posizioni: int, campi: int, mancanti: string[], scarti: string[]}> esito per "<modello>-<anno>"
     */
    public function aggiorna(?string $modello, ?int $anno, ?string $cartellaPdf, callable $messaggio): array
    {
        foreach (['pdftocairo', 'pdftotext', 'pdfinfo', 'qpdf'] as $comando) {
            if (trim((string) shell_exec('command -v ' . $comando)) === '') {
                throw new \RuntimeException($comando . ' non disponibile: installare ' . ($comando === 'qpdf' ? 'qpdf' : 'poppler-utils') . ' (vedi Dockerfile)');
            }
        }
        if ((is_dir($this->cartellaModelli) === false) && (mkdir($this->cartellaModelli, 0775, true) === false)) {
            throw new \RuntimeException('Impossibile creare la cartella ' . $this->cartellaModelli);
        }
        $gruppi = [];
        foreach ($this->leggiCsv($this->cartellaSorgenti . 'modelli.csv') as $riga) {
            if ((($modello === null) || ($riga['modello'] === $modello)) && (($anno === null) || ((int) $riga['anno'] === $anno))) {
                $gruppi[$riga['modello'] . '-' . $riga['anno']][] = $riga;
            }
        }
        if (count($gruppi) === 0) {
            throw new \UnexpectedValueException('Nessun modello in modelli.csv per il modello e l\'anno richiesti.');
        }
        $esiti = [];
        foreach ($gruppi as $nome => $fascicoli) {
            $esiti[$nome] = $this->aggiornaModello($nome, $fascicoli, $cartellaPdf, $messaggio);
        }
        return $esiti;
    }

    private function aggiornaModello(string $nome, array $fascicoli, ?string $cartellaPdf, callable $messaggio): array
    {
        $codici = array_column($this->leggiCsv($this->cartellaSorgenti . 'campi-' . $nome . '.csv'), 'codice');
        if (count($codici) === 0) {
            throw new \RuntimeException('Catalogo dei campi assente: campi-' . $nome . '.csv (sisma specifiche-dichiarativi)');
        }
        $lettore = new LettoreModelloPdf($codici);
        $posizioni = [];
        $scarti = [];
        foreach ($fascicoli as $fascicolo) {
            $destinazione = $this->cartellaModelli . $nome . '-' . $fascicolo['fascicolo'] . '.pdf';
            $sorgente = $cartellaPdf === null ? $this->scarica($fascicolo['url']) : rtrim($cartellaPdf, '/') . '/' . $nome . '-' . $fascicolo['fascicolo'] . '.pdf';
            try {
                if (is_file($sorgente) === false) {
                    throw new \RuntimeException('Fascicolo non trovato: ' . $sorgente);
                }
                preg_match('~Pages:\s+(\d+)~', (string) shell_exec('pdfinfo ' . escapeshellarg($sorgente) . ' 2>/dev/null'), $info);
                if ((int) ($info[1] ?? 0) !== (int) $fascicolo['pagine']) {
                    throw new \UnexpectedValueException(sprintf('%s, fascicolo %s: %d pagine invece di %d, modello cambiato: verificare le posizioni.', $nome, $fascicolo['fascicolo'], (int) ($info[1] ?? 0), $fascicolo['pagine']));
                }
                exec(sprintf('qpdf --object-streams=disable --compress-streams=y --remove-unreferenced-resources=yes %s %s 2>&1', escapeshellarg($sorgente), escapeshellarg($destinazione)), $output, $esito);
                // 3: riscritto con avvisi (piccoli errori di struttura corretti)
                if (($esito !== 0) && ($esito !== 3)) {
                    throw new \RuntimeException('Comando non riuscito: qpdf ' . implode(' ', $output));
                }
            } finally {
                if ($cartellaPdf === null) {
                    @unlink($sorgente);
                }
            }
            array_push($posizioni, ...$lettore->leggi($destinazione, $fascicolo['fascicolo']));
            array_push($scarti, ...array_map(fn(string $scarto) => 'fascicolo ' . $fascicolo['fascicolo'] . ', ' . $scarto, $lettore->getScarti()));
            $messaggio(sprintf('  %s, fascicolo %s: %d pagine', $nome, $fascicolo['fascicolo'], $fascicolo['pagine']));
        }
        $posizioni = $this->correggi($nome, $posizioni);
        $perPagina = [];
        foreach ($posizioni as $posizione) {
            $perPagina[$posizione['fascicolo'] . '/' . $posizione['pagina']][] = $posizione;
        }
        $posizioni = [];
        foreach ($perPagina as $chiave => $campi) {
            [$fascicolo, $pagina] = explode('/', (string) $chiave);
            array_push($posizioni, ...$lettore->rifinisci($this->cartellaModelli . $nome . '-' . $fascicolo . '.pdf', (int) $pagina, $campi));
        }
        $this->scriviPosizioni($this->cartellaModelli . $nome . '.csv', $posizioni);
        $trovati = array_flip(array_column($posizioni, 'codice'));
        return [
            'posizioni' => count($posizioni),
            'campi' => count(array_filter($codici, fn(string $codice) => isset($trovati[$codice]))),
            'mancanti' => array_values(array_filter($codici, fn(string $codice) => !isset($trovati[$codice]))),
            'scarti' => $scarti,
        ];
    }

    /**
     * Correzioni manuali: sostituiscono la posizione letta del campo (o la
     * tolgono con il fascicolo "-"); i campi dell'intestazione ("#CF",
     * "#MODULO") restano per pagina.
     */
    private function correggi(string $nome, array $posizioni): array
    {
        $file = $this->cartellaSorgenti . 'posizioni-' . $nome . '.csv';
        if (is_file($file) === false) {
            return $posizioni;
        }
        $correzioni = $this->leggiCsv($file);
        $sostituiti = array_flip(array_column($correzioni, 'codice'));
        $posizioni = array_values(array_filter($posizioni, fn(array $p) => str_starts_with($p['codice'], '#CF') || str_starts_with($p['codice'], '#MODULO') || !isset($sostituiti[$p['codice']])));
        foreach ($correzioni as $correzione) {
            if ($correzione['fascicolo'] === '-') {
                continue;
            }
            $posizioni[] = ['codice' => $correzione['codice'], 'fascicolo' => $correzione['fascicolo'], 'pagina' => (int) $correzione['pagina'], 'x' => (float) $correzione['x'], 'y' => (float) $correzione['y'],
                'larghezza' => (float) $correzione['larghezza'], 'altezza' => (float) $correzione['altezza'], 'celle' => self::celleUguali((float) $correzione['x'], (float) $correzione['larghezza'], (int) $correzione['celle']),
                'destra' => $correzione['destra'] === '1', 'virgola' => 0.0];
        }
        return $posizioni;
    }

    private function scriviPosizioni(string $file, array $posizioni): void
    {
        usort($posizioni, fn(array $a, array $b) => [$a['fascicolo'], $a['pagina'], $a['codice']] <=> [$b['fascicolo'], $b['pagina'], $b['codice']]);
        $handle = @fopen($file, 'w') ?: throw new \RuntimeException('Impossibile scrivere ' . $file);
        fputcsv($handle, self::COLONNE_POSIZIONI, ';', '"', '');
        foreach ($posizioni as $p) {
            fputcsv($handle, [$p['codice'], $p['fascicolo'], $p['pagina'], self::numero($p['x']), self::numero($p['y']), self::numero($p['larghezza']), self::numero($p['altezza']),
                implode(' ', array_map(self::numero(...), $p['celle'])), $p['destra'] ? '1' : '0', self::numero($p['virgola'] ?? 0.0)], ';', '"', '');
        }
        fclose($handle);
    }

    /**
     * @return list<float> bordi di $numero celle uguali (nessuno con meno di due celle)
     */
    private static function celleUguali(float $x, float $larghezza, int $numero): array
    {
        return $numero < 2 ? [] : array_map(fn(int $i) => round($x + ($i * $larghezza / $numero), 1), range(0, $numero));
    }

    private static function numero(float $valore): string
    {
        return rtrim(rtrim(sprintf('%.3f', $valore), '0'), '.');
    }

    /**
     * @return list<array<string, string>>
     */
    private function leggiCsv(string $file): array
    {
        $handle = @fopen($file, 'r');
        if ($handle === false) {
            return [];
        }
        $colonne = fgetcsv($handle, null, ';', '"', '');
        $righe = [];
        while (($riga = fgetcsv($handle, null, ';', '"', '')) !== false) {
            if (($riga !== [null]) && (count($riga) === count($colonne))) {
                $righe[] = array_combine($colonne, $riga);
            }
        }
        fclose($handle);
        return $righe;
    }

    protected function scarica(string $url): string
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Telonio)']);
        $contenuto = curl_exec($curl);
        $codice = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if (!is_string($contenuto) || ($codice !== 200) || (str_starts_with($contenuto, '%PDF') === false)) {
            throw new \RuntimeException('Download del modello non riuscito (' . $codice . '): ' . $url);
        }
        $file = tempnam(sys_get_temp_dir(), 'modello');
        file_put_contents($file, $contenuto);
        return $file;
    }
}
