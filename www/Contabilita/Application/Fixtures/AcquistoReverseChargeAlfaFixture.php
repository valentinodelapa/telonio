<?php

namespace Contabilita\Application\Fixtures;

use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Fattura di acquisto demo di Alfa da Delta: manutenzione al 22% e
 * subappalto in inversione contabile con l'integrazione dell'imposta (RC22).
 */
class AcquistoReverseChargeAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(EsercizioAlfa2026Fixture::class)
                ->addDependency(FornitoreDeltaAlfaFixture::class)
                ->addDependency(NotaCreditoAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $this->addEntity(RegistrazioneDocumentoIva::registra($this->dataMapper, $this->getEntityByFixtureName(EsercizioAlfa2026Fixture::class),
                        $this->getEntityByFixtureName(FornitoreDeltaAlfaFixture::class), CausaleContabile::fatturaAcquisto, '2026-01-26', 'D/45', ['22' => 1000.0, 'RC22' => 100.0]));
    }
}
