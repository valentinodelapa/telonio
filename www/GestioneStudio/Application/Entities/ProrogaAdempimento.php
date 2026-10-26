<?php

namespace GestioneStudio\Application\Entities;

use Telonio\Application\Entities\Studio;
use GestioneStudio\Application\Enumerations\CondizioneProroga;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Proroga del termine per un anno di riferimento. Senza studio è una
 * proroga di legge del calendario fiscale (sorgenti del modulo), con lo
 * studio è inserita dallo studio e prevale. Una proroga può valere solo per
 * alcuni soggetti (CondizioneProroga, es. i soggetti ISA).
 */
class ProrogaAdempimento extends BaseEntity
{

    protected int $id;
    protected ?Studio $studio = null;
    protected TermineAdempimento $termineAdempimento;
    protected int $anno;
    protected SismaDate $data;
    protected CondizioneProroga $condizione = CondizioneProroga::tutti;
    protected string $provvedimento;
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
}
