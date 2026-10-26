<?php

namespace Deleghe\Application\Fixtures;

use Anagrafica\Application\Fixtures\ContoCorrenteVerdiFixture;
use Anagrafica\Application\Fixtures\SoggettoVerdiFixture;
use Telonio\Application\Fixtures\StudioFixture;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Enumerations\StatoDelega;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Ditta individuale: contributi fissi artigiani e un rigo IMU generato dal modulo IMU (origine).
 */
class DelegaVerdiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class)
                ->addDependency(SoggettoVerdiFixture::class)
                ->addDependency(ContoCorrenteVerdiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $delega = new Delega($this->dataMapper);
        $delega->studio = $this->getEntityByFixtureName(StudioFixture::class);
        $delega->soggetto = $this->getEntityByFixtureName(SoggettoVerdiFixture::class);
        $delega->contoAddebito = $this->getEntityByFixtureName(ContoCorrenteVerdiFixture::class);
        $delega->scadenza = new SismaDate('2026-11-16');
        $delega->descrizione = 'Contributi artigiani e IMU';
        $delega->stato = StatoDelega::bozza;
        $this->addEntity($delega);
    }
}
