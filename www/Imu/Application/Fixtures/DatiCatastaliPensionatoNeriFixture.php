<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\CausaleClassamento;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dati catastali demo: unica abitazione di Neri a Bergamo, pensionata residente all'estero (imposta al 50% e aliquota differenziata).
 */
class DatiCatastaliPensionatoNeriFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobilePensionatoNeriFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiCatastali = new DatiCatastali($this->dataMapper);
        $datiCatastali->immobile = $this->getEntityByFixtureName(ImmobilePensionatoNeriFixture::class);
        $datiCatastali->validoDal = new SismaDate('2015-07-01');
        $datiCatastali->causale = CausaleClassamento::costituzione;
        $datiCatastali->categoria = CategoriaCatastale::a2;
        $datiCatastali->classe = '3';
        $datiCatastali->consistenza = 6.0;
        $datiCatastali->rendita = 620.0;
        $this->addEntity($datiCatastali);
    }
}
