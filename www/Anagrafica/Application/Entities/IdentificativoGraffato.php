<?php

namespace Anagrafica\Application\Entities;

use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Ulteriore identificativo catastale di un'unità immobiliare graffata:
 * più particelle (o subalterni) accatastate insieme come un'unica unità
 * con un solo classamento. L'immobile resta identificato dalla prima
 * particella; le altre sono elencate qui (atti, successioni, volture).
 */
class IdentificativoGraffato extends BaseEntity
{

    protected int $id;
    protected Immobile $immobile;
    protected ?string $sezione = null;
    protected string $foglio;
    protected string $particella;
    protected ?string $subalterno = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function getIdentificativo(): string
    {
        return trim(($this->sezione !== null ? 'Sez. ' . $this->sezione . ' ' : '')
                . 'Fg. ' . $this->foglio . ' Part. ' . $this->particella
                . ($this->subalterno !== null ? ' Sub. ' . $this->subalterno : ''));
    }
}
