<?php

namespace Successioni\Application\Fixtures;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Classamento demo del negozio di Trento (C/1).
 */
class DatiCatastaliNegozioEspositoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileNegozioEspositoFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiCatastali = new DatiCatastali($this->dataMapper);
        $datiCatastali->immobile = $this->getEntityByFixtureName(ImmobileNegozioEspositoFixture::class);
        $datiCatastali->validoDal = new SismaDate('2005-01-01');
        $datiCatastali->categoria = CategoriaCatastale::c1;
        $datiCatastali->classe = '5';
        $datiCatastali->consistenza = 40.0;
        $datiCatastali->superficie = 48.0;
        $datiCatastali->rendita = 2000.0;
        $this->addEntity($datiCatastali);
    }
}
