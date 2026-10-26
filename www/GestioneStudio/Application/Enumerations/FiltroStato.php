<?php

namespace GestioneStudio\Application\Enumerations;

/**
 * Filtro dello scadenzario sullo stato di lavorazione.
 */
enum FiltroStato: string
{

    case aperte = 'aperte';
    case chiuse = 'chiuse';
    case tutte = 'tutte';

    public function getLabel(): string
    {
        return match ($this) {
            self::aperte => 'Da fare e in lavorazione',
            self::chiuse => 'Completate e non dovute',
            self::tutte => 'Tutte',
        };
    }

    public function isIncluso(StatoLavorazione $stato): bool
    {
        return match ($this) {
            self::aperte => $stato->isAperta(),
            self::chiuse => $stato->isAperta() === false,
            self::tutte => true,
        };
    }
}
