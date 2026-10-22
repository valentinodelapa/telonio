<?php

namespace Imu\Application\Entities;

use Imu\Application\Enumerations\MotivoEsenzioneTerreni;
use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Comune nel cui territorio i terreni agricoli sono esenti per ubicazione
 * (art. 1, comma 758, lett. b e d, L. 160/2019). Parziale: l'esenzione vale
 * solo per una parte del territorio (comuni "PD" della circolare n. 9/1993,
 * comuni solo in parte insulari), da verificare per il singolo terreno.
 * Tabella di riferimento comune a tutti gli studi.
 */
class ComuneEsenzioneTerreni extends BaseEntity
{

    protected int $id;
    protected string $codiceCatastale;
    protected MotivoEsenzioneTerreni $motivo;
    protected bool $parziale = false;
    protected string $denominazione;
    protected ?string $provincia = null;
    protected string $fonte;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
