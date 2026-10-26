<?php

namespace GestioneStudio\Application\Entities;

use Telonio\Application\Entities\Studio;
use GestioneStudio\Application\Enumerations\AmbitoAdempimento;
use GestioneStudio\Application\Enumerations\CategoriaAdempimento;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Adempimento del calendario con i suoi termini (TermineAdempimento).
 *
 * Senza studio appartiene al calendario fiscale comune a tutti gli studi,
 * importato dalle sorgenti del modulo con "sisma calendario-fiscale" e in
 * sola lettura; con lo studio è un adempimento proprio dello studio (es.
 * una scadenza interna o un termine non presente nel calendario).
 */
class Adempimento extends ReferencedEntity
{

    public const PRIMO_ID_STUDIO = 1000;

    protected int $id;
    protected ?Studio $studio = null;
    protected string $codice;
    protected string $denominazione;
    protected CategoriaAdempimento $categoria = CategoriaAdempimento::altro;
    protected AmbitoAdempimento $ambito = AmbitoAdempimento::tutti;
    protected ?string $fonte = null;
    protected bool $slittamentoFestivi = true;
    protected bool $prorogaAgosto = true;
    protected bool $attivo = true;
    protected ?string $note = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function isDelCalendario(): bool
    {
        return $this->studio === null;
    }

    public function isVisibile(Studio $studio): bool
    {
        return ($this->studio === null) || ($this->studio->id === $studio->id);
    }
}
