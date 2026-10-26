<?php

namespace Deleghe\Application\Fixtures;

use Anagrafica\Application\Fixtures\ContoCorrenteRossiFixture;
use Anagrafica\Application\Fixtures\SoggettoRossiFixture;
use Telonio\Application\Fixtures\StudioFixture;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Enumerations\StatoDelega;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Persona fisica: saldo e primo acconto IRPEF con addizionale regionale, delega già pagata.
 */
class DelegaRossiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class)
                ->addDependency(SoggettoRossiFixture::class)
                ->addDependency(ContoCorrenteRossiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $delega = new Delega($this->dataMapper);
        $delega->studio = $this->getEntityByFixtureName(StudioFixture::class);
        $delega->soggetto = $this->getEntityByFixtureName(SoggettoRossiFixture::class);
        $delega->contoAddebito = $this->getEntityByFixtureName(ContoCorrenteRossiFixture::class);
        $delega->scadenza = new SismaDate('2026-06-30');
        $delega->descrizione = 'Redditi 2026: saldo 2025 e primo acconto 2026';
        $delega->stato = StatoDelega::quietanzata;
        $delega->dataQuietanza = new SismaDate('2026-06-30');
        $delega->protocollo = '26063012345678901';
        $this->addEntity($delega);
    }
}
