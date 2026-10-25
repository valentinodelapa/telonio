<?php

namespace Successioni\Application\Fixtures;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Enumerations\CausaleClassamento;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Classamento demo dell'appartamento di Napoli (A/2).
 */
class DatiCatastaliCasaEspositoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileCasaEspositoFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiCatastali = new DatiCatastali($this->dataMapper);
        $datiCatastali->immobile = $this->getEntityByFixtureName(ImmobileCasaEspositoFixture::class);
        $datiCatastali->validoDal = new SismaDate('1995-06-01');
        $datiCatastali->causale = CausaleClassamento::costituzione;
        $datiCatastali->categoria = CategoriaCatastale::a2;
        $datiCatastali->classe = '3';
        $datiCatastali->consistenza = 6.0;
        $datiCatastali->superficie = 110.0;
        $datiCatastali->rendita = 1100.0;
        $this->addEntity($datiCatastali);
    }
}
