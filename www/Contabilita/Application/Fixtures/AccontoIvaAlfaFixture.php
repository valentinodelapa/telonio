<?php

namespace Contabilita\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Contabilita\Application\Enumerations\MetodoAccontoIva;
use Contabilita\Application\Services\ElaborazioneAccontoIva;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Acconto IVA demo di Alfa per il 2026 con il metodo previsionale, con il
 * versamento del 28 dicembre (6013) nell'archivio di Deleghe F24.
 */
class AccontoIvaAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(LiquidazioneAlfaGennaioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $this->addEntity((new ElaborazioneAccontoIva($this->dataMapper))->elabora($this->getEntityByFixtureName(SoggettoAlfaFixture::class), 2026, MetodoAccontoIva::previsionale, 600.0,
                        'Dovuto presunto per dicembre comunicato dal cliente'));
    }
}
