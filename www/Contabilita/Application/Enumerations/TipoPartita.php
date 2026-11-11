<?php

namespace Contabilita\Application\Enumerations;

/**
 * Tipo della partita aperta del partitario clienti e fornitori. I
 * documenti (fatture e addebiti) e gli insoluti sono partite "in
 * apertura" (credito verso il cliente, debito verso il fornitore); gli
 * acconti ricevuti o pagati e le note di credito non abbinate sono
 * partite di segno opposto, che si abbinano ai documenti successivi.
 */
enum TipoPartita: string
{

    case documento = 'D';
    case insoluto = 'I';
    case acconto = 'A';
    case notaCredito = 'N';

    public function getLabel(): string
    {
        return match ($this) {
            self::documento => 'Documento',
            self::insoluto => 'Insoluto (partita riaperta)',
            self::acconto => 'Acconto',
            self::notaCredito => 'Nota di credito da abbinare',
        };
    }

    /**
     * La partita è nel segno naturale del partitario (dare per i clienti,
     * avere per i fornitori).
     */
    public function isApertura(): bool
    {
        return in_array($this, [self::documento, self::insoluto], true);
    }
}
