<?php

namespace Contabilita\Application\Fixtures;

use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Acquisto demo di merci per la rivendita del negozio Ferri (marzo).
 */
class AcquistoFerriMarzoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(CorrispettiviFerriFebbraioFixture::class)
                ->addDependency(EsercizioFerri2026Fixture::class)
                ->addDependency(FornitoreGrossistaFerriFixture::class)
                ->addDependency(AcquistoFerriFebbraioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $this->addEntity(RegistrazioneDocumentoIva::registra($this->dataMapper, $this->getEntityByFixtureName(EsercizioFerri2026Fixture::class),
                        $this->getEntityByFixtureName(FornitoreGrossistaFerriFixture::class), CausaleContabile::fatturaAcquisto, '2026-03-09', '1082', ['4' => 1000.0, '10' => 2000.0], ['rivendita' => true]));
    }
}
