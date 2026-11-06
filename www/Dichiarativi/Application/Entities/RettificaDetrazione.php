<?php

namespace Dichiarativi\Application\Entities;

use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Rigo del prospetto D delle istruzioni IVA (Appendice, "Rettifiche della
 * detrazione"): importo con segno, più per l'aumento della detrazione e meno
 * per la diminuzione. Il totale va nel rigo VF70 del modulo.
 */
class RettificaDetrazione extends BaseEntity
{

    protected int $id;
    protected Dichiarazione $dichiarazione;
    protected int $modulo = 1;
    protected int $rigo;
    protected float $importo = 0.0;
    protected ?string $nota = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
