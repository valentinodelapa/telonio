<?php

namespace Imu\Application\Enumerations;

/**
 * Codici di carica del dichiarante (tabella delle istruzioni della
 * dichiarazione IMU).
 */
enum CodiceCarica: string
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
    case tutore = '10';
    case liquidatoreDittaIndividuale = '11';
    case amministratoreCondominio = '12';

    public function getLabel(): string
    {
        return $this->value . ' - ' . match ($this) {
            self::rappresentanteLegale => 'Rappresentante legale, negoziale o di fatto, socio amministratore',
            self::rappresentanteIncapace => 'Rappresentante di minore, inabilitato o interdetto, amministratore di sostegno, curatore dell\'eredità giacente',
            self::curatoreFallimentare => 'Curatore fallimentare',
            self::commissarioLiquidatore => 'Commissario liquidatore',
            self::custodeGiudiziario => 'Custode o amministratore giudiziario, commissario giudiziale',
            self::rappresentanteFiscale => 'Rappresentante fiscale di soggetto non residente',
            self::erede => 'Erede',
            self::liquidatore => 'Liquidatore (liquidazione volontaria)',
            self::soggettoSubentrante => 'Soggetto subentrante per operazioni straordinarie (incorporante, beneficiaria, ...)',
            self::tutore => 'Soggetto esercente l\'attività tutoria per funzione istituzionale',
            self::liquidatoreDittaIndividuale => 'Liquidatore di ditta individuale (periodo ante liquidazione)',
            self::amministratoreCondominio => 'Amministratore di condominio',
        };
    }
}
