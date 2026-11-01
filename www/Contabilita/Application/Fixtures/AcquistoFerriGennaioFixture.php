<?php

namespace Contabilita\Application\Fixtures;

use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Acquisto demo di merci per la rivendita del negozio Ferri (gennaio).
 */
class AcquistoFerriGennaioFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(EsercizioFerri2026Fixture::class)
                ->addDependency(FornitoreGrossistaFerriFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $this->addEntity(RegistrazioneDocumentoIva::registra($this->dataMapper, $this->getEntityByFixtureName(EsercizioFerri2026Fixture::class),
                        $this->getEntityByFixtureName(FornitoreGrossistaFerriFixture::class), CausaleContabile::fatturaAcquisto, '2026-01-12', '1001', ['4' => 2000.0, '10' => 3000.0, '22' => 1500.0], ['rivendita' => true]));
    }
}
