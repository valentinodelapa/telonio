<?php

namespace Deleghe\Application\Entities;

use CommSuite\Application\Entities\Studio;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * File telematico F24 generato per Entratel (record A, M, V, Z delle
 * specifiche tecniche per gli intermediari). Il file va controllato e
 * autenticato con il software dell'Agenzia e trasmesso con Entratel: il
 * contenuto resta qui per poterlo scaricare di nuovo.
 */
class InvioTelematico extends ReferencedEntity
{

    protected int $id;
    protected Studio $studio;
    protected Intermediario $intermediario;
    protected SismaDateTime $dataCreazione;
    protected bool $addebitoIntermediario = false;
    protected int $numeroDeleghe;
    protected float $totale;
    protected string $nomeFile;
    protected string $contenuto;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataCreazione = new SismaDateTime();
    }
}
