<?php

namespace Anagrafica\Application\Entities;

use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Nuovo canone annuo dalla data (aggiornamento ISTAT, rinegoziazione):
 * fino alla prima variazione vale il canone del contratto. Il quadro RB
 * riporta il canone dell'anno in proporzione ai giorni di ciascun importo.
 */
class VariazioneCanone extends BaseEntity
{

    protected int $id;
    protected ContrattoLocazione $contrattoLocazione;
    protected SismaDate $validoDal;
    protected float $canoneAnnuo;
    protected bool $rinegoziazione = false;
    protected ?string $note = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->validoDal = new SismaDate();
    }
}
