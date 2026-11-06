<?php

namespace Contabilita\Console\Commands;

use Contabilita\Application\Services\TabelleRiferimento\TabelleContabilita;
use SismaFramework\Console\BaseClasses\BaseCommand;

/**
 * Importa o aggiorna le tabelle di riferimento della contabilità comuni a
 * tutti gli studi (piani dei conti modello e codici IVA) dalle sorgenti
 * del modulo.
 *
 * Uso:
 *   sisma tabelle-contabilita                 aggiorna il database
 *   sisma tabelle-contabilita --sql=FILE      genera lo script SQL
 */
class TabelleContabilitaCommand extends BaseCommand
{

    #[\Override]
    public function checkCompatibility(string $command): bool
    {
        return $command === 'tabelle-contabilita';
    }

    #[\Override]
    protected function help(): string
    {
        return <<<OUTPUT
Usage: sisma tabelle-contabilita [--sql=FILE]

Importa o aggiorna le tabelle della contabilità comuni a tutti gli studi
dalle sorgenti del modulo (Contabilita/Database/sorgenti):
  - piani-conti.csv   piani dei conti modello della suite
  - conti.csv         conti dei piani, con funzione e voce CEE
  - codici-iva.csv    codici IVA della suite con la norma
  - prodotti-agricoli.csv  prodotti con la percentuale di compensazione

I piani delle aziende e i codici e i modelli degli studi non sono toccati.

Options:
  --sql=FILE        scrive lo script SQL invece di aggiornare il database
                    (es. Contabilita/Database/dati-riferimento.sql)
OUTPUT;
    }

    #[\Override]
    protected function execute(): bool
    {
        try {
            $tabelle = new TabelleContabilita();
            $dati = $tabelle->leggi();
            $this->output(sprintf('Piani dei conti: %d, conti: %d, codici IVA: %d', count($dati['piani']), count($dati['conti']), count($dati['codiciIva'])));
            $fileSql = $this->getOption('sql');
            return $fileSql === null ? $this->aggiornaDatabase($tabelle, $dati) : $this->scriviSql($fileSql, $tabelle, $dati);
        } catch (\Throwable $errore) {
            $this->output('Errore: ' . $errore->getMessage());
            return false;
        }
    }

    private function scriviSql(string $fileSql, TabelleContabilita $tabelle, array $dati): bool
    {
        $file = @fopen($fileSql, 'w') ?: throw new \RuntimeException('Impossibile scrivere ' . $fileSql);
        fwrite($file, "-- =====================================================\n"
                . "-- Contabilita - piani dei conti modello e codici IVA\n"
                . "-- =====================================================\n"
                . "-- Generato con \"sisma tabelle-contabilita --sql\" il " . date('d/m/Y H:i') . ": non modificare a mano.\n"
                . "-- Fonte: Contabilita/Database/sorgenti (norma indicata per codice IVA).\n"
                . sprintf("-- Righe: piano_conti %d, conto %d, codice_iva %d.\n", count($dati['piani']), count($dati['conti']), count($dati['codiciIva']))
                . "-- Idempotente: aggiorna le tabelle della suite senza toccare i dati degli studi.\n"
                . "-- Da eseguire dopo schema.sql.\n\n"
                . "SET NAMES utf8mb4;\n\n");
        foreach ($tabelle->istruzioni($dati) as $istruzione) {
            fwrite($file, $istruzione . "\n\n");
        }
        fclose($file);
        $this->output('Script scritto in ' . $fileSql);
        return true;
    }

    private function aggiornaDatabase(TabelleContabilita $tabelle, array $dati): bool
    {
        $connessione = new \PDO('mysql:host=' . \Config\DATABASE_HOST . ';port=' . \Config\DATABASE_PORT . ';dbname=' . \Config\DATABASE_NAME . ';charset=utf8mb4',
                \Config\DATABASE_USERNAME, \Config\DATABASE_PASSWORD, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $connessione->beginTransaction();
        foreach ($tabelle->istruzioni($dati) as $istruzione) {
            $connessione->exec($istruzione);
        }
        $connessione->commit();
        $this->output(sprintf('  piano_conti: %d piani della suite', (int) $connessione->query('SELECT COUNT(*) FROM `piano_conti` WHERE `studio_id` IS NULL AND `soggetto_id` IS NULL')->fetchColumn()));
        $this->output(sprintf('  codice_iva: %d codici della suite attivi', (int) $connessione->query('SELECT COUNT(*) FROM `codice_iva` WHERE `studio_id` IS NULL AND `attivo` = 1')->fetchColumn()));
        $this->output('Tabelle della contabilità aggiornate.');
        return true;
    }
}
