<?php

namespace Anagrafica\Application\Entities;

use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Interfaces\Storicizzabile;
use Anagrafica\Application\Traits\StoricizzabileTrait;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Indirizzo storicizzato di un soggetto. Per ciascun tipo (residenza,
 * domicilio fiscale, sede legale, ...) può esserci una sola versione
 * vigente a una certa data.
 */
class Indirizzo extends BaseEntity implements Storicizzabile
{

    use StoricizzabileTrait;

    protected int $id;
    protected Soggetto $soggetto;
    protected TipoIndirizzo $tipoIndirizzo;
    protected SismaDate $validoDal;
    protected ?SismaDate $validoAl = null;
    protected ?string $presso = null;
    protected string $indirizzo;
    protected ?string $numeroCivico = null;
    protected ?string $frazione = null;
    protected ?string $cap = null;
    protected ?Comune $comune = null;
    protected ?string $localitaEstera = null;
    protected Nazione $nazione;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->validoDal = new SismaDate();
    }

    public function getRiga1(): string
    {
        return trim($this->indirizzo . ($this->numeroCivico !== null ? ', ' . $this->numeroCivico : ''));
    }

    public function getRiga2(): string
    {
        if ($this->comune !== null) {
            $localita = trim(($this->cap ?? '') . ' ' . ($this->frazione !== null ? $this->frazione . ' - ' : '') . $this->comune->getEtichetta());
        } else {
            $localita = trim(($this->cap ?? '') . ' ' . ($this->localitaEstera ?? ''));
        }
        return $this->nazione->isItalia() ? $localita : trim($localita . ' - ' . $this->nazione->denominazione, ' -');
    }
}
