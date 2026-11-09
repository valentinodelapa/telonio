<?php

namespace Dichiarativi\Application\Entities;

use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Valore di un punto della Certificazione Unica (importo, giorni o codice).
 */
class ValoreCertificazioneUnica extends BaseEntity
{

    protected int $id;
    protected CertificazioneUnica $certificazioneUnica;
    protected int $punto;
    protected float $valore;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
