<?php

namespace Dichiarativi\Console\Commands;

use Dichiarativi\Application\Services\TabelleRiferimento\TabelleDichiarativi;
use SismaFramework\Console\BaseClasses\BaseCommand;

/**
 * Importa nel database il catalogo dei campi e dei quadri dei modelli
 * dalle sorgenti del modulo, o ne scrive lo script SQL.
 *
 * Uso:
 *   sisma tabelle-dichiarativi
 *   sisma tabelle-dichiarativi --sql=Dichiarativi/Database/dati-riferimento.sql
 */
class TabelleDichiarativiCommand extends BaseCommand
{

    #[\Override]
    public function checkCompatibility(string $command): bool
    {
        return $command === 'tabelle-dichiarativi';
    }

    #[\Override]
    protected function help(): string
    {
        return <<<OUTPUT
Usage: sisma tabelle-dichiarativi [--sql=FILE]

Allinea le tabelle campo_modello e quadro_modello alle sorgenti in
Dichiarativi/Database/sorgenti (catalogo ricavato dalle specifiche
tecniche con "sisma specifiche-dichiarativi").

Options:
  --sql=FILE   scrive lo script SQL idempotente invece di aggiornare il database
OUTPUT;
    }

    #[\Override]
    protected function execute(): bool
    {
        try {
            $tabelle = new TabelleDichiarativi();
            $dati = $tabelle->leggi();
            $this->output(sprintf('Modelli: %d, campi: %d, quadri: %d', count($dati['modelli']), count($dati['campi']), count($dati['quadri'])));
            $fileSql = $this->getOption('sql');
            return $fileSql === null ? $this->aggiornaDatabase($tabelle, $dati) : $this->scriviSql($fileSql, $tabelle, $dati);
        } catch (\Throwable $errore) {
            $this->output('Errore: ' . $errore->getMessage());
            return false;
        }
    }

    private function scriviSql(string $fileSql, TabelleDichiarativi $tabelle, array $dati): bool
    {
        $file = @fopen($fileSql, 'w') ?: throw new \RuntimeException('Impossibile scrivere ' . $fileSql);
        fwrite($file, "-- =====================================================\n"
                . "-- Dichiarativi - catalogo dei campi e dei quadri dei modelli\n"
                . "-- =====================================================\n"
                . "-- Generato con \"sisma tabelle-dichiarativi --sql\" il " . date('d/m/Y H:i') . ": non modificare a mano.\n"
                . "-- Fonte: Dichiarativi/Database/sorgenti, dalle specifiche tecniche dell'Agenzia delle Entrate (specifiche.csv).\n"
                . sprintf("-- Righe: campo_modello %d, quadro_modello %d.\n", count($dati['campi']), count($dati['quadri']))
                . "-- Idempotente. Da eseguire dopo schema.sql.\n\n"
                . "SET NAMES utf8mb4;\n\n");
        foreach ($tabelle->istruzioni($dati) as $istruzione) {
            fwrite($file, $istruzione . "\n\n");
        }
        fclose($file);
        $this->output('Script scritto in ' . $fileSql);
        return true;
    }

    private function aggiornaDatabase(TabelleDichiarativi $tabelle, array $dati): bool
    {
        $connessione = new \PDO('mysql:host=' . \Config\DATABASE_HOST . ';port=' . \Config\DATABASE_PORT . ';dbname=' . \Config\DATABASE_NAME . ';charset=utf8mb4',
                \Config\DATABASE_USERNAME, \Config\DATABASE_PASSWORD, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $connessione->beginTransaction();
        foreach ($tabelle->istruzioni($dati) as $istruzione) {
            $connessione->exec($istruzione);
        }
        $connessione->commit();
        $this->output(sprintf('  campo_modello: %d campi', (int) $connessione->query('SELECT COUNT(*) FROM `campo_modello`')->fetchColumn()));
        $this->output('Catalogo dei modelli aggiornato.');
        return true;
    }
}
