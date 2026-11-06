<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Anagrafica\Application\Fixtures\SoggettoVerdiFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Possesso demo: alloggio di Verdi a Bergamo in comodato al figlio (base al 50% e aliquota differenziata).
 */
class PossessoComodatoVerdiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileComodatoVerdiFixture::class)
                ->addDependency(SoggettoVerdiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $possesso = new Possesso($this->dataMapper);
        $possesso->immobile = $this->getEntityByFixtureName(ImmobileComodatoVerdiFixture::class);
        $possesso->soggetto = $this->getEntityByFixtureName(SoggettoVerdiFixture::class);
        $possesso->titolo = TitoloPossesso::proprieta;
        $possesso->quota = 100.0;
        $possesso->validoDal = new SismaDate('2018-04-01');
        $possesso->utilizzo = UtilizzoImmobile::comodato;
        $this->addEntity($possesso);
    }
}
