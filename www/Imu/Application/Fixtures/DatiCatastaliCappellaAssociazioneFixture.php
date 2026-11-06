<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\CausaleClassamento;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dati catastali demo: cappella dell'associazione destinata esclusivamente al culto (esente, comma 759, lett. d).
 */
class DatiCatastaliCappellaAssociazioneFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileCappellaAssociazioneFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiCatastali = new DatiCatastali($this->dataMapper);
        $datiCatastali->immobile = $this->getEntityByFixtureName(ImmobileCappellaAssociazioneFixture::class);
        $datiCatastali->validoDal = new SismaDate('2010-03-01');
        $datiCatastali->causale = CausaleClassamento::costituzione;
        $datiCatastali->categoria = CategoriaCatastale::b7;
        $datiCatastali->classe = '1';
        $datiCatastali->consistenza = 120.0;
        $datiCatastali->rendita = 200.0;
        $this->addEntity($datiCatastali);
    }
}
