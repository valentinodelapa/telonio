<?php

namespace Telonio\Application\Services\Aggiornamenti;

/**
 * Passo dell'aggiornamento del database: uno script SQL di un modulo da
 * eseguire e/o da registrare come applicato.
 */
final class PassoAggiornamento
{

    public function __construct(
            public readonly string $modulo,
            public readonly string $script,
            public readonly string $percorso,
            public readonly bool $esegui,
            public readonly bool $registra,
    ) {

    }

    /**
     * Migrazione dei dati in PHP (aggiornamento-<versione>.php).
     */
    public function isMigrazione(): bool
    {
        return str_ends_with($this->script, '.php');
    }

    public function getDescrizione(): string
    {
        return $this->modulo . '/Database/' . $this->script;
    }
}
