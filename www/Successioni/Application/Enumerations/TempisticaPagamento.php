<?php

namespace Successioni\Application\Enumerations;

/**
 * Tempistica del pagamento dell'imposta di successione autoliquidata (rigo
 * EF18ter, colonna 1).
 */
enum TempisticaPagamento: string
{

    case nonContestuale = '1';
    case contestuale = '2';

    public function getLabel(): string
    {
        return match ($this) {
            self::nonContestuale => 'Entro 90 giorni dal termine di presentazione (F24 dei beneficiari)',
            self::contestuale => 'Con la presentazione (addebito sul conto indicato)',
        };
    }
}
