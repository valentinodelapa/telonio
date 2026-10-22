<?php

namespace Successioni\Console\Commands;

use Anagrafica\Application\Services\TabelleRiferimento\GeneratoreSql;
use SismaFramework\Console\BaseClasses\BaseCommand;
use Successioni\Application\Services\TabelleRiferimento\SpecificheSuc;

/**
 * Aggiorna gli XML Schema della dichiarazione di successione (fornitura
 * SUC13) dalle specifiche tecniche dell'Agenzia delle Entrate, verifica i
 * codici degli enum del modulo e importa la tabella degli uffici di
 * registrazione.
 *
 * Uso:
 *   sisma specifiche-successioni                aggiorna XSD e database
 *   sisma specifiche-successioni --sql=FILE     genera lo script SQL
 *   sisma specifiche-successioni --zip=FILE|URL usa un archivio già scaricato
 */
class SpecificheSuccessioniCommand extends BaseCommand
{

    #[\Override]
    public function checkCompatibility(string $command): bool
    {
        return $command === 'specifiche-successioni';
    }

    #[\Override]
    protected function help(): string
    {
        return <<<OUTPUT
Usage: sisma specifiche-successioni [--zip=FILE|URL] [--sql=FILE] [--solo-verifica]

Scarica l'archivio delle specifiche tecniche della dichiarazione di
successione (SUC13_aaaammgg.zip, pagina "Specifiche tecniche" dell'Agenzia
delle Entrate), aggiorna gli XSD del modulo (Successioni/Application/Assets/xsd),
confronta i codici degli enum del modulo con le enumerazioni degli schemi e
importa gli uffici di registrazione. Se i codici non coincidono il comando
segnala gli enum da aggiornare e non modifica nulla.

Options:
  --zip=FILE|URL     archivio già scaricato o URL dell'archivio
  --sql=FILE         scrive lo script SQL degli uffici invece di aggiornare il
                     database (es. Successioni/Database/dati-riferimento.sql)
  --solo-verifica    confronta gli enum con gli XSD del modulo, senza scaricare
OUTPUT;
    }

    #[\Override]
    protected function execute(): bool
    {
        $specifiche = new SpecificheSuc();
        try {
            if ($this->getOption('solo-verifica') !== null) {
                return $this->stampaDifferenze($specifiche->verificaEnum(SpecificheSuc::getCartellaXsd()));
            }
            $temporanea = sys_get_temp_dir() . '/specifiche-suc-' . getmypid();
            mkdir($temporanea, 0775, true);
            $archivio = $specifiche->scarica($this->getOption('zip'), $temporanea . '/SUC13.zip');
            $estratti = $specifiche->estrai($archivio, $temporanea . '/xsd');
            $this->output(sprintf('Archivio delle specifiche: %d XSD', count($estratti)));
            if ($this->stampaDifferenze($specifiche->verificaEnum($temporanea . '/xsd')) === false) {
                return false;
            }
            $tabelle = [
                'ufficio_registrazione' => ['colonne' => SpecificheSuc::COLONNE_UFFICI, 'righe' => $specifiche->leggiUffici($temporanea . '/xsd')],
                'ufficio_trascrizione' => ['colonne' => SpecificheSuc::COLONNE_CODICE_DENOMINAZIONE, 'righe' => $specifiche->leggiUfficiTrascrizione($temporanea . '/xsd')],
                'comune_catastale_tavolare' => ['colonne' => SpecificheSuc::COLONNE_CODICE_DENOMINAZIONE, 'righe' => $specifiche->leggiComuniCatastali($temporanea . '/xsd')],
            ];
            foreach ($tabelle as $tabella => ['righe' => $righe]) {
                $this->output(sprintf('%s: %d', $tabella, count($righe)));
            }
            foreach ($estratti as $nome) {
                $destinazione = SpecificheSuc::getCartellaXsd() . '/' . $nome;
                if ((is_dir(dirname($destinazione)) === false) && (mkdir(dirname($destinazione), 0775, true) === false)) {
                    throw new \RuntimeException('Impossibile creare ' . dirname($destinazione));
                }
                copy($temporanea . '/xsd/' . $nome, $destinazione);
            }
            $this->output('XSD aggiornati in ' . SpecificheSuc::getCartellaXsd());
            $this->eliminaCartella($temporanea);
            $fileSql = $this->getOption('sql');
            return $fileSql === null ? $this->aggiornaDatabase($tabelle) : $this->scriviSql($fileSql, $tabelle);
        } catch (\Throwable $errore) {
            $this->output('Errore: ' . $errore->getMessage());
            return false;
        }
    }

    private function eliminaCartella(string $cartella): void
    {
        $elementi = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cartella, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($elementi as $elemento) {
            $elemento->isDir() ? rmdir($elemento->getPathname()) : unlink($elemento->getPathname());
        }
        rmdir($cartella);
    }

    private function stampaDifferenze(array $differenze): bool
    {
        if (count($differenze) === 0) {
            $this->output('Codici degli enum allineati alle specifiche.');
            return true;
        }
        $this->output('Enum da aggiornare prima di usare le nuove specifiche:');
        foreach ($differenze as $differenza) {
            $this->output('  ' . $differenza);
        }
        return false;
    }

    private function istruzioni(array $tabelle): \Generator
    {
        $generatore = new GeneratoreSql();
        foreach ($tabelle as $tabella => ['colonne' => $colonne, 'righe' => $righe]) {
            yield from $generatore->generaUpsert($tabella, $colonne, $righe);
        }
    }

    private function scriviSql(string $fileSql, array $tabelle): bool
    {
        $file = fopen($fileSql, 'w');
        if ($file === false) {
            throw new \RuntimeException('Impossibile scrivere ' . $fileSql);
        }
        fwrite($file, "-- Successioni - dati di riferimento (generato da: sisma specifiche-successioni --sql)\n"
                . "-- Fonte: specifiche tecniche della dichiarazione di successione (fornitura SUC13),\n"
                . "--   " . SpecificheSuc::URL_PAGINA . "\n"
                . "-- Righe: " . implode(', ', array_map(fn($tabella, $dati) => $tabella . ' ' . count($dati['righe']), array_keys($tabelle), $tabelle)) . ".\n"
                . "-- Idempotente: aggiorna per codice, non cancella righe (referenziate).\n"
                . "-- Da eseguire dopo schema.sql.\n\n"
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
        $connessione->beginTransaction();
        foreach ($this->istruzioni($tabelle) as $istruzione) {
            $connessione->exec($istruzione);
        }
        $connessione->commit();
        foreach (array_keys($tabelle) as $tabella) {
            $this->output(sprintf('%s: %d righe', $tabella, (int) $connessione->query("SELECT COUNT(*) FROM `$tabella`")->fetchColumn()));
        }
        return true;
    }
}
