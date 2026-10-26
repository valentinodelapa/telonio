<?php

namespace GestioneStudio\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Interfaces\Storicizzabile;
use Anagrafica\Application\Traits\StoricizzabileTrait;
use Telonio\Application\Entities\Studio;
use Telonio\Application\Entities\Utente;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Adempimento a cui è tenuto il soggetto, con l'operatore responsabile.
 *
 * Storicizzata: valgono i termini che cadono nella validità (es. dalla
 * data di inizio attività fino all'ultima dichiarazione dopo la
 * cessazione); un nuovo responsabile da una data è una variazione.
 */
class AssegnazioneAdempimento extends BaseEntity implements Storicizzabile
{

    use StoricizzabileTrait;

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected Adempimento $adempimento;
    protected ?Utente $responsabile = null;
    protected SismaDate $validoDal;
    protected ?SismaDate $validoAl = null;
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
