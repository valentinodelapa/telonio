<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dati catastali demo: classamento originario dell'appartamento.
 */
class DatiCatastaliCasaRossiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileCasaRossiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiCatastali = new DatiCatastali($this->dataMapper);
        $datiCatastali->immobile = $this->getEntityByFixtureName(ImmobileCasaRossiFixture::class);
        $datiCatastali->validoDal = new SismaDate('2010-01-01');
        $datiCatastali->validoAl = new SismaDate('2021-12-31');
        $datiCatastali->categoria = CategoriaCatastale::a2;
        $datiCatastali->classe = '4';
        $datiCatastali->consistenza = 5.5;
        $datiCatastali->superficie = 95.0;
        $datiCatastali->rendita = 900.0;
        $this->addEntity($datiCatastali);
    }
}
