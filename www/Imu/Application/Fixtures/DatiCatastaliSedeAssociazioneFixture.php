<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Enumerations\CausaleClassamento;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dati catastali demo: sede dell'associazione (C/1 adattato ad aule, classamento semplificato).
 */
class DatiCatastaliSedeAssociazioneFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileSedeAssociazioneFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiCatastali = new DatiCatastali($this->dataMapper);
        $datiCatastali->immobile = $this->getEntityByFixtureName(ImmobileSedeAssociazioneFixture::class);
        $datiCatastali->validoDal = new SismaDate('2010-03-01');
        $datiCatastali->causale = CausaleClassamento::costituzione;
        $datiCatastali->categoria = CategoriaCatastale::b5;
        $datiCatastali->classe = '2';
        $datiCatastali->consistenza = 1400.0;
        $datiCatastali->rendita = 3600.0;
        $this->addEntity($datiCatastali);
    }
}
