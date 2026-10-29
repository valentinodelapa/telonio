<?php

namespace Telonio\Application\Services\Aggiornamenti;

/**
 * Piano dell'aggiornamento del database dagli script dei moduli
 * (<Modulo>/Database), nell'ordine di MODULE_FOLDERS (dipendenze prima):
 *
 * - modulo non ancora installato (nessuna delle tabelle del suo schema.sql
 *   esiste): schema.sql e dati-riferimento.sql; gli aggiornamento-*.sql
 *   sono solo registrati, perché lo schema è già quello della versione;
 * - modulo installato: gli aggiornamento-<versione>.sql non ancora
 *   registrati, in ordine di versione, poi schema.sql (tabelle nuove), la
 *   registrazione degli aggiornamenti eseguiti e dati-riferimento.sql.
 *
 * Tutti gli script sono idempotenti (CREATE TABLE IF NOT EXISTS, ADD COLUMN
 * IF NOT EXISTS, INSERT ... ON DUPLICATE KEY UPDATE): su un database
 * aggiornato a mano prima del registro (aggiornamento_database), gli
 * script di aggiornamento vengono rieseguiti una volta senza effetti.
 */
class PianoAggiornamentoDatabase
{

    public const SCHEMA = 'schema.sql';
    public const DATI = 'dati-riferimento.sql';
    private const AGGIORNAMENTO = '/^aggiornamento-(\d+(?:\.\d+)*)\.sql$/';

    /**
     * @param array<string, string> $moduli cartella Database indicizzata per nome del modulo
     * @param string[] $tabelleEsistenti
     * @param string[] $scriptEseguiti "<Modulo>/<script>" già registrati
     * @return PassoAggiornamento[]
     */
    public function pianifica(array $moduli, array $tabelleEsistenti, array $scriptEseguiti): array
    {
        $tabelleEsistenti = array_flip(array_map('strtolower', $tabelleEsistenti));
        $scriptEseguiti = array_flip($scriptEseguiti);
        $passi = [];
        foreach ($moduli as $modulo => $cartella) {
            $schema = $cartella . DIRECTORY_SEPARATOR . self::SCHEMA;
            $dati = $cartella . DIRECTORY_SEPARATOR . self::DATI;
            $installato = count(array_intersect_key(array_flip($this->getTabelle($schema)), $tabelleEsistenti)) > 0;
            $daApplicare = array_values(array_filter($this->getAggiornamenti($cartella), fn($script) => isset($scriptEseguiti[$modulo . '/' . $script]) === false));
            if ($installato) {
                foreach ($daApplicare as $script) {
                    $passi[] = new PassoAggiornamento($modulo, $script, $cartella . DIRECTORY_SEPARATOR . $script, true, false);
                }
            }
            if (is_file($schema)) {
                $passi[] = new PassoAggiornamento($modulo, self::SCHEMA, $schema, true, false);
            }
            // Registrazione dopo lo schema: il registro (aggiornamento_database)
            // è creato dallo schema del modulo base.
            foreach ($daApplicare as $script) {
                $passi[] = new PassoAggiornamento($modulo, $script, $cartella . DIRECTORY_SEPARATOR . $script, false, true);
            }
            if (is_file($dati)) {
                $passi[] = new PassoAggiornamento($modulo, self::DATI, $dati, true, false);
            }
        }
        return $passi;
    }

    /**
     * @return string[] tabelle create da schema.sql
     */
    public function getTabelle(string $schema): array
    {
        if (is_file($schema) === false) {
            return [];
        }
        preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', (string) file_get_contents($schema), $tabelle);
        return array_map('strtolower', $tabelle[1]);
    }

    /**
     * @return string[] aggiornamento-<versione>.sql in ordine di versione
     */
    public function getAggiornamenti(string $cartella): array
    {
        $script = [];
        foreach (is_dir($cartella) ? scandir($cartella) : [] as $file) {
            if (preg_match(self::AGGIORNAMENTO, $file, $parti) === 1) {
                $script[$parti[1]] = $file;
            }
        }
        uksort($script, 'version_compare');
        return array_values($script);
    }
}
