<?php

namespace Anagrafica\Console\Commands;

use Anagrafica\Application\Services\TabelleRiferimento\ArchivioComuniAnpr;
use Anagrafica\Application\Services\TabelleRiferimento\ClassificazioniAtecoIstat;
use Anagrafica\Application\Services\TabelleRiferimento\CodiciIsoUe;
use Anagrafica\Application\Services\TabelleRiferimento\GeneratoreSql;
use Anagrafica\Application\Services\TabelleRiferimento\StatiEsteriAnpr;
use SismaFramework\Console\BaseClasses\BaseCommand;

/**
 * Importa o aggiorna le tabelle di riferimento dell'anagrafica: comuni e
 * stati esteri (ANPR, con i codici ISO alpha-2 di EU Vocabularies),
 * classificazioni ATECO (ISTAT).
 *
 * Uso:
 *   sisma tabelle-riferimento                 aggiorna il database
 *   sisma tabelle-riferimento --sql=FILE      genera lo script SQL
 */
class TabelleRiferimentoCommand extends BaseCommand
{

    /**
     * Tabella => colonne della chiave unica usata per l'aggiornamento.
     */
    private const CHIAVI = [
        'nazione' => ['id_anpr'],
        'comune' => ['id_anpr'],
        'codice_ateco' => ['classificazione', 'codice'],
    ];

    private array $fileTemporanei = [];

    public function __construct(
        private ArchivioComuniAnpr $archivioComuni = new ArchivioComuniAnpr(),
        private StatiEsteriAnpr $statiEsteri = new StatiEsteriAnpr(),
        private CodiciIsoUe $codiciIso = new CodiciIsoUe(),
        private ClassificazioniAtecoIstat $classificazioniAteco = new ClassificazioniAtecoIstat(),
        private GeneratoreSql $generatoreSql = new GeneratoreSql(),
    ) {

    }

    #[\Override]
    public function checkCompatibility(string $command): bool
    {
        return $command === 'tabelle-riferimento';
    }

    #[\Override]
    protected function help(): string
    {
        return <<<OUTPUT
Usage: sisma tabelle-riferimento [--sql=FILE] [--comuni=FILE|URL] [--stati=FILE|URL]
                                 [--iso=FILE|URL] [--ateco2025=FILE|URL] [--ateco2022=FILE|URL]

Importa o aggiorna le tabelle di riferimento del modulo Anagrafica:
  - comuni (ANPR, Ministero dell'Interno): archivio storico con date di
    istituzione e cessazione e codici catastali dell'Agenzia delle Entrate
  - stati esteri (ANPR): codici Z, ISO 3166 e periodo di validità; il codice
    ISO alpha-2 dalla tabella dei paesi di EU Vocabularies (Ufficio delle
    pubblicazioni dell'UE)
  - classificazioni ATECO 2025 e ATECO 2007 agg. 2022 (ISTAT), con il
    raccordo 2022 -> 2025

Le righe esistenti vengono aggiornate, le nuove inserite, nessuna viene
cancellata (sono referenziate dai soggetti).

Options:
  --sql=FILE            scrive lo script SQL invece di aggiornare il database
                        (es. Anagrafica/Database/dati-riferimento.sql)
  --comuni=FILE|URL     archivio comuni (CSV ANPR)
  --stati=FILE|URL      tabella stati esteri (xlsx ANPR)
  --iso=FILE|URL        corrispondenza ISO alpha-3 -> alpha-2 (CSV EU Vocabularies)
  --ateco2025=FILE|URL  struttura ATECO 2025 (xlsx ISTAT)
  --ateco2022=FILE|URL  tabella operativa di riclassificazione ATECO 2022 -> 2025 (xlsx ISTAT)
OUTPUT;
    }

    #[\Override]
    protected function execute(): bool
    {
        try {
            $tabelle = $this->leggiFonti();
            $fileSql = $this->getOption('sql');
            return $fileSql === null ? $this->aggiornaDatabase($tabelle) : $this->scriviSql($fileSql, $tabelle);
        } catch (\Throwable $errore) {
            $this->output('Errore: ' . $errore->getMessage());
            return false;
        } finally {
            array_map('unlink', $this->fileTemporanei);
        }
    }

    /**
     * @return array<string, array{colonne: string[], righe: array}>
     */
    private function leggiFonti(): array
    {
        $this->output('Lettura archivio comuni...');
        $comuni = $this->archivioComuni->leggi($this->scarica($this->getOption('comuni') ?? ArchivioComuniAnpr::URL));
        $this->output('  ' . count($comuni) . ' versioni di comuni, ' . count(array_filter($comuni, fn($comune) => $comune['valido_al'] === null)) . ' vigenti, ' . $this->archivioComuni->getEscluse() . ' escluse perché senza codice catastale');
        $this->output('Lettura stati esteri...');
        $codiciIso = $this->codiciIso->leggi($this->scarica($this->getOption('iso') ?? CodiciIsoUe::url()));
        $stati = $this->statiEsteri->leggi($this->scarica($this->getOption('stati') ?? StatiEsteriAnpr::URL), $codiciIso);
        $this->output('  ' . count($stati) . ' stati e territori');
        $this->output('Lettura classificazioni ATECO...');
        $ateco = $this->classificazioniAteco->leggi(
                $this->scarica($this->getOption('ateco2025') ?? ClassificazioniAtecoIstat::URL_ATECO_2025),
                $this->scarica($this->getOption('ateco2022') ?? ClassificazioniAtecoIstat::URL_RICLASSIFICAZIONE_2022));
        foreach (array_count_values(array_column($ateco, 'classificazione')) as $classificazione => $numero) {
            $this->output('  ATECO ' . $classificazione . ': ' . $numero . ' codici');
        }
        return [
            'nazione' => ['colonne' => StatiEsteriAnpr::COLONNE, 'righe' => $stati],
            'comune' => ['colonne' => ArchivioComuniAnpr::COLONNE, 'righe' => $comuni],
            'codice_ateco' => ['colonne' => ClassificazioniAtecoIstat::COLONNE, 'righe' => $ateco],
        ];
    }

    private function scarica(string $sorgente): string
    {
        if (preg_match('#^https?://#', $sorgente) !== 1) {
            return $sorgente;
        }
        $this->output('  download ' . strtok($sorgente, '?'));
        $contesto = stream_context_create(['http' => ['timeout' => 120, 'user_agent' => 'Mozilla/5.0 (Telonio tabelle-riferimento)']]);
        $contenuto = file_get_contents($sorgente, false, $contesto);
        if ($contenuto === false) {
            throw new \RuntimeException('Download non riuscito: ' . $sorgente);
        }
        $file = tempnam(sys_get_temp_dir(), 'rif');
        file_put_contents($file, $contenuto);
        $this->fileTemporanei[] = $file;
        return $file;
    }

    private function istruzioni(array $tabelle): \Generator
    {
        foreach ($tabelle as $tabella => $dati) {
            yield from $this->generatoreSql->generaUpsert($tabella, $dati['colonne'], array_values($dati['righe']));
        }
    }

    private function scriviSql(string $fileSql, array $tabelle): bool
    {
        $file = fopen($fileSql, 'w') ?: throw new \RuntimeException('Impossibile scrivere ' . $fileSql);
        fwrite($file, "-- =====================================================\n"
                . "-- Anagrafica - tabelle di riferimento\n"
                . "-- =====================================================\n"
                . "-- Generato con \"sisma tabelle-riferimento --sql\" il " . date('d/m/Y H:i') . ": non modificare a mano.\n"
                . "-- Fonti:\n"
                . "--   comuni e stati esteri: tabelle di decodifica ANPR (Ministero dell'Interno),\n"
                . "--   con codici catastali attribuiti dall'Agenzia delle Entrate\n"
                . "--     " . ArchivioComuniAnpr::URL . "\n"
                . "--     " . StatiEsteriAnpr::URL . "\n"
                . "--   codici ISO alpha-2 degli stati esteri: EU Vocabularies, tabella \"Countries\n"
                . "--   and territories\" (Ufficio delle pubblicazioni dell'UE, endpoint SPARQL)\n"
                . "--     " . CodiciIsoUe::ENDPOINT . "\n"
                . "--   classificazioni ATECO: ISTAT\n"
                . "--     " . ClassificazioniAtecoIstat::URL_ATECO_2025 . "\n"
                . "--     " . ClassificazioniAtecoIstat::URL_RICLASSIFICAZIONE_2022 . "\n"
                . "-- Righe: " . implode(', ', array_map(fn($tabella, $dati) => $tabella . ' ' . count($dati['righe']), array_keys($tabelle), $tabelle)) . ".\n"
                . "-- Idempotente: eseguibile anche su tabelle già popolate per aggiornarle.\n"
                . "-- Da eseguire dopo schema.sql e prima delle fixture.\n\n"
                . "SET NAMES utf8mb4;\n\n");
        foreach ($this->istruzioni($tabelle) as $istruzione) {
            fwrite($file, $istruzione . "\n\n");
        }
        fclose($file);
        $this->output('Script scritto in ' . $fileSql);
        return true;
    }

    private function aggiornaDatabase(array $tabelle): bool
    {
        $connessione = new \PDO('mysql:host=' . \Config\DATABASE_HOST . ';port=' . \Config\DATABASE_PORT . ';dbname=' . \Config\DATABASE_NAME . ';charset=utf8mb4',
                \Config\DATABASE_USERNAME, \Config\DATABASE_PASSWORD, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $righePrima = [];
        foreach (array_keys($tabelle) as $tabella) {
            $righePrima[$tabella] = $this->conta($connessione, $tabella);
        }
        $connessione->beginTransaction();
        $righeToccate = 0;
        foreach ($this->istruzioni($tabelle) as $istruzione) {
            $righeToccate += $connessione->exec($istruzione);
        }
        $connessione->commit();
        $inserite = 0;
        foreach ($tabelle as $tabella => $dati) {
            $nuove = $this->conta($connessione, $tabella) - $righePrima[$tabella];
            $inserite += $nuove;
            $this->output(sprintf('  %s: %d inserite', $tabella, $nuove));
            $this->segnalaAssenti($connessione, $tabella, $dati['righe']);
        }
        $this->output(sprintf('  righe modificate: %d', ($righeToccate - $inserite) / 2));
        $this->output('Tabelle di riferimento aggiornate.');
        return true;
    }

    /**
     * Righe del database non più presenti nella fonte: vengono mantenute
     * (possono essere referenziate) e solo segnalate.
     */
    private function segnalaAssenti(\PDO $connessione, string $tabella, array $righe): void
    {
        $colonneChiave = self::CHIAVI[$tabella];
        $chiaveRiga = fn(array $riga) => implode('|', array_map(fn($colonna) => (string) $riga[$colonna], $colonneChiave));
        $chiaviFonte = array_flip(array_map($chiaveRiga, $righe));
        $assenti = [];
        foreach ($connessione->query('SELECT `' . implode('`, `', $colonneChiave) . "` FROM `$tabella`", \PDO::FETCH_ASSOC) as $riga) {
            if (isset($chiaviFonte[$chiaveRiga($riga)]) === false) {
                $assenti[] = $chiaveRiga($riga);
            }
        }
        if (count($assenti) > 0) {
            $this->output(sprintf('  %s: %d righe non più presenti nella fonte (mantenute): %s', $tabella, count($assenti), implode(', ', array_slice($assenti, 0, 20))));
        }
    }

    private function conta(\PDO $connessione, string $tabella): int
    {
        return (int) $connessione->query("SELECT COUNT(*) FROM `$tabella`")->fetchColumn();
    }
}
