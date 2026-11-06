<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\CausaleClassamento;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dati catastali demo: alloggio regolarmente assegnato di tipo IACP (detrazione del comma 749).
 */
class DatiCatastaliAlloggioAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileAlloggioAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiCatastali = new DatiCatastali($this->dataMapper);
        $datiCatastali->immobile = $this->getEntityByFixtureName(ImmobileAlloggioAlfaFixture::class);
        $datiCatastali->validoDal = new SismaDate('2012-02-01');
        $datiCatastali->causale = CausaleClassamento::costituzione;
        $datiCatastali->categoria = CategoriaCatastale::a4;
        $datiCatastali->classe = '1';
        $datiCatastali->consistenza = 4.5;
        $datiCatastali->rendita = 300.0;
        $this->addEntity($datiCatastali);
    }
}
