<?php

namespace Anagrafica\Application\Entities;

use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Pertinenza (box, cantina, ...) con rendita autonoma locata insieme
 * all'immobile del contratto: nel quadro RB ha un proprio rigo con la quota
 * del canone proporzionale alla rendita (per le locazioni brevi il canone
 * va tutto all'abitazione e la pertinenza ha l'utilizzo 9).
 */
class PertinenzaLocata extends BaseEntity
{

    protected int $id;
    protected ContrattoLocazione $contrattoLocazione;
    protected Immobile $immobile;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
