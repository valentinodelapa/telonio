<?php

namespace Imu\Application\Entities;

use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Coefficiente di aggiornamento dei costi dei fabbricati del gruppo D a
 * valore contabile (art. 1, comma 746, L. 160/2019) stabilito con il
 * decreto annuale del MEF per l'anno d'imposta e l'anno di sostenimento
 * del costo. Tabella di riferimento comune a tutti gli studi.
 */
class CoefficienteFabbricatiD extends BaseEntity
{

    protected int $id;
    protected int $annoImposta;
    protected int $annoCosto;
    protected float $coefficiente;
    protected string $fonte;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
