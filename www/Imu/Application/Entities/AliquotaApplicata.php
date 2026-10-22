<?php

namespace Imu\Application\Entities;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Aliquota da applicare all'immobile del contribuente in un anno, al posto
 * di quella della fattispecie di base: un'aliquota differenziata del
 * prospetto del comune (es. abitazione locata a canone concordato) o
 * un'aliquota indicata dallo studio (es. per gli anni senza prospetto).
 *
 * È annuale perché i prospetti cambiano ogni anno; per l'anno successivo
 * il calcolo propone la differenziata del nuovo prospetto con la stessa
 * descrizione.
 */
class AliquotaApplicata extends BaseEntity
{

    protected int $id;
    protected Soggetto $soggetto;
    protected Immobile $immobile;
    protected int $anno;
    protected ?AliquotaDifferenziata $aliquotaDifferenziata = null;
    protected ?float $aliquota = null;
    protected ?string $motivo = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function getValore(): ?float
    {
        return $this->aliquotaDifferenziata?->aliquota ?? $this->aliquota;
    }
}
