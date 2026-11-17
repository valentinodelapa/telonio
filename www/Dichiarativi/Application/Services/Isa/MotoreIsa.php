<?php

namespace Dichiarativi\Application\Services\Isa;

/**
 * Motore di calcolo ufficiale degli ISA: il software "Il tuo ISA <anno> CPB"
 * dell'Agenzia delle Entrate, installato nell'immagine PHP (stage
 * "iltuoisa" del Dockerfile, in /opt/iltuoisa/<anno modello>) e usato in
 * modalità batch ("invisibile", classe MainInvisibileIsa):
 *
 * - elabora: dal file d'ingresso (record P, Q ed eventuale R di ogni
 *   posizione, come nelle specifiche tecniche degli ISA) al file con i
 *   risultati del calcolo nei campi "I" del record Q (punteggio, indicatori,
 *   probabilità dei modelli di business), da allegare a Redditi;
 * - esito: l'esito del calcolo in XML (nomi, valori e punteggi degli
 *   indicatori), per mostrarlo nella scheda;
 * - stampaModello e stampaEsito: i PDF del modello compilato e dell'esito.
 *
 * Ogni chiamata avvia la JVM con la banca dati interna del software
 * (qualche secondo); i file passano da una cartella temporanea. Con errori
 * bloccanti nella posizione (es. "Errore 8044") il calcolo e l'esito non
 * producono file: tornano vuoti, con gli errori nel report.
 */
class MotoreIsa
{

    public const CARTELLA = '/opt/iltuoisa/';
    private const CLASSE = 'it.mef.sogei.isa.a%d.invisibile.MainInvisibileIsa';

    public function __construct(private string $cartella = self::CARTELLA)
    {

    }

    public function isDisponibile(int $annoModello): bool
    {
        return is_file($this->getCartellaAnno($annoModello) . 'jre/bin/java');
    }

    /**
     * @return array{output: string, report: string} file con i risultati del calcolo e messaggi del software
     */
    public function elabora(int $annoModello, string $ingresso): array
    {
        $esito = $this->esegui($annoModello, 'elabora', $ingresso, ['output.txt']);
        return ['output' => $esito['output.txt'], 'report' => $esito['report']];
    }

    /**
     * @return array{output: string, xml: string, report: string}
     */
    public function esito(int $annoModello, string $ingresso): array
    {
        $esito = $this->esegui($annoModello, 'esito', $ingresso, ['output.txt', 'esito.xml']);
        return ['output' => $esito['output.txt'], 'xml' => $esito['esito.xml'], 'report' => $esito['report']];
    }

    /**
     * PDF del modello ISA compilato (dal file con i risultati del calcolo).
     */
    public function stampaModello(int $annoModello, string $elaborato): string
    {
        return $this->esegui($annoModello, 'stampamodello', $elaborato, ['stampa.pdf'])['stampa.pdf'];
    }

    /**
     * PDF dell'esito del calcolo (punteggio e indicatori).
     */
    public function stampaEsito(int $annoModello, string $elaborato): string
    {
        return $this->esegui($annoModello, 'stampa', $elaborato, ['stampa.pdf'])['stampa.pdf'];
    }

    private function getCartellaAnno(int $annoModello): string
    {
        return rtrim($this->cartella, '/') . '/' . $annoModello . '/';
    }

    /**
     * @param string[] $prodotti file prodotti dal software, nell'ordine degli argomenti dopo il file d'ingresso
     * @return array<string, string> contenuto dei file prodotti e del report ("report")
     */
    private function esegui(int $annoModello, string $opzione, string $ingresso, array $prodotti): array
    {
        if ($this->isDisponibile($annoModello) === false) {
            throw new \RuntimeException(sprintf('Motore ISA %d non installato in %s (stage "iltuoisa" del Dockerfile)', $annoModello, $this->getCartellaAnno($annoModello)));
        }
        $cartellaAnno = $this->getCartellaAnno($annoModello);
        $lavoro = sys_get_temp_dir() . '/isa-' . bin2hex(random_bytes(6));
        mkdir($lavoro, 0700);
        try {
            file_put_contents($lavoro . '/ingresso.txt', $ingresso);
            $argomenti = [$lavoro . '/report.txt', $lavoro . '/ingresso.txt'];
            foreach ($prodotti as $prodotto) {
                $argomenti[] = $lavoro . '/' . $prodotto;
            }
            // cartella di lavoro come corrente: il software vi scrive i suoi file di servizio (es. derby.log), la cartella
            // del motore resta in sola lettura
            $classpath = implode(':', array_merge(glob($cartellaAnno . '*.jar') ?: [], [$cartellaAnno, $cartellaAnno . 'res']));
            $comando = sprintf('cd %s && HOME=%s %s -Djava.awt.headless=true -cp %s %s -%s %s 2>&1', escapeshellarg($lavoro), escapeshellarg($lavoro), escapeshellarg($cartellaAnno . 'jre/bin/java'), escapeshellarg($classpath),
                sprintf(self::CLASSE, $annoModello), $opzione, implode(' ', array_map('escapeshellarg', $argomenti)));
            exec($comando, $uscita, $codice);
            $report = (string) @file_get_contents($lavoro . '/report.txt');
            $risultato = ['report' => $report];
            foreach ($prodotti as $prodotto) {
                $contenuto = @file_get_contents($lavoro . '/' . $prodotto);
                // controlli bloccanti del software sulla posizione: nessun file prodotto, gli errori sono nel report
                if ((($contenuto === false) || ($contenuto === '')) && in_array($opzione, ['elabora', 'esito'], true) && (preg_match('/Errore \d+/', $report) === 1)) {
                    $risultato[$prodotto] = '';
                    continue;
                }
                if (($codice !== 0) || ($contenuto === false) || ($contenuto === '')) {
                    throw new \RuntimeException(sprintf("Il tuo ISA %d (-%s) non ha prodotto %s.\n%s\n%s", $annoModello, $opzione, $prodotto, trim($report), implode("\n", array_slice($uscita, -10))));
                }
                $risultato[$prodotto] = $contenuto;
            }
            return $risultato;
        } finally {
            self::rimuovi($lavoro);
        }
    }

    private static function rimuovi(string $cartella): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cartella, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $elemento) {
            $elemento->isDir() ? @rmdir($elemento->getPathname()) : @unlink($elemento->getPathname());
        }
        @rmdir($cartella);
    }
}
