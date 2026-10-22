<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Anagrafica\Application\Fixtures\SoggettoBianchiFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Possesso demo: Bianchi acquista la villa il 10 marzo 2026 e vi trasferisce la residenza.
 */
class PossessoVillaBianchiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileVillaBianchiFixture::class)
                ->addDependency(SoggettoBianchiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $possesso = new Possesso($this->dataMapper);
        $possesso->immobile = $this->getEntityByFixtureName(ImmobileVillaBianchiFixture::class);
        $possesso->soggetto = $this->getEntityByFixtureName(SoggettoBianchiFixture::class);
        $possesso->titolo = TitoloPossesso::proprieta;
        $possesso->quota = 100.0;
        $possesso->validoDal = new SismaDate('2026-03-10');
        $possesso->utilizzo = UtilizzoImmobile::abitazionePrincipale;
        $this->addEntity($possesso);
    }
}
