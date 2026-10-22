<?php

namespace Deleghe\Application\Entities;

use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Tasso ufficiale di riferimento (operazioni di rifinanziamento principali
 * BCE) in vigore da una data, per le sanzioni civili INPS e INAIL.
 */
class TassoRiferimento extends BaseEntity
{

    protected int $id;
    protected SismaDate $validoDal;
    protected float $tasso;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
