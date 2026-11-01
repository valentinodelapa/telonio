<?php

namespace Contabilita\Application\Enumerations;

/**
 * Codice di carica del dichiarante nelle comunicazioni e dichiarazioni IVA
 * (tabella delle istruzioni del modello IVA annuale; la comunicazione
 * delle liquidazioni periodiche non ammette il codice 10).
 */
enum CodiceCaricaIva: string
{

    case rappresentanteLegale = '1';
    case rappresentanteIncapace = '2';
    case curatoreFallimentare = '3';
    case commissarioLiquidatore = '4';
    case custodeGiudiziario = '5';
    case rappresentanteFiscale = '6';
    case erede = '7';
    case liquidatore = '8';
    case soggettoSubentrante = '9';
    case tutore = '11';
    case liquidatoreDittaIndividuale = '12';
    case amministratoreCondominio = '13';
    case pubblicaAmministrazione = '14';
    case commissarioPubblicaAmministrazione = '15';

    public function getLabel(): string
    {
        return $this->value . ' - ' . match ($this) {
            self::rappresentanteLegale => 'Rappresentante legale, negoziale o di fatto, socio amministratore',
            self::rappresentanteIncapace => 'Rappresentante di minore, inabilitato o interdetto, amministratore di sostegno, curatore dell\'eredità giacente',
            self::curatoreFallimentare => 'Curatore fallimentare o della liquidazione giudiziale',
            self::commissarioLiquidatore => 'Commissario liquidatore (liquidazione coatta amministrativa, amministrazione straordinaria)',
            self::custodeGiudiziario => 'Custode o amministratore giudiziario, commissario giudiziale',
            self::rappresentanteFiscale => 'Rappresentante fiscale di soggetto non residente',
            self::erede => 'Erede',
            self::liquidatore => 'Liquidatore (liquidazione volontaria)',
            self::soggettoSubentrante => 'Soggetto subentrante per operazioni straordinarie (cessionario d\'azienda, beneficiaria, incorporante, ...)',
            self::tutore => 'Soggetto esercente l\'attività tutoria per funzione istituzionale',
            self::liquidatoreDittaIndividuale => 'Liquidatore di ditta individuale (periodo ante liquidazione)',
            self::amministratoreCondominio => 'Amministratore di condominio',
            self::pubblicaAmministrazione => 'Soggetto che sottoscrive per conto di una pubblica amministrazione',
            self::commissarioPubblicaAmministrazione => 'Commissario liquidatore di una pubblica amministrazione',
        };
    }
}
