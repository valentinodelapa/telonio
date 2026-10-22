<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dati catastali demo: nuova rendita dopo la ristrutturazione (variazione catastale).
 */
class DatiCatastaliCasaRossiRistrutturazioneFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileCasaRossiFixture::class)
                ->addDependency(DatiCatastaliCasaRossiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiCatastali = new DatiCatastali($this->dataMapper);
        $datiCatastali->immobile = $this->getEntityByFixtureName(ImmobileCasaRossiFixture::class);
        $datiCatastali->validoDal = new SismaDate('2022-01-01');
        $datiCatastali->categoria = CategoriaCatastale::a2;
        $datiCatastali->classe = '5';
        $datiCatastali->consistenza = 6.0;
        $datiCatastali->superficie = 102.0;
        $datiCatastali->rendita = 1050.0;
        $this->addEntity($datiCatastali);
    }
}
