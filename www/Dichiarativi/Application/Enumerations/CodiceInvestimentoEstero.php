<?php

namespace Dichiarativi\Application\Enumerations;

/**
 * Codice di individuazione del bene del quadro RW (colonna 3), dalla
 * "Tabella codici investimenti all'estero e attività estere di natura
 * finanziaria" in appendice alle istruzioni del fascicolo 2 del modello
 * Redditi PF 2026. Determina l'imposta: IVAFE in misura fissa per i conti
 * correnti e libretti (1) e proporzionale per gli altri prodotti
 * finanziari (da 2 a 14 e 20), IVIE per gli immobili (15 e, per
 * l'abitazione principale di categoria A/1, A/8 o A/9, 19), imposta sulle
 * cripto-attività (21); per i beni da 16 a 18 il solo monitoraggio.
 */
enum CodiceInvestimentoEstero: int
{

    case contiCorrenti = 1;
    case partecipazioni = 2;
    case obbligazioni = 3;
    case titoliNonRappresentativi = 4;
    case valuteEstere = 5;
    case titoliPubbliciItaliani = 6;
    case contrattiFinanziari = 7;
    case polizze = 8;
    case derivati = 9;
    case metalliPreziosi = 10;
    case trustFondazioni = 11;
    case previdenzaEstera = 12;
    case altriStrumentiFinanziari = 13;
    case altreAttivitaFinanziarie = 14;
    case immobili = 15;
    case beniMobiliRegistrati = 16;
    case operedArte = 17;
    case altriBeniPatrimoniali = 18;
    case abitazionePrincipale = 19;
    case depositoTitoli = 20;
    case criptoAttivita = 21;

    public function getLabel(): string
    {
        return $this->value . ' - ' . match ($this) {
            self::contiCorrenti => 'Conti correnti e depositi esteri',
            self::partecipazioni => 'Partecipazioni al capitale o al patrimonio di società non residenti',
            self::obbligazioni => 'Obbligazioni estere e titoli similari',
            self::titoliNonRappresentativi => 'Titoli non rappresentativi di merce e certificati di massa emessi da non residenti',
            self::valuteEstere => 'Valute estere da depositi e conti correnti',
            self::titoliPubbliciItaliani => 'Titoli pubblici italiani emessi all\'estero',
            self::contrattiFinanziari => 'Contratti di natura finanziaria stipulati con controparti non residenti',
            self::polizze => 'Polizze di assicurazione sulla vita e di capitalizzazione',
            self::derivati => 'Contratti derivati e altri rapporti finanziari conclusi fuori dal territorio dello Stato',
            self::metalliPreziosi => 'Metalli preziosi allo stato grezzo o monetato detenuti all\'estero',
            self::trustFondazioni => 'Partecipazioni al patrimonio di trust, fondazioni o altre entità giuridiche diverse dalle società',
            self::previdenzaEstera => 'Forme di previdenza gestite da soggetti esteri',
            self::altriStrumentiFinanziari => 'Altri strumenti finanziari anche di natura non partecipativa',
            self::altreAttivitaFinanziarie => 'Altre attività estere di natura finanziaria',
            self::immobili => 'Beni immobili',
            self::beniMobiliRegistrati => 'Beni mobili registrati (es. yacht e auto di lusso)',
            self::operedArte => 'Opere d\'arte e gioielli',
            self::altriBeniPatrimoniali => 'Altri beni patrimoniali',
            self::abitazionePrincipale => 'Immobile estero adibito ad abitazione principale',
            self::depositoTitoli => 'Conto deposito titoli all\'estero',
            self::criptoAttivita => 'Cripto-attività',
        };
    }

    /**
     * IVAFE proporzionale (0,20 per cento, 0,40 nei Paesi a fiscalità
     * privilegiata).
     */
    public function isIvafeProporzionale(): bool
    {
        return (($this->value >= 2) && ($this->value <= 14)) || ($this === self::depositoTitoli);
    }

    public function isIvie(): bool
    {
        return in_array($this, [self::immobili, self::abitazionePrincipale], true);
    }

    /**
     * Beni senza imposta: solo monitoraggio (colonna 16).
     */
    public function isSoloMonitoraggio(): bool
    {
        return in_array($this, [self::beniMobiliRegistrati, self::operedArte, self::altriBeniPatrimoniali], true);
    }

    /**
     * Prodotto finanziario (colonna 14 del quadro reddituale).
     */
    public function isFinanziario(): bool
    {
        return ($this->value <= 14) || in_array($this, [self::depositoTitoli, self::criptoAttivita], true);
    }
}
