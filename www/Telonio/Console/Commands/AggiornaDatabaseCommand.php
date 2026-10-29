<?php

namespace Telonio\Console\Commands;

use Telonio\Application\Services\Aggiornamenti\DatabaseAmministrazione;
use Telonio\Application\Services\Aggiornamenti\PassoAggiornamento;
use Telonio\Application\Services\Aggiornamenti\PianoAggiornamentoDatabase;
use SismaFramework\Console\BaseClasses\BaseCommand;

/**
 * Porta il database alla versione del codice installato eseguendo, come
 * root, gli script SQL dei moduli (schema, aggiornamenti non ancora
 * registrati, dati di riferimento); vale anche per la prima installazione.
 *
 * Uso:
 *   sisma aggiorna-database             esegue l'aggiornamento
 *   sisma aggiorna-database --simula    mostra il piano senza eseguirlo
 */
class AggiornaDatabaseCommand extends BaseCommand
{

    public function __construct(
            private ?DatabaseAmministrazione $database = null,
            private PianoAggiornamentoDatabase $piano = new PianoAggiornamentoDatabase(),
            private ?array $moduli = null,
            private ?string $fileCache = null,
    ) {

    }

    #[\Override]
    public function checkCompatibility(string $command): bool
    {
        return $command === 'aggiorna-database';
    }

    #[\Override]
    protected function help(): string
    {
        return <<<OUTPUT
Usage: sisma aggiorna-database [--simula] [--utente=UTENTE]

Porta il database alla versione del codice installato con gli script SQL
dei moduli (<Modulo>/Database), nell'ordine di MODULE_FOLDERS:
  - modulo nuovo: schema.sql e dati-riferimento.sql
  - modulo installato: gli aggiornamento-<versione>.sql non ancora
    applicati, poi schema.sql (tabelle nuove) e dati-riferimento.sql
Gli script applicati sono registrati nella tabella aggiornamento_database.
Vale anche per la prima installazione su un database vuoto. Al termine
elimina la cache dei riferimenti tra le entità (Cache/referenceCache.json).

Si collega come root con DB_ROOT_PASSWORD (.env): l'utente
dell'applicazione non può modificare la struttura del database.

Options:
  --simula          mostra gli script che verrebbero eseguiti, senza eseguirli
  --utente=UTENTE   utente amministratore del database (default root)
OUTPUT;
    }

    #[\Override]
    protected function execute(): bool
    {
        try {
            $database = $this->database ?? new DatabaseAmministrazione($this->getOption('utente') ?? 'root');
            $passi = $this->piano->pianifica($this->moduli ?? $this->getCartelleModuli(), $database->getTabelle(), $database->getScriptEseguiti());
            if ($this->getOption('simula') !== null) {
                return $this->mostraPiano($passi);
            }
            $this->output('Aggiornamento del database ' . $database->getDatabase() . '...');
            foreach ($passi as $passo) {
                $this->eseguiPasso($database, $passo);
            }
            $this->eliminaCache();
            $this->output('Database aggiornato.');
            return true;
        } catch (\Throwable $errore) {
            $this->output('Errore: ' . $errore->getMessage());
            return false;
        }
    }

    /**
     * @return array<string, string>
     */
    private function getCartelleModuli(): array
    {
        $cartelle = [];
        foreach (\Config\MODULE_FOLDERS as $modulo) {
            $cartelle[$modulo] = \Config\ROOT_PATH . $modulo . DIRECTORY_SEPARATOR . 'Database';
        }
        return $cartelle;
    }

    /**
     * @param PassoAggiornamento[] $passi
     */
    private function mostraPiano(array $passi): bool
    {
        $this->output('Piano di aggiornamento (nessuna modifica eseguita):');
        foreach ($passi as $passo) {
            $this->output('  ' . ($passo->esegui ? 'esegue    ' : 'registra  ') . $passo->getDescrizione());
        }
        return true;
    }

    private function eseguiPasso(DatabaseAmministrazione $database, PassoAggiornamento $passo): void
    {
        if ($passo->esegui) {
            $this->output('  ' . $passo->getDescrizione());
            $sql = file_get_contents($passo->percorso);
            if ($sql === false) {
                throw new \RuntimeException('Impossibile leggere ' . $passo->percorso);
            }
            try {
                $risultati = $database->esegui($sql);
            } catch (\RuntimeException $errore) {
                throw new \RuntimeException($passo->getDescrizione() . ': ' . $errore->getMessage(), 0, $errore);
            }
            foreach ($risultati as $risultato) {
                $this->mostraRisultato($risultato);
            }
        }
        if ($passo->registra) {
            $database->registra($passo->modulo, $passo->script);
            if ($passo->esegui === false) {
                $this->output('  ' . $passo->getDescrizione() . ' registrato');
            }
        }
    }

    /**
     * Elenchi di controllo degli script di aggiornamento (es. dati da
     * completare dopo una modifica dello schema).
     */
    private function mostraRisultato(array $risultato): void
    {
        $this->output('    ' . implode(' | ', $risultato['colonne']));
        foreach ($risultato['righe'] as $riga) {
            $this->output('    ' . implode(' | ', array_map(fn($valore) => $valore ?? 'NULL', $riga)));
        }
    }

    private function eliminaCache(): void
    {
        $file = $this->fileCache ?? \Config\REFERENCE_CACHE_PATH;
        if (is_file($file)) {
            unlink($file);
        }
    }
}
