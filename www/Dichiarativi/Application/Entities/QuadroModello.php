<?php

namespace Dichiarativi\Application\Entities;

use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Denominazione di un quadro di un modello di dichiarazione per anno del
 * modello, dai titoli delle tabelle delle specifiche tecniche.
 */
class QuadroModello extends BaseEntity
{

    protected int $id;
    protected ModelloDichiarativo $modello;
    protected int $anno;
    protected string $quadro;
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
