<?php

namespace Anagrafica\Application\Entities;

use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Interfaces\Storicizzabile;
use Anagrafica\Application\Traits\StoricizzabileTrait;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Versione storicizzata dei dati di classamento di un immobile: una
 * variazione catastale (nuova rendita, cambio di categoria) è una nuova
 * versione con la decorrenza della variazione.
 *
 * Fabbricati: categoria, classe, consistenza, superficie, rendita.
 * Terreni: qualità di coltura, classe, superficie, redditi dominicale e
 * agrario.
 */
class DatiCatastali extends BaseEntity implements Storicizzabile
{

    use StoricizzabileTrait;

    protected int $id;
    protected Immobile $immobile;
    protected SismaDate $validoDal;
    protected ?SismaDate $validoAl = null;
    protected ?CategoriaCatastale $categoria = null;
    protected ?string $classe = null;
    protected ?float $consistenza = null;
    protected ?float $superficie = null;
    protected ?float $rendita = null;
    protected ?string $qualitaColtura = null;
    protected ?float $redditoDominicale = null;
    protected ?float $redditoAgrario = null;

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
