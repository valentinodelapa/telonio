<?php

namespace Successioni\Application\Fixtures;

use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Metà dell'appartamento di Napoli di Giuseppe Esposito (abitazione principale).
 */
class PossessoCasaEspositoGiuseppeFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileCasaEspositoFixture::class);
        $this->addDependency(SoggettoEspositoGiuseppeFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $possesso = new Possesso($this->dataMapper);
        $possesso->immobile = $this->getEntityByFixtureName(ImmobileCasaEspositoFixture::class);
        $possesso->soggetto = $this->getEntityByFixtureName(SoggettoEspositoGiuseppeFixture::class);
        $possesso->titolo = TitoloPossesso::proprieta;
        $possesso->quota = 50.0;
        $possesso->validoDal = new SismaDate('1995-06-01');
        $possesso->utilizzo = UtilizzoImmobile::abitazionePrincipale;
        $this->addEntity($possesso);
    }
}
