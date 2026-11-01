<?php

namespace Contabilita\Application\Fixtures;

use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Fattura di vendita demo di Alfa a Beta (gennaio 2026, 22% e 10%).
 */
class FatturaVenditaAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(EsercizioAlfa2026Fixture::class)
                ->addDependency(ClienteBetaAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $this->addEntity(RegistrazioneDocumentoIva::registra($this->dataMapper, $this->getEntityByFixtureName(EsercizioAlfa2026Fixture::class),
                        $this->getEntityByFixtureName(ClienteBetaAlfaFixture::class), CausaleContabile::fatturaVendita, '2026-01-15', '1', ['22' => 2500.0, '10' => 400.0]));
    }
}
