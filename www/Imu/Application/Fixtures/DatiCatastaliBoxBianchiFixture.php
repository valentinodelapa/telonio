<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dati catastali demo: classamento del box.
 */
class DatiCatastaliBoxBianchiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileBoxBianchiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiCatastali = new DatiCatastali($this->dataMapper);
        $datiCatastali->immobile = $this->getEntityByFixtureName(ImmobileBoxBianchiFixture::class);
        $datiCatastali->validoDal = new SismaDate('2005-06-01');
        $datiCatastali->categoria = CategoriaCatastale::c6;
        $datiCatastali->classe = '8';
        $datiCatastali->consistenza = 18.0;
        $datiCatastali->rendita = 180.0;
        $this->addEntity($datiCatastali);
    }
}
