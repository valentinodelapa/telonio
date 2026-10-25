<?php

namespace Successioni\Application\Fixtures;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Enumerations\CausaleClassamento;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Classamento demo del terreno di Caserta.
 */
class DatiCatastaliTerrenoEspositoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileTerrenoEspositoFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiCatastali = new DatiCatastali($this->dataMapper);
        $datiCatastali->immobile = $this->getEntityByFixtureName(ImmobileTerrenoEspositoFixture::class);
        $datiCatastali->validoDal = new SismaDate('1990-01-01');
        $datiCatastali->causale = CausaleClassamento::costituzione;
        $datiCatastali->superficie = 12500.0;
        $datiCatastali->qualitaColtura = 'Seminativo';
        $datiCatastali->redditoDominicale = 80.0;
        $datiCatastali->redditoAgrario = 45.0;
        $this->addEntity($datiCatastali);
    }
}
