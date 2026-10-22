<?php

namespace Deleghe\Application\Enumerations;

/**
 * Codici identificativi del secondo codice fiscale indicato nel modello F24
 * (coobbligato, erede, genitore, ...).
 *
 * Tabella breve e stabile, pubblicata dall'Agenzia delle Entrate solo in
 * PDF ("Tabella dei codici identificativi del secondo codice fiscale",
 * aggiornamento dell'08/10/2025):
 * https://www.agenziaentrate.gov.it/portale/strumenti/codici-attivita-e-tributo/f24-codici-tributo-per-i-versamenti/tabelle-dei-codici-tributo-e-altri-codici-per-il-modello-f24
 * Ai nuovi aggiornamenti della tabella va aggiornato questo enum.
 */
enum CodiceIdentificativo: string
{

    case genitoreTutore = '02';
    case curatoreFallimentare = '03';
    case erede = '07';
    case defunto = '08';
    case committente = '09';
    case stazioneAppaltante = '40';
    case obbligatoSolidale = '50';
    case interventoSostitutivo = '51';
    case aderenteConsolidato = '61';
    case diversoDalFruitoreCredito = '62';
    case controparte = '63';
    case gestoreDepositoFiscale = '64';
    case responsabileInversioneContabileLogistica = '66';
    case assicuratriceEsteraRappresentata = '70';
    case propostoAttoIntroduttivo = '71';
    case rappresentanteFiscale = '72';
    case contribuenteSocieta = '73';
    case risultanteOperazioneStraordinaria = '74';
    case accollante = '80';

    public function getLabel(): string
    {
        return $this->value . ' - ' . match ($this) {
            self::genitoreTutore => 'Genitore / tutore / amministratore di sostegno',
            self::curatoreFallimentare => 'Curatore fallimentare',
            self::erede => 'Erede',
            self::defunto => 'Defunto',
            self::committente => 'Committente',
            self::stazioneAppaltante => 'Stazione appaltante',
            self::obbligatoSolidale => 'Obbligato solidale',
            self::interventoSostitutivo => 'Intervento sostitutivo',
            self::aderenteConsolidato => 'Soggetto aderente al consolidato',
            self::diversoDalFruitoreCredito => 'Soggetto diverso dal fruitore del credito',
            self::controparte => 'Controparte',
            self::gestoreDepositoFiscale => 'Gestore del deposito fiscale o del deposito del destinatario registrato',
            self::responsabileInversioneContabileLogistica => 'Soggetto solidalmente responsabile inversione contabile IVA logistica',
            self::assicuratriceEsteraRappresentata => 'Impresa assicuratrice estera fiscalmente rappresentata',
            self::propostoAttoIntroduttivo => 'Soggetto che ha proposto l\'atto introduttivo del giudizio',
            self::rappresentanteFiscale => 'Rappresentante fiscale',
            self::contribuenteSocieta => 'Contribuente - società',
            self::risultanteOperazioneStraordinaria => 'Soggetto risultante dall\'operazione straordinaria',
            self::accollante => 'Accollante del debito d\'imposta',
        };
    }

    /**
     * Con i servizi telematici dell'Agenzia il conto di addebito deve
     * essere intestato al secondo soggetto.
     */
    public function isAddebitoSulSecondoSoggetto(): bool
    {
        return in_array($this, [self::genitoreTutore, self::curatoreFallimentare, self::erede, self::obbligatoSolidale,
            self::interventoSostitutivo, self::rappresentanteFiscale, self::risultanteOperazioneStraordinaria, self::accollante], true);
    }

    /**
     * Codice ammesso solo nei modelli presentati con i servizi telematici
     * dell'Agenzia (Entratel, Fisconline, F24 web).
     */
    public function isSoloServiziAgenzia(): bool
    {
        return $this === self::accollante;
    }
}
