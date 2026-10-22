<?php

namespace Imu\Application\Entities;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Interfaces\Storicizzabile;
use Anagrafica\Application\Traits\StoricizzabileTrait;
use Imu\Application\Enumerations\EsenzioneImu;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Caratteristiche IMU di un immobile dell'Anagrafica, valide per tutti i
 * possessori e storicizzate: interesse storico o artistico e inagibilità
 * (riduzione del 50% della base imponibile, comma 747), ruralità
 * strumentale, fabbricato merce, area fabbricabile con il valore venale al
 * 1° gennaio (comma 746), fabbricato del gruppo D a valore contabile
 * (costi in CostoContabileImu), ex comune nei comuni nati da fusione con
 * aliquote differenziate, esenzioni oggettive.
 *
 * In assenza di versioni l'immobile si tratta secondo i soli dati
 * catastali.
 */
class CaratteristicheImu extends BaseEntity implements Storicizzabile
{

    use StoricizzabileTrait;

    protected int $id;
    protected Immobile $immobile;
    protected SismaDate $validoDal;
    protected ?SismaDate $validoAl = null;
    protected bool $storicoArtistico = false;
    protected bool $inagibile = false;
    protected bool $ruraleStrumentale = false;
    protected bool $beneMerce = false;
    protected bool $areaFabbricabile = false;
    protected ?float $valoreVenale = null;
    protected bool $valoreContabile = false;
    protected ?string $exComune = null;
    protected ?EsenzioneImu $esenzione = null;
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
     * Elenco breve delle caratteristiche, per gli elenchi.
     *
     * @return string[]
     */
    public function getEtichette(): array
    {
        return array_keys(array_filter([
            'Storico/artistico' => $this->storicoArtistico,
            'Inagibile' => $this->inagibile,
            'Rurale strumentale' => $this->ruraleStrumentale,
            'Bene merce' => $this->beneMerce,
            'Area fabbricabile' => $this->areaFabbricabile,
            'Valore contabile' => $this->valoreContabile,
            'Esente' => $this->esenzione !== null,
        ]));
    }
}
