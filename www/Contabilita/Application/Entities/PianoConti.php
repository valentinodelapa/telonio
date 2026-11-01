<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;
use Telonio\Application\Entities\Studio;

/**
 * Piano dei conti: modello della suite (senza studio, id sotto
 * PRIMO_ID_STUDIO, aggiornato da "sisma tabelle-contabilita"), modello
 * dello studio o piano dell'azienda (con il soggetto), copiato da un
 * modello all'apertura della contabilità e poi personalizzabile.
 */
class PianoConti extends ReferencedEntity
{

    public const PRIMO_ID_STUDIO = 1000;

    protected int $id;
    protected ?Studio $studio = null;
    protected ?Soggetto $soggetto = null;
    protected string $denominazione;
    protected ?string $descrizione = null;
    protected ?PianoConti $pianoOrigine = null;
    protected SismaDateTime $dataCreazione;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataCreazione = new SismaDateTime();
    }

    public function isModello(): bool
    {
        return $this->soggetto === null;
    }

    public function isDellaSuite(): bool
    {
        return ($this->studio === null) && ($this->soggetto === null);
    }
}
