<?php

namespace Successioni\Application\Entities;

use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Comune catastale delle province di Trento e Bolzano (catasto tavolare):
 * tabella delle specifiche tecniche SUC13, comune a tutti gli studi. Il
 * codice si ricava dal nome indicato sull'immobile in Anagrafica.
 */
class ComuneCatastaleTavolare extends BaseEntity
{

    protected int $id;
    protected string $codice;
    protected string $denominazione;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
