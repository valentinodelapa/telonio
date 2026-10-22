<?php

namespace Deleghe\Application\Fixtures;

use Anagrafica\Application\Fixtures\ContoCorrenteRossiFixture;
use Anagrafica\Application\Fixtures\SoggettoRossiFixture;
use CommSuite\Application\Fixtures\StudioFixture;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Enumerations\ModelloF24;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * F24 ELIDE: imposta di registro per l'annualità successiva di un contratto
 * di locazione, con l'identificativo telematico del contratto.
 */
class DelegaRossiRegistroFixture extends BaseFixture
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
        $delega->modello = ModelloF24::elide;
        $delega->contoAddebito = $this->getEntityByFixtureName(ContoCorrenteRossiFixture::class);
        $delega->scadenza = new SismaDate('2026-11-30');
        $delega->descrizione = 'Registro locazione via Appia: annualità 2026';
        $this->addEntity($delega);
    }
}
