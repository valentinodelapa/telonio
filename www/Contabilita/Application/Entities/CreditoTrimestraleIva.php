<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use Telonio\Application\Entities\Studio;

/**
 * Credito IVA del trimestre chiesto a rimborso o in compensazione con il
 * modello IVA TR presentato: registrato dal modulo Dichiarativi, vale anche
 * se la liquidazione che chiude il trimestre non è ancora elaborata o viene
 * eliminata e rielaborata. Con il gruppo IVA il soggetto è la controllante.
 */
class CreditoTrimestraleIva extends BaseEntity
{

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected ?GruppoIva $gruppoIva = null;
    protected int $anno;
    protected int $trimestre;
    protected float $importo = 0.0;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
