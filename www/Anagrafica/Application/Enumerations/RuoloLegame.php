<?php

namespace Anagrafica\Application\Enumerations;

/**
 * Ruolo che un soggetto collegato ricopre presso un altro soggetto
 * (cariche sociali, partecipazioni, rappresentanza).
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
        };
    }

    public function prevedeQuota(): bool
    {
        return in_array($this, [self::socio, self::socioAccomandatario, self::socioAccomandante, self::erede, self::collaboratoreFamiliare], true);
    }
}
