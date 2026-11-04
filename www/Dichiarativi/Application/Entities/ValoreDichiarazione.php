<?php

namespace Dichiarativi\Application\Entities;

use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Valore di un campo non posizionale della dichiarazione (codice del campo
 * del modello e numero del modulo), in forma canonica (FormatoCampo). I
 * valori inseriti o corretti a mano sono segnati e la proposta non li
 * sovrascrive.
 */
class ValoreDichiarazione extends BaseEntity
{

    protected int $id;
    protected Dichiarazione $dichiarazione;
    protected int $modulo = 1;
    protected string $codice;
    protected string $valore;
    protected bool $manuale = false;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
