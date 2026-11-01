<?php

namespace Contabilita\Application\Fixtures;

use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Fattura di acquisto demo di Alfa da Gamma (consulenza di gennaio 2026).
 */
class FatturaAcquistoAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(EsercizioAlfa2026Fixture::class)
                ->addDependency(FornitoreGammaAlfaFixture::class)
                ->addDependency(FatturaVenditaAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $this->addEntity(RegistrazioneDocumentoIva::registra($this->dataMapper, $this->getEntityByFixtureName(EsercizioAlfa2026Fixture::class),
                        $this->getEntityByFixtureName(FornitoreGammaAlfaFixture::class), CausaleContabile::fatturaAcquisto, '2026-01-20', 'G/12', ['22' => 800.0]));
    }
}
