<?php

namespace GestioneStudio\Console\Commands;

use GestioneStudio\Application\Services\TabelleRiferimento\CalendarioFiscale;
use SismaFramework\Console\BaseClasses\BaseCommand;

/**
 * Importa o aggiorna il calendario fiscale comune a tutti gli studi
 * (adempimenti, termini e proroghe di legge) dalle sorgenti del modulo.
 *
 * Uso:
 *   sisma calendario-fiscale                 aggiorna il database
 *   sisma calendario-fiscale --sql=FILE      genera lo script SQL
 */
class CalendarioFiscaleCommand extends BaseCommand
{

    #[\Override]
    public function checkCompatibility(string $command): bool
    {
        return $command === 'calendario-fiscale';
    }

    #[\Override]
    protected function help(): string
    {
        return <<<OUTPUT
Usage: sisma calendario-fiscale [--sql=FILE]

Importa o aggiorna il calendario fiscale comune a tutti gli studi dalle
sorgenti del modulo (GestioneStudio/Database/sorgenti):
  - adempimenti.csv   adempimenti con id stabile e fonte normativa
  - termini.csv       termini per periodo dell'anno di riferimento
  - proroghe.csv      proroghe di legge per anno, con il provvedimento

Gli adempimenti tolti dalle sorgenti restano non attivi (sono assegnati ai
clienti); gli adempimenti, i termini e le proroghe degli studi non sono
toccati.

Options:
  --sql=FILE        scrive lo script SQL invece di aggiornare il database
                    (es. GestioneStudio/Database/dati-riferimento.sql)
OUTPUT;
    }

    #[\Override]
    protected function execute(): bool
    {
        try {
            $calendario = new CalendarioFiscale();
            $dati = $calendario->leggi();
            $this->output(sprintf('Adempimenti: %d, termini: %d, proroghe: %d', count($dati['adempimenti']), count($dati['termini']), count($dati['proroghe'])));
            $fileSql = $this->getOption('sql');
            return $fileSql === null ? $this->aggiornaDatabase($calendario, $dati) : $this->scriviSql($fileSql, $calendario, $dati);
        } catch (\Throwable $errore) {
            $this->output('Errore: ' . $errore->getMessage());
            return false;
        }
    }

    private function scriviSql(string $fileSql, CalendarioFiscale $calendario, array $dati): bool
    {
        $file = @fopen($fileSql, 'w') ?: throw new \RuntimeException('Impossibile scrivere ' . $fileSql);
        fwrite($file, "-- =====================================================\n"
                . "-- GestioneStudio - calendario fiscale\n"
                . "-- =====================================================\n"
                . "-- Generato con \"sisma calendario-fiscale --sql\" il " . date('d/m/Y H:i') . ": non modificare a mano.\n"
                . "-- Fonte: GestioneStudio/Database/sorgenti (norma indicata per adempimento).\n"
                . sprintf("-- Righe: adempimento %d, termine_adempimento %d, proroga_adempimento %d.\n", count($dati['adempimenti']), count($dati['termini']), count($dati['proroghe']))
                . "-- Idempotente: aggiorna il calendario fiscale senza toccare i dati degli studi.\n"
                . "-- Da eseguire dopo schema.sql.\n\n"
                . "SET NAMES utf8mb4;\n\n");
        foreach ($calendario->istruzioni($dati) as $istruzione) {
            fwrite($file, $istruzione . "\n\n");
        }
        fclose($file);
        $this->output('Script scritto in ' . $fileSql);
        return true;
    }

    private function aggiornaDatabase(CalendarioFiscale $calendario, array $dati): bool
    {
        $connessione = new \PDO('mysql:host=' . \Config\DATABASE_HOST . ';port=' . \Config\DATABASE_PORT . ';dbname=' . \Config\DATABASE_NAME . ';charset=utf8mb4',
                \Config\DATABASE_USERNAME, \Config\DATABASE_PASSWORD, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $connessione->beginTransaction();
        foreach ($calendario->istruzioni($dati) as $istruzione) {
            $connessione->exec($istruzione);
        }
        $connessione->commit();
        foreach (['adempimento', 'termine_adempimento', 'proroga_adempimento'] as $tabella) {
            $this->output(sprintf('  %s: %d righe del calendario', $tabella, (int) $connessione->query($tabella === 'termine_adempimento'
                                    ? 'SELECT COUNT(*) FROM `termine_adempimento` JOIN `adempimento` ON `adempimento`.`id` = `adempimento_id` WHERE `studio_id` IS NULL'
                                    : "SELECT COUNT(*) FROM `$tabella` WHERE `studio_id` IS NULL")->fetchColumn()));
        }
        $this->output('Calendario fiscale aggiornato.');
        return true;
    }
}
