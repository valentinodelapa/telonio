<?php

namespace Contabilita\Application\Fixtures;

use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Acquisto demo di materiali di consumo del negozio Ferri: non per la
 * rivendita, escluso dai rapporti della ventilazione.
 */
class AcquistoFerriConsumoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(EsercizioFerri2026Fixture::class)
                ->addDependency(FornitoreServiziFerriFixture::class)
                ->addDependency(AcquistoFerriGennaioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $this->addEntity(RegistrazioneDocumentoIva::registra($this->dataMapper, $this->getEntityByFixtureName(EsercizioFerri2026Fixture::class),
                        $this->getEntityByFixtureName(FornitoreServiziFerriFixture::class), CausaleContabile::fatturaAcquisto, '2026-01-20', 'G/7', ['22' => 200.0]));
    }
}
