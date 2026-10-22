<?php

namespace Successioni\Application\Enumerations;

/**
 * Passività e oneri deducibili del quadro ED (tabella CodiciPassivita delle
 * specifiche SUC13; artt. 20-24 TUS).
 */
enum TipoPassivita: string
{

    case speseMediche = '1';
    case speseFunerarie = '2';
    case mutui = '3';
    case debitiBancari = '4';
    case debitiImpresa = '5';
    case debitiCambiari = '6';
    case debitiEnti = '7';
    case lavoroSubordinato = '8';
    case debitiTributari = '9';
    case assegniMantenimento = '10';
    case legatoOnere = '11';
    case altre = '12';

    public function getLabel(): string
    {
        return match ($this) {
            self::speseMediche => 'Spese mediche e chirurgiche degli ultimi sei mesi',
            self::speseFunerarie => 'Spese funerarie (fino a 1.033 euro)',
            self::mutui => 'Mutui e contratti per l\'acquisto di immobili',
            self::debitiBancari => 'Debiti verso aziende o istituti di credito (saldi passivi di conto corrente)',
            self::debitiImpresa => 'Debiti inerenti l\'esercizio di imprese',
            self::debitiCambiari => 'Debiti cambiari',
            self::debitiEnti => 'Debiti verso lo Stato, enti territoriali ed enti previdenziali',
            self::lavoroSubordinato => 'Rapporto di lavoro subordinato (TFR, trattamenti integrativi)',
            self::debitiTributari => 'Debiti tributari',
            self::assegniMantenimento => 'Assegni di mantenimento al coniuge divorziato',
            self::legatoOnere => 'Legato o altro onere su una quota ereditaria o sul legato',
            self::altre => 'Altre passività',
        };
    }

    public const MASSIMO_SPESE_FUNERARIE = 1033;

    /**
     * La passività va collegata al cespite cui si riferisce (mutui, debiti
     * bancari e d'impresa).
     */
    public function richiedeCespite(): bool
    {
        return in_array($this, [self::mutui, self::debitiBancari, self::debitiImpresa], true);
    }

    /**
     * Il legato o onere non ha la quota di possesso.
     */
    public function hasQuota(): bool
    {
        return $this !== self::legatoOnere;
    }
}
