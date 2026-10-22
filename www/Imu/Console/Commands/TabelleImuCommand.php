<?php

namespace Imu\Console\Commands;

use Anagrafica\Application\Services\TabelleRiferimento\GeneratoreSql;
use Imu\Application\Services\TabelleRiferimento\CircolareTerreniMontani;
use Imu\Application\Services\TabelleRiferimento\TabelleImu;
use SismaFramework\Console\BaseClasses\BaseCommand;

/**
 * Importa o aggiorna le tabelle di riferimento del modulo IMU:
 * coefficienti dei fabbricati del gruppo D a valore contabile e comuni con
 * terreni agricoli esenti (circolare n. 9/1993, isole minori).
 *
 * Uso:
 *   sisma tabelle-imu                 aggiorna il database
 *   sisma tabelle-imu --sql=FILE      genera lo script SQL
 */
class TabelleImuCommand extends BaseCommand
{

    #[\Override]
    public function checkCompatibility(string $command): bool
    {
        return $command === 'tabelle-imu';
    }

    #[\Override]
    protected function help(): string
    {
        return <<<OUTPUT
Usage: sisma tabelle-imu [--sql=FILE] [--copia=CARTELLA]

Importa o aggiorna le tabelle di riferimento del modulo IMU:
  - coefficienti dei fabbricati del gruppo D a valore contabile (comma 746),
    dai decreti annuali del MEF (Imu/Database/sorgenti)
  - comuni con terreni agricoli esenti (comma 758): circolare n. 9/1993
    letta dalla Gazzetta Ufficiale e abbinata all'archivio comuni, isole
    minori dell'allegato A alla L. 448/2001 (Imu/Database/sorgenti)

Le tabelle non sono referenziate: vengono sostituite per intero.

Options:
  --sql=FILE        scrive lo script SQL invece di aggiornare il database
                    (es. Imu/Database/dati-riferimento.sql)
  --copia=CARTELLA  salva il testo della circolare e lo rilegge da lì
OUTPUT;
    }

    #[\Override]
    protected function execute(): bool
    {
        try {
            $tabelle = new TabelleImu();
            $coefficienti = $tabelle->leggiCoefficienti();
            $this->output(sprintf('Coefficienti dei fabbricati D: %d righe, anni %s', count($coefficienti), implode(', ', array_unique(array_column($coefficienti, 'anno_imposta')))));
            $this->output('Circolare n. 9/1993 dalla Gazzetta Ufficiale...');
            $circolare = (new CircolareTerreniMontani($this->getOption('copia')))->leggi();
            $this->output(sprintf('  %d comuni, di cui %d parzialmente delimitati', count($circolare), count(array_filter(array_column($circolare, 'parziale')))));
            $esenzioni = $tabelle->leggiEsenzioni($circolare);
            foreach ($tabelle->getAvvisi() as $avviso) {
                $this->output('  avviso: ' . $avviso);
            }
            $this->output(sprintf('Comuni con terreni esenti: %d righe', count($esenzioni)));
            $dati = [
                'coefficiente_fabbricati_d' => ['colonne' => TabelleImu::COLONNE_COEFFICIENTI, 'righe' => $coefficienti],
                'comune_esenzione_terreni' => ['colonne' => TabelleImu::COLONNE_ESENZIONI, 'righe' => $esenzioni],
            ];
            $fileSql = $this->getOption('sql');
            return $fileSql === null ? $this->aggiornaDatabase($dati) : $this->scriviSql($fileSql, $dati);
        } catch (\Throwable $errore) {
            $this->output('Errore: ' . $errore->getMessage());
            return false;
        }
    }

    private function istruzioni(array $dati): \Generator
    {
        $generatore = new GeneratoreSql();
        foreach ($dati as $tabella => ['colonne' => $colonne, 'righe' => $righe]) {
            yield 'DELETE FROM `' . $tabella . '`;';
            yield from $generatore->generaUpsert($tabella, $colonne, $righe);
        }
    }

    private function scriviSql(string $fileSql, array $dati): bool
    {
        $file = fopen($fileSql, 'w') ?: throw new \RuntimeException('Impossibile scrivere ' . $fileSql);
        fwrite($file, "-- =====================================================\n"
                . "-- Imu - tabelle di riferimento\n"
                . "-- =====================================================\n"
                . "-- Generato con \"sisma tabelle-imu --sql\" il " . date('d/m/Y H:i') . ": non modificare a mano.\n"
                . "-- Fonti:\n"
                . "--   coefficienti dei fabbricati D e isole minori: Imu/Database/sorgenti\n"
                . "--   circolare n. 9/1993: " . CircolareTerreniMontani::URL_ATTO . "\n"
                . "-- Righe: " . implode(', ', array_map(fn($tabella, $tabellaDati) => $tabella . ' ' . count($tabellaDati['righe']), array_keys($dati), $dati)) . ".\n"
                . "-- Idempotente: le tabelle vengono svuotate e ripopolate.\n"
                . "-- Da eseguire dopo schema.sql.\n\n"
                . "SET NAMES utf8mb4;\n\n");
        foreach ($this->istruzioni($dati) as $istruzione) {
            fwrite($file, $istruzione . "\n\n");
        }
        fclose($file);
        $this->output('Script scritto in ' . $fileSql);
        return true;
    }

    private function aggiornaDatabase(array $dati): bool
    {
        $connessione = new \PDO('mysql:host=' . \Config\DATABASE_HOST . ';port=' . \Config\DATABASE_PORT . ';dbname=' . \Config\DATABASE_NAME . ';charset=utf8mb4',
                \Config\DATABASE_USERNAME, \Config\DATABASE_PASSWORD, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $connessione->beginTransaction();
        foreach ($this->istruzioni($dati) as $istruzione) {
            $connessione->exec($istruzione);
        }
        $connessione->commit();
        foreach (array_keys($dati) as $tabella) {
            $this->output(sprintf('  %s: %d righe', $tabella, (int) $connessione->query("SELECT COUNT(*) FROM `$tabella`")->fetchColumn()));
        }
        $this->output('Tabelle di riferimento aggiornate.');
        return true;
    }
}
