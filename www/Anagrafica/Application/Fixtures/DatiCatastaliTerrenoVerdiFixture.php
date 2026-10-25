<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Enumerations\CausaleClassamento;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dati catastali demo: qualità e redditi del terreno.
 */
class DatiCatastaliTerrenoVerdiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileTerrenoVerdiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiCatastali = new DatiCatastali($this->dataMapper);
        $datiCatastali->immobile = $this->getEntityByFixtureName(ImmobileTerrenoVerdiFixture::class);
        $datiCatastali->validoDal = new SismaDate('2018-06-01');
        $datiCatastali->causale = CausaleClassamento::costituzione;
        $datiCatastali->classe = '2';
        $datiCatastali->superficie = 12000.0;
        $datiCatastali->qualitaColtura = 'SEMINATIVO';
        $datiCatastali->redditoDominicale = 85.2;
        $datiCatastali->redditoAgrario = 62.1;
        $this->addEntity($datiCatastali);
    }
}
