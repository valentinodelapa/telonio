<?php

namespace Dichiarativi\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\Enumerations\TipoPerdita;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use Telonio\Application\Entities\Studio;

/**
 * Perdite fiscali non compensate alla fine di un periodo d'imposta (art. 84
 * Tuir: in misura limitata all'80 per cento del reddito, comma 1, o piena
 * se dei primi tre periodi d'imposta, comma 2), per il primo anno in cui la
 * dichiarazione dell'anno prima non è nella suite: sono i residui del
 * prospetto delle perdite non compensate di quella dichiarazione. Dagli
 * anni successivi i residui vengono dalla dichiarazione dell'anno prima.
 */
class PerditaPregressa extends BaseEntity
{

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    /** periodo d'imposta alla cui fine le perdite sono residue */
    protected int $anno;
    protected TipoPerdita $tipo = TipoPerdita::impresa;
    protected int $limitata = 0;
    protected int $piena = 0;
    protected ?string $note = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
