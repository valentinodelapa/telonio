<?php

namespace Deleghe\Console\Commands;

use Anagrafica\Application\Services\TabelleRiferimento\GeneratoreSql;
use Deleghe\Application\Services\TabelleRiferimento\ArchivioTabelleF24;
use Deleghe\Application\Services\TabelleRiferimento\FonteAgenzia;
use Deleghe\Application\Services\TabelleRiferimento\SorgentiModulo;
use Deleghe\Application\Services\TabelleRiferimento\TabelleF24Agenzia;
use Deleghe\Application\Services\TabelleRiferimento\TassiRiferimentoBce;
use Deleghe\Application\Services\TabelleRiferimento\UfficiAgenzia;
use SismaFramework\Console\BaseClasses\BaseCommand;

/**
 * Importa o aggiorna le tabelle di riferimento delle deleghe F24:
 * - dall'archivio delle tabelle F24 dell'Agenzia delle Entrate: codici
 *   tributo con le regole di compilazione, causali contributo INPS e degli
 *   altri enti, sedi INPS e INAIL, regioni, province, enti, tipi ELIDE,
 *   formati della matricola INPS;
 * - dal servizio "Ricerca codici tributo": codici degli uffici;
 * - dal servizio dati della BCE: tasso di riferimento per le sanzioni civili;
 * - dalle sorgenti del modulo: tassi di interesse legale, codici del
 *   ravvedimento e rateazioni straordinarie del secondo acconto.
 *
 * Uso:
 *   sisma tabelle-f24                 aggiorna il database
 *   sisma tabelle-f24 --sql=FILE      genera lo script SQL
 */
class TabelleF24Command extends BaseCommand
{

    /**
     * Tabelle con righe referenziate: prima dell'aggiornamento tutte le
     * righe sono segnate come non in uso, l'upsert riattiva quelle ancora
     * presenti nella fonte.
     */
    private const TABELLE_IN_USO = ['codice_tributo', 'codice_tabella_f24', 'causale_contributo'];

    #[\Override]
    public function checkCompatibility(string $command): bool
    {
        return $command === 'tabelle-f24';
    }

    #[\Override]
    protected function help(): string
    {
        return <<<OUTPUT
Usage: sisma tabelle-f24 [--sql=FILE] [--archivio=CARTELLA|FILE|URL] [--copia=CARTELLA] [--bce=FILE|URL]

Importa o aggiorna le tabelle di riferimento del modulo Deleghe:
  - archivio delle tabelle F24 dell'Agenzia delle Entrate ("Tabelle zippate",
    file RAR estratto con bsdtar): codici tributo con le regole di
    compilazione, causali contributo, sedi INPS e INAIL, regioni, province,
    altri enti, tipi ELIDE, formati della matricola INPS
  - codici degli uffici (servizio "Ricerca codici tributo")
  - tasso di riferimento BCE per le sanzioni civili INPS e INAIL
  - tassi di interesse legale, codici del ravvedimento e rateazioni
    straordinarie del secondo acconto (Deleghe/Database/sorgenti)

Le righe esistenti vengono aggiornate, le nuove inserite; i codici non più
presenti nelle fonti restano (sono referenziati) con in_uso = 0.

Options:
  --sql=FILE        scrive lo script SQL invece di aggiornare il database
                    (es. Deleghe/Database/dati-riferimento.sql)
  --archivio=...    cartella già estratta, file RAR o URL dell'archivio
                    (default: l'archivio più recente indicato dall'Agenzia)
  --copia=CARTELLA  salva la pagina degli uffici e la rilegge da lì
  --bce=FILE|URL    serie CSV dei tassi BCE (default: servizio dati BCE)
OUTPUT;
    }

    #[\Override]
    protected function execute(): bool
    {
        $archivio = new ArchivioTabelleF24();
        try {
            $tabelle = $this->leggiFonti($archivio);
            $fileSql = $this->getOption('sql');
            return $fileSql === null ? $this->aggiornaDatabase($tabelle) : $this->scriviSql($fileSql, $tabelle, $archivio);
        } catch (\Throwable $errore) {
            $this->output('Errore: ' . $errore->getMessage());
            return false;
        } finally {
            $archivio->pulisci();
        }
    }

    /**
     * @return array<string, array{colonne: string[], righe: array}>
     */
    private function leggiFonti(ArchivioTabelleF24 $archivio): array
    {
        $this->output('Archivio delle tabelle F24...');
        $archivio->prepara($this->getOption('archivio'), fn(string $messaggio) => $this->output($messaggio));
        $this->output('  aggiornamento ' . ($archivio->getData() === null ? 'non indicato' : \DateTimeImmutable::createFromFormat('Ymd', $archivio->getData())->format('d/m/Y')));
        $lettore = new TabelleF24Agenzia($archivio);
        $codici = $lettore->leggiCodiciTributo();
        foreach (array_count_values(array_column($codici, 'sezione')) as $sezione => $numero) {
            $this->output(sprintf('  codici tributo sezione %s: %d', $sezione, $numero));
        }
        $voci = $lettore->leggiTabelle();
        $this->output('Codici degli uffici...');
        $uffici = (new UfficiAgenzia(new FonteAgenzia($this->getOption('copia'))))->leggi();
        $this->output('  ' . count($uffici) . ' uffici');
        $voci = array_merge($voci, $uffici);
        foreach (array_count_values(array_column($voci, 'tabella')) as $tabella => $numero) {
            $this->output(sprintf('  tabella %s: %d voci', $tabella, $numero));
        }
        $causali = $lettore->leggiCausali($voci);
        foreach (array_count_values(array_column($causali, 'ente')) as $ente => $numero) {
            $this->output(sprintf('  causali ente %s: %d', $ente, $numero));
        }
        foreach ($lettore->getAvvisi() as $avviso) {
            $this->output('  avviso: ' . $avviso);
        }
        $this->output('Tasso di riferimento BCE...');
        $tassiBce = (new TassiRiferimentoBce())->leggi($this->getOption('bce'));
        $ultimo = end($tassiBce);
        $this->output(sprintf('  %d variazioni, ultima dal %s: %s%%', count($tassiBce), $ultimo['valido_dal'], $ultimo['tasso']));
        $sorgenti = new SorgentiModulo();
        $tassi = $sorgenti->leggiTassi();
        $esistenti = array_fill_keys(array_map(fn($codice) => $codice['sezione'] . '|' . $codice['codice'], $codici), true);
        $regole = $sorgenti->leggiRegoleRavvedimento($esistenti);
        $secondoAcconto = $sorgenti->leggiRateazioniSecondoAcconto($esistenti);
        $this->output(sprintf('Sorgenti del modulo: %d tassi di interesse legale, %d regole di ravvedimento, %d rateazioni del secondo acconto',
                        count($tassi), count($regole), count($secondoAcconto)));
        return [
            'codice_tributo' => ['colonne' => TabelleF24Agenzia::COLONNE_CODICI, 'righe' => $codici],
            'codice_tabella_f24' => ['colonne' => TabelleF24Agenzia::COLONNE_TABELLE, 'righe' => $voci],
            'causale_contributo' => ['colonne' => TabelleF24Agenzia::COLONNE_CAUSALI, 'righe' => $causali],
            'tasso_interesse_legale' => ['colonne' => SorgentiModulo::COLONNE_TASSI, 'righe' => $tassi],
            'regola_ravvedimento' => ['colonne' => SorgentiModulo::COLONNE_REGOLE, 'righe' => $regole],
            'rateazione_secondo_acconto' => ['colonne' => SorgentiModulo::COLONNE_SECONDO_ACCONTO, 'righe' => $secondoAcconto],
            'tasso_riferimento' => ['colonne' => TassiRiferimentoBce::COLONNE, 'righe' => $tassiBce],
        ];
    }

    private function istruzioni(array $tabelle): \Generator
    {
        $generatoreSql = new GeneratoreSql();
        foreach ($tabelle as $tabella => $dati) {
            if (in_array($tabella, self::TABELLE_IN_USO, true)) {
                yield 'UPDATE `' . $tabella . '` SET `in_uso` = 0;';
            } elseif (in_array($tabella, ['regola_ravvedimento', 'rateazione_secondo_acconto'], true)) {
                // dati curati nel modulo, non referenziati: si sostituiscono
                yield 'DELETE FROM `' . $tabella . '`;';
            }
            yield from $generatoreSql->generaUpsert($tabella, $dati['colonne'], $dati['righe']);
        }
    }

    private function scriviSql(string $fileSql, array $tabelle, ArchivioTabelleF24 $archivio): bool
    {
        $file = fopen($fileSql, 'w') ?: throw new \RuntimeException('Impossibile scrivere ' . $fileSql);
        fwrite($file, "-- =====================================================\n"
                . "-- Deleghe - tabelle di riferimento\n"
                . "-- =====================================================\n"
                . "-- Generato con \"sisma tabelle-f24 --sql\" il " . date('d/m/Y H:i') . ": non modificare a mano.\n"
                . "-- Fonti:\n"
                . "--   archivio delle tabelle F24 dell'Agenzia delle Entrate"
                . ($archivio->getData() === null ? '' : ' aggiornato al ' . \DateTimeImmutable::createFromFormat('Ymd', $archivio->getData())->format('d/m/Y')) . "\n"
                . "--     " . ArchivioTabelleF24::URL_RIEPILOGO . "\n"
                . "--   codici degli uffici: " . FonteAgenzia::URL . "VisualizzaTabella.php?ArcName=UFFICI\n"
                . "--   tassi di interesse legale, codici del ravvedimento e rateazioni del secondo acconto: Deleghe/Database/sorgenti\n"
                . "--   tasso di riferimento BCE: " . TassiRiferimentoBce::URL . "\n"
                . "-- Righe: " . implode(', ', array_map(fn($tabella, $dati) => $tabella . ' ' . count($dati['righe']), array_keys($tabelle), $tabelle)) . ".\n"
                . "-- Idempotente: eseguibile anche su tabelle già popolate per aggiornarle; le\n"
                . "-- righe non più presenti nelle fonti restano con in_uso = 0.\n"
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
        foreach ($this->istruzioni($tabelle) as $istruzione) {
            $connessione->exec($istruzione);
        }
        $connessione->commit();
        foreach (array_keys($tabelle) as $tabella) {
            $this->output(sprintf('  %s: %d righe (%+d)', $tabella, $this->conta($connessione, $tabella), $this->conta($connessione, $tabella) - $righePrima[$tabella]));
        }
        foreach (self::TABELLE_IN_USO as $tabella) {
            $nonInUso = (int) $connessione->query("SELECT COUNT(*) FROM `$tabella` WHERE `in_uso` = 0")->fetchColumn();
            if ($nonInUso > 0) {
                $this->output(sprintf('  %s: %d righe non più presenti nelle fonti (mantenute, non in uso)', $tabella, $nonInUso));
            }
        }
        $this->output('Tabelle di riferimento aggiornate.');
        return true;
    }

    private function conta(\PDO $connessione, string $tabella): int
    {
        return (int) $connessione->query("SELECT COUNT(*) FROM `$tabella`")->fetchColumn();
    }
}
