<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dati catastali demo: classamento della villa.
 */
class DatiCatastaliVillaBianchiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileVillaBianchiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiCatastali = new DatiCatastali($this->dataMapper);
        $datiCatastali->immobile = $this->getEntityByFixtureName(ImmobileVillaBianchiFixture::class);
        $datiCatastali->validoDal = new SismaDate('2005-06-01');
        $datiCatastali->categoria = CategoriaCatastale::a8;
        $datiCatastali->classe = '3';
        $datiCatastali->consistenza = 11.5;
        $datiCatastali->rendita = 2800.0;
        $this->addEntity($datiCatastali);
    }
}
