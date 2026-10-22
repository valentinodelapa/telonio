<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Enumerations\NaturaGiuridica;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dati fiscali demo: associazione, ente non commerciale.
 */
class DatiFiscaliAssociazioneFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAssociazioneFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiFiscali = new DatiFiscali($this->dataMapper);
        $datiFiscali->soggetto = $this->getEntityByFixtureName(SoggettoAssociazioneFixture::class);
        $datiFiscali->validoDal = new SismaDate('2010-03-01');
        $datiFiscali->denominazione = 'Associazione Culturale Il Faro';
        $datiFiscali->naturaGiuridica = NaturaGiuridica::associazione;
        $datiFiscali->enteNonCommerciale = true;
        $this->addEntity($datiFiscali);
    }
}
