<?php

namespace GestioneStudio\Application\Entities;

use Telonio\Application\Entities\Studio;
use GestioneStudio\Application\Enumerations\AmbitoAdempimento;
use GestioneStudio\Application\Enumerations\CategoriaAdempimento;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Adempimento del calendario con i suoi termini (TermineAdempimento).
 *
 * Senza studio appartiene al calendario fiscale comune a tutti gli studi,
 * importato dalle sorgenti del modulo con "sisma calendario-fiscale" e in
 * sola lettura; con lo studio è un adempimento proprio dello studio (es.
 * una scadenza interna o un termine non presente nel calendario).
 *
 * La fonte normativa può cambiare da una data (es. dal 2027 il Testo unico
 * IVA e il testo unico adempimenti e accertamento): le scadenze da
 * quella data citano la nuova fonte.
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
    protected ?string $nuovaFonte = null;
    protected ?SismaDate $nuovaFonteDal = null;
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

    /**
     * Fonte del termine alla data (di default oggi).
     */
    public function getFonte(?\DateTimeInterface $data = null): ?string
    {
        if (($this->nuovaFonte !== null) && ($this->nuovaFonteDal !== null)
                && (($data ?? new \DateTimeImmutable())->format('Y-m-d') >= $this->nuovaFonteDal->format('Y-m-d'))) {
            return $this->nuovaFonte;
        }
        return $this->fonte;
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
