<?php

namespace Successioni\Application\Entities;

use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Quota della passività sostenuta dal beneficiario (sezione ripartizione
 * del quadro ED): riduce l'attivo netto della sua quota.
 */
class RipartizionePassivita extends BaseEntity
{

    protected int $id;
    protected PassivitaSuccessione $passivitaSuccessione;
    protected BeneficiarioSuccessione $beneficiarioSuccessione;
    protected int $quotaNumeratore = 1;
    protected int $quotaDenominatore = 1;
    protected ?float $valoreQuota = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function getQuota(): float
    {
        return $this->quotaDenominatore > 0 ? $this->quotaNumeratore / $this->quotaDenominatore : 0.0;
    }
}
