<?php

namespace Dichiarativi\Application\Services\TabelleRiferimento;

/**
 * Aggiornamento della sorgente delle aliquote dell'addizionale comunale:
 * per ogni anno d'imposta elencato in Database/sorgenti/addizionali.csv
 * scarica l'elenco pubblicato dall'Agenzia delle Entrate, lo legge
 * (LettoreAddizionaliComunali) e riscrive addizionali-comunali-<anno>.csv,
 * che "sisma tabelle-dichiarativi" importa nella tabella
 * addizionale_comunale. La tabella dell'addizionale regionale è trascritta
 * dalle istruzioni del modello (addizionali-regionali-<anno>.csv).
 */
class AddizionaliComunali extends SpecificheDichiarativi
{

    public const COLONNE_FONTI = ['anno', 'url', 'fonte'];
    public const COLONNE = ['codice', 'aliquota_unica', 'aliquote', 'tipologia', 'esenzione', 'tipizzazione', 'esenzione_casi', 'casi_particolari'];

    public function __construct(private string $cartellaSorgenti = TabelleDichiarativi::CARTELLA)
    {
        parent::__construct($cartellaSorgenti);
    }

    /**
     * @return array<int, int> comuni letti per anno d'imposta
     */
    public function aggiornaAnni(?int $anno = null): array
    {
        if (trim((string) shell_exec('command -v pdftotext')) === '') {
            throw new \RuntimeException('pdftotext non disponibile: installare poppler-utils (vedi Dockerfile)');
        }
        $esiti = [];
        foreach ((new TabelleDichiarativi($this->cartellaSorgenti))->leggiFontiAddizionali() as $fonte) {
            if (($anno !== null) && ($fonte['anno'] !== $anno)) {
                continue;
            }
            $pdf = tempnam(sys_get_temp_dir(), 'addc');
            try {
                file_put_contents($pdf, $this->scarica($fonte['url']));
                $tsv = shell_exec('pdftotext -enc UTF-8 -tsv ' . escapeshellarg($pdf) . ' - 2>/dev/null');
            } finally {
                unlink($pdf);
            }
            if (!is_string($tsv) || ($tsv === '')) {
                throw new \RuntimeException('Testo dell\'elenco non estratto: ' . $fonte['url']);
            }
            $comuni = (new LettoreAddizionaliComunali())->leggi($tsv);
            if (count($comuni) < 7000) {
                throw new \UnexpectedValueException(sprintf('Elenco %d: letti solo %d comuni, impaginazione del PDF cambiata?', $fonte['anno'], count($comuni)));
            }
            ksort($comuni);
            $this->scrivi($fonte['anno'], $comuni);
            $esiti[$fonte['anno']] = count($comuni);
        }
        if (count($esiti) === 0) {
            throw new \UnexpectedValueException('Nessun anno in addizionali.csv per la richiesta.');
        }
        return $esiti;
    }

    private function scrivi(int $anno, array $comuni): void
    {
        $nome = 'addizionali-comunali-' . $anno . '.csv';
        $file = @fopen($this->cartellaSorgenti . $nome, 'w') ?: throw new \RuntimeException('Impossibile scrivere ' . $nome);
        fputcsv($file, self::COLONNE, ';', '"', '');
        $numero = fn(?float $valore): ?string => $valore === null ? null : rtrim(rtrim(number_format($valore, 3, '.', ''), '0'), '.');
        foreach ($comuni as $codice => $comune) {
            fputcsv($file, [$codice, $numero($comune['aliquotaUnica']), count($comune['aliquote']) === 0 ? null : implode('|', array_map($numero, $comune['aliquote'])),
                $comune['tipologia'], $numero($comune['esenzione']), $comune['tipizzazione'], $numero($comune['esenzioneCasi']), $comune['casiParticolari']], ';', '"', '');
        }
        fclose($file);
    }
}
