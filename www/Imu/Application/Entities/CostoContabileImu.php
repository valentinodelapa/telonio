<?php

namespace Imu\Application\Entities;

use Anagrafica\Application\Entities\Immobile;
use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Costo di acquisizione o incrementativo, risultante dalle scritture
 * contabili, di un fabbricato del gruppo D non iscritto in catasto (o senza
 * rendita) interamente posseduto da un'impresa: il valore IMU è la somma
 * dei costi per i coefficienti dell'anno di sostenimento (comma 746).
 */
class CostoContabileImu extends BaseEntity
{

    protected int $id;
    protected Immobile $immobile;
    protected int $anno;
    protected float $importo;
    protected ?string $descrizione = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
