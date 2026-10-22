<?php

namespace Imu\Application\Fixtures;

use Imu\Application\Entities\CostoContabileImu;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Costo contabile demo del magazzino: costruzione (2023).
 */
class CostoMagazzinoAlfa2023Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileMagazzinoAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $costo = new CostoContabileImu($this->dataMapper);
        $costo->immobile = $this->getEntityByFixtureName(ImmobileMagazzinoAlfaFixture::class);
        $costo->anno = 2023;
        $costo->importo = 420000.0;
        $costo->descrizione = 'Costruzione';
        $this->addEntity($costo);
    }
}
