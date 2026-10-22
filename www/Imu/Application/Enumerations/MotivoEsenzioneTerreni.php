<?php

namespace Imu\Application\Enumerations;

/**
 * Esenzione dei terreni agricoli per ubicazione (art. 1, comma 758, L.
 * 160/2019).
 */
enum MotivoEsenzioneTerreni: string
{

    case montano = 'M';
    case isolaMinore = 'I';

    public function getLabel(): string
    {
        return match ($this) {
            self::montano => 'Terreno in area montana o di collina (circolare n. 9/1993)',
            self::isolaMinore => 'Terreno in un comune delle isole minori (L. 448/2001, all. A)',
        };
    }

    public function getRiferimento(): string
    {
        return match ($this) {
            self::montano => 'comma 758, lett. d), L. 160/2019',
            self::isolaMinore => 'comma 758, lett. b), L. 160/2019',
        };
    }
}
