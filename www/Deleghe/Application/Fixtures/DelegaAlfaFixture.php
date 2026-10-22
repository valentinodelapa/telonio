<?php

namespace Deleghe\Application\Fixtures;

use Anagrafica\Application\Fixtures\ContoCorrenteAlfaFixture;
use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use CommSuite\Application\Fixtures\StudioFixture;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Enumerations\StatoDelega;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Società: ritenute, contributi INPS dei dipendenti e compensazione del credito IVA annuale.
 */
class DelegaAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class)
                ->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(ContoCorrenteAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $delega = new Delega($this->dataMapper);
        $delega->studio = $this->getEntityByFixtureName(StudioFixture::class);
        $delega->soggetto = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $delega->contoAddebito = $this->getEntityByFixtureName(ContoCorrenteAlfaFixture::class);
        $delega->scadenza = new SismaDate('2026-11-16');
        $delega->descrizione = 'Versamenti di ottobre';
        $delega->stato = StatoDelega::bozza;
        $this->addEntity($delega);
    }
}
