<?php

namespace Contabilita\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Contabilita\Application\Entities\RegistroIva;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Sezionale demo delle vendite di Alfa, con numerazione propria dei
 * protocolli.
 */
class SezionaleVenditeAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(EsercizioAlfa2026Fixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $alfa = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $registro = new RegistroIva($this->dataMapper);
        $registro->studio = $alfa->studio;
        $registro->soggetto = $alfa;
        $registro->tipo = TipoRegistroIva::vendite;
        $registro->sezionale = 'B';
        $registro->descrizione = 'Fatture emesse verso l\'estero';
        $this->addEntity($registro);
    }
}
