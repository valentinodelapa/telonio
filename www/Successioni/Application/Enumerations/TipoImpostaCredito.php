<?php

namespace Successioni\Application\Enumerations;

/**
 * Imposta pagata sull'acquisto della precedente prima casa che genera il
 * credito d'imposta del quadro EH, sezione III (art. 7 L. 448/1998;
 * tabella TipoImposta_Type delle specifiche SUC13).
 */
enum TipoImpostaCredito: string
{

    case registro = '1';
    case iva = '2';
    case ivaSostegniBis = '3';

    public function getLabel(): string
    {
        return match ($this) {
            self::registro => 'Imposta di registro',
            self::iva => 'IVA',
            self::ivaSostegniBis => 'IVA (credito del Decreto Sostegni-bis)',
        };
    }
}
