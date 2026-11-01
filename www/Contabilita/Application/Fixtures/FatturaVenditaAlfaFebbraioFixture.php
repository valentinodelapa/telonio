<?php

namespace Contabilita\Application\Fixtures;

use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Fattura di vendita demo di Alfa a Beta (febbraio 2026): dà importi alle
 * liquidazioni di febbraio e marzo e alla comunicazione del trimestre.
 */
class FatturaVenditaAlfaFebbraioFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(EsercizioAlfa2026Fixture::class)
                ->addDependency(ClienteBetaAlfaFixture::class)
                ->addDependency(FatturaVenditaAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $this->addEntity(RegistrazioneDocumentoIva::registra($this->dataMapper, $this->getEntityByFixtureName(EsercizioAlfa2026Fixture::class),
                        $this->getEntityByFixtureName(ClienteBetaAlfaFixture::class), CausaleContabile::fatturaVendita, '2026-02-12', '4', ['22' => 3200.0]));
    }
}
