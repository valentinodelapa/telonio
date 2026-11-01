<?php

namespace Contabilita\Application\Fixtures;

use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\EsigibilitaIva;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Fattura di vendita demo di Alfa al Comune con la scissione dei
 * pagamenti: l'IVA è annotata ma non entra nella liquidazione.
 */
class FatturaSplitAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(EsercizioAlfa2026Fixture::class)
                ->addDependency(ClientePaAlfaFixture::class)
                ->addDependency(FatturaAcquistoAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $this->addEntity(RegistrazioneDocumentoIva::registra($this->dataMapper, $this->getEntityByFixtureName(EsercizioAlfa2026Fixture::class),
                        $this->getEntityByFixtureName(ClientePaAlfaFixture::class), CausaleContabile::fatturaVendita, '2026-01-22', '2', ['22' => 500.0], ['esigibilita' => EsigibilitaIva::scissionePagamenti]));
    }
}
