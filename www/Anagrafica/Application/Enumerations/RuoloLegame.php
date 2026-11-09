<?php

namespace Anagrafica\Application\Enumerations;

/**
 * Ruolo che un soggetto collegato ricopre presso un altro soggetto
 * (cariche sociali, partecipazioni, rappresentanza) o legame familiare del
 * soggetto collegato con il soggetto (coniuge, figlio, ascendente, altro
 * familiare), per il prospetto dei familiari a carico del modello Redditi.
 */
enum RuoloLegame: string
{

    case legaleRappresentante = 'LR';
    case amministratoreUnico = 'AU';
    case presidenteCda = 'PCA';
    case consigliere = 'CON';
    case socio = 'SOC';
    case socioAccomandatario = 'SAC';
    case socioAccomandante = 'SAN';
    case sindaco = 'SIN';
    case revisore = 'REV';
    case liquidatore = 'LIQ';
    case procuratore = 'PRO';
    case tutoreCuratore = 'TUT';
    case erede = 'ERE';
    case collaboratoreFamiliare = 'CFA';
    case coniuge = 'CNG';
    case figlio = 'FIG';
    case ascendente = 'ASC';
    case altroFamiliare = 'AFM';

    public function getLabel(): string
    {
        return match ($this) {
            self::legaleRappresentante => 'Legale rappresentante',
            self::amministratoreUnico => 'Amministratore unico',
            self::presidenteCda => 'Presidente del CdA',
            self::consigliere => 'Consigliere di amministrazione',
            self::socio => 'Socio',
            self::socioAccomandatario => 'Socio accomandatario',
            self::socioAccomandante => 'Socio accomandante',
            self::sindaco => 'Sindaco',
            self::revisore => 'Revisore legale',
            self::liquidatore => 'Liquidatore',
            self::procuratore => 'Procuratore',
            self::tutoreCuratore => 'Tutore / curatore',
            self::erede => 'Erede',
            self::collaboratoreFamiliare => 'Collaboratore familiare',
            self::coniuge => 'Coniuge o unito civilmente',
            self::figlio => 'Figlio',
            self::ascendente => 'Genitore o altro ascendente',
            self::altroFamiliare => 'Altro familiare (fratello, suocero, genero, nipote)',
        };
    }

    public function prevedeQuota(): bool
    {
        return in_array($this, [self::socio, self::socioAccomandatario, self::socioAccomandante, self::erede, self::collaboratoreFamiliare], true);
    }

    /**
     * Legame familiare (coniuge, figlio, ascendente, altro familiare), non
     * una carica o una partecipazione.
     */
    public function isFamiliare(): bool
    {
        return in_array($this, [self::coniuge, self::figlio, self::ascendente, self::altroFamiliare], true);
    }

    /**
     * Familiari che possono essere a carico pur non convivendo (coniuge e
     * figli); ascendenti e altri familiari solo se conviventi.
     */
    public function richiedeConvivenza(): bool
    {
        return in_array($this, [self::ascendente, self::altroFamiliare], true);
    }
}
