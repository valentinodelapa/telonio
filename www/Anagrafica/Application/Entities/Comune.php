<?php

namespace Anagrafica\Application\Entities;

use Anagrafica\Application\Interfaces\Storicizzabile;
use Anagrafica\Application\Traits\StoricizzabileTrait;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Tabella di riferimento dei comuni italiani, comune a tutti gli studi,
 * importata dall'archivio storico ANPR (comando tabelle-riferimento).
 *
 * Ogni riga è una versione del comune nel periodo di validità: un cambio
 * di denominazione o di provincia genera una nuova riga con lo stesso
 * codice catastale. I riferimenti (luogo di nascita, indirizzi) puntano
 * alla versione in vigore all'epoca, così restano corretti nel tempo.
 */
class Comune extends ReferencedEntity implements Storicizzabile
{

    use StoricizzabileTrait;

    protected int $id;
    protected int $idAnpr;
    protected string $codiceCatastale;
    protected ?string $codiceIstat = null;
    protected string $denominazione;
    protected ?string $siglaProvincia = null;
    protected SismaDate $validoDal;
    protected ?SismaDate $validoAl = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function getEtichetta(): string
    {
        return $this->denominazione . ($this->siglaProvincia !== null ? ' (' . $this->siglaProvincia . ')' : '');
    }

    public function isSoppresso(): bool
    {
        return $this->isVigenteAl(new SismaDate()) === false;
    }
}
