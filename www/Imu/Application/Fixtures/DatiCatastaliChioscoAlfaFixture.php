<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\CausaleClassamento;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dati catastali demo: chiosco di Alfa a Roma su area demaniale: soggetto passivo il concessionario (comma 743).
 */
class DatiCatastaliChioscoAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileChioscoAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiCatastali = new DatiCatastali($this->dataMapper);
        $datiCatastali->immobile = $this->getEntityByFixtureName(ImmobileChioscoAlfaFixture::class);
        $datiCatastali->validoDal = new SismaDate('2016-05-01');
        $datiCatastali->causale = CausaleClassamento::costituzione;
        $datiCatastali->categoria = CategoriaCatastale::c1;
        $datiCatastali->classe = '4';
        $datiCatastali->consistenza = 40.0;
        $datiCatastali->rendita = 700.0;
        $this->addEntity($datiCatastali);
    }
}
