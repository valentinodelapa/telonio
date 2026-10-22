<?php

namespace Imu\Application\Entities;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Interfaces\Storicizzabile;
use Anagrafica\Application\Traits\StoricizzabileTrait;
use Imu\Application\Enumerations\AssimilazioneAbitazione;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Agevolazioni IMU che dipendono dal possessore dell'immobile,
 * storicizzate per soggetto e immobile:
 * - comodato a parenti in linea retta di primo grado con i requisiti del
 *   comma 747, lett. c) (riduzione del 50% della base imponibile);
 * - terreno o area posseduti e condotti da coltivatore diretto o IAP
 *   (terreni esenti, comma 758 lett. a; aree trattate come terreni
 *   agricoli, comma 741 lett. d);
 * - assimilazione all'abitazione principale (comma 741, lett. c);
 * - pensionato residente all'estero (imposta al 50%, L. 178/2020, c. 48;
 *   al 37,5% per il 2022, L. 234/2021, c. 743).
 */
class AgevolazioneImu extends BaseEntity implements Storicizzabile
{

    use StoricizzabileTrait;

    protected int $id;
    protected Soggetto $soggetto;
    protected Immobile $immobile;
    protected SismaDate $validoDal;
    protected ?SismaDate $validoAl = null;
    protected bool $comodatoParenti = false;
    protected bool $conduzioneAgricola = false;
    protected ?AssimilazioneAbitazione $assimilazione = null;
    protected bool $pensionatoEstero = false;
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

    /**
     * @return string[]
     */
    public function getEtichette(): array
    {
        return array_keys(array_filter([
            'Comodato a parenti' => $this->comodatoParenti,
            'CD/IAP' => $this->conduzioneAgricola,
            'Assimilata ad abitazione principale' => $this->assimilazione !== null,
            'Pensionato estero' => $this->pensionatoEstero,
        ]));
    }
}
