<?php

namespace Anagrafica\Application\Enumerations;

/**
 * Forme giuridiche dei soggetti diversi dalle persone fisiche.
 *
 * Le persone fisiche con partita IVA (ditte individuali, professionisti)
 * restano di tipo persona fisica e non hanno natura giuridica.
 */
enum NaturaGiuridica: string
{

    case societaSemplice = 'SS';
    case societaNomeCollettivo = 'SNC';
    case societaAccomanditaSemplice = 'SAS';
    case studioAssociato = 'STA';
    case societaResponsabilitaLimitata = 'SRL';
    case societaResponsabilitaLimitataSemplificata = 'SRLS';
    case societaPerAzioni = 'SPA';
    case societaAccomanditaPerAzioni = 'SAPA';
    case societaCooperativa = 'COOP';
    case consorzio = 'CONS';
    case associazione = 'ASS';
    case fondazione = 'FOND';
    case comitato = 'COM';
    case condominio = 'COND';
    case enteEcclesiastico = 'ECCL';
    case entePubblico = 'PUBB';

    public function getLabel(): string
    {
        return match ($this) {
            self::societaSemplice => 'Società semplice',
            self::societaNomeCollettivo => 'Società in nome collettivo',
            self::societaAccomanditaSemplice => 'Società in accomandita semplice',
            self::studioAssociato => 'Studio associato / associazione professionale',
            self::societaResponsabilitaLimitata => 'Società a responsabilità limitata',
            self::societaResponsabilitaLimitataSemplificata => 'S.r.l. semplificata',
            self::societaPerAzioni => 'Società per azioni',
            self::societaAccomanditaPerAzioni => 'Società in accomandita per azioni',
            self::societaCooperativa => 'Società cooperativa',
            self::consorzio => 'Consorzio',
            self::associazione => 'Associazione',
            self::fondazione => 'Fondazione',
            self::comitato => 'Comitato',
            self::condominio => 'Condominio',
            self::enteEcclesiastico => 'Ente ecclesiastico',
            self::entePubblico => 'Ente pubblico',
        };
    }

    public function getTipoSoggetto(): TipoSoggetto
    {
        return match ($this) {
            self::societaSemplice,
            self::societaNomeCollettivo,
            self::societaAccomanditaSemplice,
            self::studioAssociato => TipoSoggetto::societaPersone,
            self::societaResponsabilitaLimitata,
            self::societaResponsabilitaLimitataSemplificata,
            self::societaPerAzioni,
            self::societaAccomanditaPerAzioni,
            self::societaCooperativa,
            self::consorzio => TipoSoggetto::societaCapitali,
            default => TipoSoggetto::ente,
        };
    }
}
