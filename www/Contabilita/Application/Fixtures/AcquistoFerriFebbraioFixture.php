<?php

namespace Contabilita\Application\Fixtures;

use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Acquisto demo di merci per la rivendita del negozio Ferri (febbraio).
 */
class AcquistoFerriFebbraioFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(CorrispettiviFerriGennaioFixture::class)
                ->addDependency(EsercizioFerri2026Fixture::class)
                ->addDependency(FornitoreGrossistaFerriFixture::class)
                ->addDependency(AcquistoFerriConsumoFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $this->addEntity(RegistrazioneDocumentoIva::registra($this->dataMapper, $this->getEntityByFixtureName(EsercizioFerri2026Fixture::class),
                        $this->getEntityByFixtureName(FornitoreGrossistaFerriFixture::class), CausaleContabile::fatturaAcquisto, '2026-02-10', '1037', ['10' => 2500.0, '22' => 1000.0], ['rivendita' => true]));
    }
}
