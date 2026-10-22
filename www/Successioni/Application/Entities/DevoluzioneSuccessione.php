<?php

namespace Successioni\Application\Entities;

use SismaFramework\Orm\BaseClasses\BaseEntity;
use Successioni\Application\Enumerations\Agevolazione;
use Successioni\Application\Enumerations\CodiceDiritto;
use Successioni\Application\Enumerations\RiduzioneSuccessioniPrecedenti;

/**
 * Rigo della sezione devoluzione di un bene: quota del bene attribuita al
 * beneficiario, con il diritto trasferito, il valore della quota e
 * l'eventuale agevolazione o riduzione.
 *
 * Se il diritto trasferito coincide con quello del defunto il valore è
 * valore del bene x quota; altrimenti (es. usufrutto e nuda proprietà) va
 * determinato con i coefficienti del TUS e indicato a mano.
 */
class DevoluzioneSuccessione extends BaseEntity
{

    protected int $id;
    protected BeneSuccessione $beneSuccessione;
    protected BeneficiarioSuccessione $beneficiarioSuccessione;
    protected int $quotaNumeratore = 1;
    protected int $quotaDenominatore = 1;
    protected ?float $valoreQuota = null;
    protected CodiceDiritto $codiceDiritto = CodiceDiritto::proprieta;
    protected ?Agevolazione $agevolazione = null;
    protected ?RiduzioneSuccessioniPrecedenti $riduzione = null;

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

    public function getQuotaTesto(): string
    {
        return $this->quotaNumeratore . '/' . $this->quotaDenominatore;
    }
}
