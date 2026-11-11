<?php

namespace Contabilita\Application\Enumerations;

/**
 * Modo in cui l'abbinamento chiude la partita: con denaro (incasso o
 * pagamento), con la ritenuta d'acconto (subita dal professionista o
 * operata sul fornitore), con la compensazione tra crediti e debiti
 * verso la stessa controparte, con una nota di credito, con un acconto
 * ricevuto o pagato prima, o senza incasso (abbuono, sconto, perdita su
 * crediti).
 */
enum TipoChiusura: string
{

    case denaro = 'DEN';
    case ritenuta = 'RIT';
    case compensazione = 'CMP';
    case notaCredito = 'NC';
    case acconto = 'ACC';
    case abbuono = 'ABB';

    public function getLabel(): string
    {
        return match ($this) {
            self::denaro => 'Incasso o pagamento',
            self::ritenuta => 'Ritenuta d\'acconto',
            self::compensazione => 'Compensazione',
            self::notaCredito => 'Nota di credito',
            self::acconto => 'Acconto',
            self::abbuono => 'Abbuono, sconto o perdita',
        };
    }

    public function getEtichettaBreve(): string
    {
        return match ($this) {
            self::denaro => 'Incasso/pagamento',
            self::ritenuta => 'Ritenuta',
            self::compensazione => 'Compensazione',
            self::notaCredito => 'Nota di credito',
            self::acconto => 'Acconto',
            self::abbuono => 'Abbuono/perdita',
        };
    }

    /**
     * Per cassa (quadri RG ed RE) la chiusura vale come incasso o
     * pagamento del documento: la ritenuta è parte del compenso (art. 54
     * Tuir), la compensazione estingue il credito come un pagamento; non
     * valgono gli abbuoni e le perdite (il ricavo non incassato non
     * concorre).
     */
    public function isIncasso(): bool
    {
        return $this !== self::abbuono;
    }
}
