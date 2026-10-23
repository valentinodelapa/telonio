<?php

namespace Successioni\Application\Fixtures;

use SismaFramework\Core\BaseClasses\BaseFixture;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Enumerations\CodiceDiritto;
use Successioni\Application\Enumerations\QuadroBene;
use Successioni\Application\Enumerations\TipologiaTerreno;

/**
 * Terreno di Caserta (reddito dominicale 80 x 1,25 x 90).
 */
class BeneTerrenoEspositoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(DichiarazioneEspositoFixture::class);
        $this->addDependency(ImmobileTerrenoEspositoFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $bene = new BeneSuccessione($this->dataMapper);
        $bene->dichiarazioneSuccessione = $this->getEntityByFixtureName(DichiarazioneEspositoFixture::class);
        $bene->quadro = QuadroBene::terreni;
        $bene->ordine = 1;
        $bene->immobile = $this->getEntityByFixtureName(ImmobileTerrenoEspositoFixture::class);
        $bene->codiceDiritto = CodiceDiritto::proprieta;
        $bene->natura = 'T';
        $bene->tipologiaTerreno = TipologiaTerreno::nonEdificabile;
        $bene->valore = 9000;
        $bene->discordanzaIntestatario = true;
        $this->addEntity($bene);
    }
}
