<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use CommSuite\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Ente non commerciale demo: associazione culturale proprietaria di una sede usata anche per corsi.
 */
class SoggettoAssociazioneFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->studio = $this->getEntityByFixtureName(StudioFixture::class);
        $soggetto->tipoSoggetto = TipoSoggetto::ente;
        $soggetto->codice = 'C010';
        $soggetto->codiceFiscale = '97012345670';
        $soggetto->nominativo = 'Associazione Culturale Il Faro';
        $soggetto->clienteStudio = true;
        $soggetto->dataCostituzione = new SismaDate('2010-03-01');
        $this->addEntity($soggetto);
    }
}
