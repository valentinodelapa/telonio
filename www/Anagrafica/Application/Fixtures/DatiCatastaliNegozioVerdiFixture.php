<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dati catastali demo: classamento del negozio.
 */
class DatiCatastaliNegozioVerdiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileNegozioVerdiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiCatastali = new DatiCatastali($this->dataMapper);
        $datiCatastali->immobile = $this->getEntityByFixtureName(ImmobileNegozioVerdiFixture::class);
        $datiCatastali->validoDal = new SismaDate('2015-03-01');
        $datiCatastali->categoria = CategoriaCatastale::c1;
        $datiCatastali->classe = '9';
        $datiCatastali->consistenza = 80.0;
        $datiCatastali->superficie = 92.0;
        $datiCatastali->rendita = 2450.0;
        $this->addEntity($datiCatastali);
    }
}
