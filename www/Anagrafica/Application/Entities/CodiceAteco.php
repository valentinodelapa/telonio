<?php

namespace Anagrafica\Application\Entities;

use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Voce di una classificazione ATECO (ISTAT), comune a tutti gli studi e
 * importata con il comando tabelle-riferimento.
 *
 * Livelli: 1 sezione, 2 divisione, 3 gruppo, 4 classe, 5 categoria,
 * 6 sottocategoria; solo le sottocategorie si attribuiscono ai soggetti.
 * Il padre è indicato per codice (non per id) perché la tabella è generata
 * da script SQL indipendenti dagli id del database.
 */
class CodiceAteco extends ReferencedEntity
{

    public const LIVELLO_SOTTOCATEGORIA = 6;

    protected int $id;
    protected ClassificazioneAteco $classificazione;
    protected string $codice;
    protected int $livello;
    protected ?string $codicePadre = null;
    protected string $titolo;
    protected ?string $codiceRaccordo = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function isSottocategoria(): bool
    {
        return $this->livello === self::LIVELLO_SOTTOCATEGORIA;
    }

    public function getEtichetta(): string
    {
        return $this->codice . ' - ' . $this->titolo;
    }
}
