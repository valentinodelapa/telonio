<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\CausaleClassamento;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dati catastali demo: casa di Rossi a Massimeno (TN): non si applica l'IMU ma l'IMIS (comma 739).
 */
class DatiCatastaliMassimenoRossiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileMassimenoRossiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiCatastali = new DatiCatastali($this->dataMapper);
        $datiCatastali->immobile = $this->getEntityByFixtureName(ImmobileMassimenoRossiFixture::class);
        $datiCatastali->validoDal = new SismaDate('2019-06-01');
        $datiCatastali->causale = CausaleClassamento::costituzione;
        $datiCatastali->categoria = CategoriaCatastale::a2;
        $datiCatastali->classe = '2';
        $datiCatastali->consistenza = 5.5;
        $datiCatastali->rendita = 350.0;
        $this->addEntity($datiCatastali);
    }
}
