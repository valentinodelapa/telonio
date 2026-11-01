<?php

namespace Contabilita\Application\Enumerations;

/**
 * Esigibilità dell'IVA del documento (campo EsigibilitaIVA della fattura
 * elettronica): immediata, differita (art. 6, c. 5, D.P.R. 633/1972) o
 * scissione dei pagamenti (art. 17-ter), in cui l'imposta è versata dal
 * cessionario e non entra nella liquidazione del cedente.
 */
enum EsigibilitaIva: string
{

    case immediata = 'I';
    case differita = 'D';
    case scissionePagamenti = 'S';

    public function getLabel(): string
    {
        return match ($this) {
            self::immediata => 'Immediata',
            self::differita => 'Differita (art. 6, c. 5, D.P.R. 633/1972)',
            self::scissionePagamenti => 'Scissione dei pagamenti (art. 17-ter D.P.R. 633/1972)',
        };
    }
}
