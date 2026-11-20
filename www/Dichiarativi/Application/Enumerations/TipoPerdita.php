<?php

namespace Dichiarativi\Application\Enumerations;

/**
 * Perdite fiscali riportabili (art. 84 Tuir): perdite d'impresa del
 * contribuente (prospetto delle perdite non compensate di Redditi PF e SC)
 * o perdite del consolidato nazionale della consolidante (quadro CS del
 * modello CNM).
 */
enum TipoPerdita: string
{

    case impresa = 'I';
    case consolidato = 'C';

    public function getLabel(): string
    {
        return match ($this) {
            self::impresa => 'Perdite d\'impresa',
            self::consolidato => 'Perdite del consolidato nazionale (consolidante)',
        };
    }
}
