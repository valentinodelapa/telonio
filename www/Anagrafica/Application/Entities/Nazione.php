<?php

namespace Anagrafica\Application\Entities;

use Anagrafica\Application\Interfaces\Storicizzabile;
use Anagrafica\Application\Traits\StoricizzabileTrait;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Tabella di riferimento degli stati e territori esteri (Italia compresa),
 * comune a tutti gli studi, importata dalla tabella ANPR degli stati esteri
 * (comando tabelle-riferimento).
 *
 * Il codice catastale (Z + tre cifre) è quello usato nel codice fiscale dei
 * nati all'estero; il codice ISO alpha-2 serve per partita IVA comunitaria
 * e fattura elettronica.
 */
class Nazione extends ReferencedEntity implements Storicizzabile
{

    use StoricizzabileTrait;

    public const CODICE_ITALIA = 'IT';

    protected int $id;
    protected int $idAnpr;
    protected ?string $codiceIso = null;
    protected ?string $codiceIso3 = null;
    protected ?string $codiceCatastale = null;
    protected string $denominazione;
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

    public function isItalia(): bool
    {
        return $this->codiceIso === self::CODICE_ITALIA;
    }
}
