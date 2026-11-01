<?php

namespace Contabilita\Application\Fixtures;

use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Nota di credito demo emessa da Alfa a Beta (storno parziale della
 * fattura n. 1).
 */
class NotaCreditoAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(EsercizioAlfa2026Fixture::class)
                ->addDependency(ClienteBetaAlfaFixture::class)
                ->addDependency(FatturaSplitAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $this->addEntity(RegistrazioneDocumentoIva::registra($this->dataMapper, $this->getEntityByFixtureName(EsercizioAlfa2026Fixture::class),
                        $this->getEntityByFixtureName(ClienteBetaAlfaFixture::class), CausaleContabile::notaCreditoEmessa, '2026-01-25', '3', ['22' => 100.0]));
    }
}
