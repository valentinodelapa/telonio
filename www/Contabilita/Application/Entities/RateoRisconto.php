<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Application\Entities\Studio;

/**
 * Costo o ricavo a cavallo dell'esercizio (art. 2424-bis, c. 6, c.c.),
 * per le scritture di assestamento: importo dell'intero periodo di
 * competenza sul conto economico. Se è già registrato nell'esercizio la
 * parte di competenza degli esercizi successivi è un risconto (attivo per
 * i costi, passivo per i ricavi); se si registra nell'esercizio successivo
 * la parte già maturata è un rateo (passivo per i costi, attivo per i
 * ricavi). Le quote si calcolano a giorni in Services\RateiRisconti.
 */
class RateoRisconto extends BaseEntity
{

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    /** esercizio (anno di chiusura) a cui si riferisce l'assestamento */
    protected int $anno;
    protected Conto $conto;
    protected string $descrizione;
    protected float $importo = 0.0;
    protected SismaDate $competenzaDal;
    protected SismaDate $competenzaAl;
    protected bool $registratoNellEsercizio = true;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
