<?php

namespace Successioni\Application\Fixtures;

use SismaFramework\Core\BaseClasses\BaseFixture;
use Successioni\Application\Entities\EstremoVoltura;
use Successioni\Application\Models\UfficioTrascrizioneModel;

/**
 * Quadro EI demo: in catasto il terreno di Caserta è ancora intestato al venditore; estremi della trascrizione dell'acquisto del 2001.
 */
class EstremoTerrenoEspositoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(BeneTerrenoEspositoFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $estremo = new EstremoVoltura($this->dataMapper);
        $estremo->beneSuccessione = $this->getEntityByFixtureName(BeneTerrenoEspositoFixture::class);
        $estremo->tipo = EstremoVoltura::TRASCRIZIONE;
        $estremo->ufficioTrascrizione = (new UfficioTrascrizioneModel($this->dataMapper))->getByCodice('CE00')[0];
        $estremo->anno = 2001;
        $estremo->numero = '9876';
        $this->addEntity($estremo);
    }
}
