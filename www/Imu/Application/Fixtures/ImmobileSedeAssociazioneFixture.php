<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Models\ComuneModel;
use CommSuite\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Immobile demo IMU: sede dell'associazione a Roma, usata anche per corsi a pagamento.
 */
class ImmobileSedeAssociazioneFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $immobile = new Immobile($this->dataMapper);
        $immobile->studio = $this->getEntityByFixtureName(StudioFixture::class);
        $immobile->tipoCatasto = TipoCatasto::fabbricati;
        $immobile->comune = (new ComuneModel($this->dataMapper))->getByCodice('H501');
        $immobile->foglio = '598';
        $immobile->particella = '214';
        $immobile->subalterno = '501';
        $immobile->indirizzo = 'Via dei Sabelli';
        $immobile->numeroCivico = '12';
        $immobile->descrizione = 'Sede e aule per corsi';
        $this->addEntity($immobile);
    }
}
