<?php

namespace Anagrafica\Application\Entities;

use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Anagrafica\Application\Interfaces\Storicizzabile;
use Anagrafica\Application\Traits\StoricizzabileTrait;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Possesso storicizzato di un immobile da parte di un soggetto: titolo,
 * quota e utilizzo nel periodo (es. acquisto, successione, cessione di
 * quote, passaggio da abitazione principale a locato).
 */
class Possesso extends BaseEntity implements Storicizzabile
{

    use StoricizzabileTrait;

    protected int $id;
    protected Immobile $immobile;
    protected Soggetto $soggetto;
    protected TitoloPossesso $titolo;
    protected float $quota = 100.0;
    protected SismaDate $validoDal;
    protected ?SismaDate $validoAl = null;
    protected ?UtilizzoImmobile $utilizzo = null;
    protected ?string $note = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->validoDal = new SismaDate();
    }
}
