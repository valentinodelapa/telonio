<?php

namespace Contabilita\Application\Enumerations;

/**
 * Natura dell'attività del soggetto: commerciale (operazioni rilevanti per
 * l'IVA e reddito d'impresa) o istituzionale, propria degli enti non
 * commerciali (fuori dal campo dell'IVA e dal reddito d'impresa: l'IVA
 * degli acquisti non è detraibile, art. 19-ter D.P.R. 633/1972; i costi non
 * concorrono al reddito d'impresa, art. 144 TUIR; le retribuzioni formano
 * la base retributiva dell'IRAP, art. 10 D.Lgs. 446/1997).
 */
enum NaturaAttivita: string
{

    case commerciale = 'C';
    case istituzionale = 'I';

    public function getLabel(): string
    {
        return match ($this) {
            self::commerciale => 'Commerciale',
            self::istituzionale => 'Istituzionale (enti non commerciali)',
        };
    }
}
