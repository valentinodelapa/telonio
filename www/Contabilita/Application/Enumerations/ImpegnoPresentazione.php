<?php

namespace Contabilita\Application\Enumerations;

/**
 * Impegno dell'incaricato alla presentazione telematica: comunicazione
 * predisposta dal contribuente (1) o da chi effettua l'invio (2).
 */
enum ImpegnoPresentazione: string
{

    case contribuente = '1';
    case intermediario = '2';

    public function getLabel(): string
    {
        return match ($this) {
            self::contribuente => '1 - Predisposta dal contribuente',
            self::intermediario => '2 - Predisposta da chi effettua l\'invio',
        };
    }
}
