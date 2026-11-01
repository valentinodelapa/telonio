<?php

namespace Contabilita\Application\Enumerations;

/**
 * Livello del conto nel piano: mastro (NN), conto (NN.NN) e sottoconto
 * (NN.NN.NNNN). Si registra solo sui sottoconti; mastri e conti
 * raggruppano i saldi.
 */
enum LivelloConto: string
{

    case mastro = 'M';
    case conto = 'C';
    case sottoconto = 'S';

    public function getLabel(): string
    {
        return match ($this) {
            self::mastro => 'Mastro',
            self::conto => 'Conto',
            self::sottoconto => 'Sottoconto',
        };
    }

    /**
     * Livello ricavato dalla forma del codice; null se il codice non è
     * nella forma NN, NN.NN o NN.NN.NNNN.
     */
    public static function daCodice(string $codice): ?self
    {
        return match (true) {
            preg_match('/^\d{2}$/', $codice) === 1 => self::mastro,
            preg_match('/^\d{2}\.\d{2}$/', $codice) === 1 => self::conto,
            preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $codice) === 1 => self::sottoconto,
            default => null,
        };
    }

    /**
     * Codice del conto superiore (null per i mastri).
     */
    public static function codicePadre(string $codice): ?string
    {
        $posizione = strrpos($codice, '.');
        return $posizione === false ? null : substr($codice, 0, $posizione);
    }
}
