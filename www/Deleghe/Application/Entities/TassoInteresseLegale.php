<?php

namespace Deleghe\Application\Entities;

use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Saggio degli interessi legali (art. 1284 c.c.) in vigore da una data,
 * fissato con decreto del Ministro dell'economia e delle finanze.
 */
class TassoInteresseLegale extends BaseEntity
{

    protected int $id;
    protected SismaDate $validoDal;
    protected float $tasso;
    protected string $provvedimento;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
