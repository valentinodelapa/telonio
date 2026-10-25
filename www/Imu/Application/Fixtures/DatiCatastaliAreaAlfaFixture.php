<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Enumerations\CausaleClassamento;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dati catastali demo: particella del catasto terreni.
 */
class DatiCatastaliAreaAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileAreaAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiCatastali = new DatiCatastali($this->dataMapper);
        $datiCatastali->immobile = $this->getEntityByFixtureName(ImmobileAreaAlfaFixture::class);
        $datiCatastali->validoDal = new SismaDate('2020-01-01');
        $datiCatastali->causale = CausaleClassamento::costituzione;
        $datiCatastali->qualitaColtura = 'SEMINATIVO';
        $datiCatastali->classe = '2';
        $datiCatastali->superficie = 3200.0;
        $datiCatastali->redditoDominicale = 28.4;
        $datiCatastali->redditoAgrario = 19.8;
        $this->addEntity($datiCatastali);
    }
}
