<?php

namespace Contabilita\Application\Enumerations;

/**
 * Natura delle operazioni senza imposta, con i codici delle specifiche
 * tecniche della fattura elettronica (allegato A al provvedimento AdE
 * 30 aprile 2018 n. 89757 e successive modifiche, versione 1.9). I codici
 * generici N2, N3 e N6, non più ammessi dal 2021, si leggono nei file
 * importati come N2.2, N3.6 e N6.9.
 */
enum NaturaIva: string
{

    case escluse = 'N1';
    case nonSoggetteTerritorialita = 'N2.1';
    case nonSoggetteAltri = 'N2.2';
    case esportazioni = 'N3.1';
    case cessioniIntracomunitarie = 'N3.2';
    case sanMarino = 'N3.3';
    case assimilateEsportazioni = 'N3.4';
    case dichiarazioniIntento = 'N3.5';
    case nonImponibiliAltre = 'N3.6';
    case esenti = 'N4';
    case regimeMargine = 'N5';
    case inversioneRottami = 'N6.1';
    case inversioneOro = 'N6.2';
    case inversioneSubappaltoEdile = 'N6.3';
    case inversioneFabbricati = 'N6.4';
    case inversioneCellulari = 'N6.5';
    case inversioneElettronici = 'N6.6';
    case inversioneEdileConnessi = 'N6.7';
    case inversioneEnergetico = 'N6.8';
    case inversioneAltri = 'N6.9';
    case assoltaAltroStato = 'N7';

    public function getLabel(): string
    {
        return match ($this) {
            self::escluse => 'Escluse ex art. 15 D.P.R. 633/1972',
            self::nonSoggetteTerritorialita => 'Non soggette ad IVA ai sensi degli artt. da 7 a 7-septies D.P.R. 633/1972',
            self::nonSoggetteAltri => 'Non soggette - altri casi',
            self::esportazioni => 'Non imponibili - esportazioni',
            self::cessioniIntracomunitarie => 'Non imponibili - cessioni intracomunitarie',
            self::sanMarino => 'Non imponibili - cessioni verso San Marino',
            self::assimilateEsportazioni => 'Non imponibili - operazioni assimilate alle cessioni all\'esportazione',
            self::dichiarazioniIntento => 'Non imponibili - a seguito di dichiarazioni d\'intento',
            self::nonImponibiliAltre => 'Non imponibili - altre operazioni che non concorrono alla formazione del plafond',
            self::esenti => 'Esenti',
            self::regimeMargine => 'Regime del margine / IVA non esposta in fattura',
            self::inversioneRottami => 'Inversione contabile - cessione di rottami e altri materiali di recupero',
            self::inversioneOro => 'Inversione contabile - cessione di oro e argento',
            self::inversioneSubappaltoEdile => 'Inversione contabile - subappalto nel settore edile',
            self::inversioneFabbricati => 'Inversione contabile - cessione di fabbricati',
            self::inversioneCellulari => 'Inversione contabile - cessione di telefoni cellulari',
            self::inversioneElettronici => 'Inversione contabile - cessione di prodotti elettronici',
            self::inversioneEdileConnessi => 'Inversione contabile - prestazioni comparto edile e settori connessi',
            self::inversioneEnergetico => 'Inversione contabile - operazioni settore energetico',
            self::inversioneAltri => 'Inversione contabile - altri casi',
            self::assoltaAltroStato => 'IVA assolta in altro stato UE',
        };
    }

    /**
     * Operazioni in inversione contabile: l'imposta è dovuta dal
     * cessionario o committente, che integra la fattura.
     */
    public function isInversioneContabile(): bool
    {
        return str_starts_with($this->value, 'N6');
    }

    /**
     * Codice letto da un file, con i codici generici in uso fino al 2020.
     */
    public static function daFile(string $codice): ?self
    {
        $codice = strtoupper(trim($codice));
        return self::tryFrom(match ($codice) {
            'N2' => 'N2.2',
            'N3' => 'N3.6',
            'N6' => 'N6.9',
            default => $codice,
        });
    }
}
