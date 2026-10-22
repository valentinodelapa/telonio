<?php

namespace Imu\Application\Fixtures;

use Imu\Application\Entities\CostoContabileImu;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Costo contabile demo del magazzino: ampliamento della baia di carico (2026).
 */
class CostoMagazzinoAlfa2026Fixture extends BaseFixture
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
        $costo->anno = 2026;
        $costo->importo = 35000.0;
        $costo->descrizione = 'Ampliamento della baia di carico';
        $this->addEntity($costo);
    }
}
