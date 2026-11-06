<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\CausaleClassamento;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dati catastali demo: alloggio di Verdi a Bergamo in comodato al figlio (base al 50% e aliquota differenziata).
 */
class DatiCatastaliComodatoVerdiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileComodatoVerdiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiCatastali = new DatiCatastali($this->dataMapper);
        $datiCatastali->immobile = $this->getEntityByFixtureName(ImmobileComodatoVerdiFixture::class);
        $datiCatastali->validoDal = new SismaDate('2018-04-01');
        $datiCatastali->causale = CausaleClassamento::costituzione;
        $datiCatastali->categoria = CategoriaCatastale::a3;
        $datiCatastali->classe = '2';
        $datiCatastali->consistenza = 5.0;
        $datiCatastali->rendita = 450.0;
        $this->addEntity($datiCatastali);
    }
}
