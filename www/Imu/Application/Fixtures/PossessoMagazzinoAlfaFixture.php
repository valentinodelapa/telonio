<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Possesso demo: magazzino costruito da Alfa sul lotto acquistato nel 2023.
 */
class PossessoMagazzinoAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileMagazzinoAlfaFixture::class)
                ->addDependency(SoggettoAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $possesso = new Possesso($this->dataMapper);
        $possesso->immobile = $this->getEntityByFixtureName(ImmobileMagazzinoAlfaFixture::class);
        $possesso->soggetto = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $possesso->titolo = TitoloPossesso::proprieta;
        $possesso->quota = 100.0;
        $possesso->validoDal = new SismaDate('2023-05-10');
        $possesso->utilizzo = UtilizzoImmobile::strumentale;
        $this->addEntity($possesso);
    }
}
