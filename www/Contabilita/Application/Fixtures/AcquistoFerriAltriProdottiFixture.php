<?php

namespace Contabilita\Application\Fixtures;

use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Acquisto demo del negozio Ferri di articoli per la casa da rivendere:
 * "altri prodotti" rispetto alle categorie ammesse alla ventilazione,
 * entrano nei rapporti e nel limite del 50% (art. 4 D.M. 24 febbraio 1973).
 */
class AcquistoFerriAltriProdottiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(EsercizioFerri2026Fixture::class)
                ->addDependency(FornitoreGrossistaFerriFixture::class)
                ->addDependency(AcquistoFerriMarzoFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $this->addEntity(RegistrazioneDocumentoIva::registra($this->dataMapper, $this->getEntityByFixtureName(EsercizioFerri2026Fixture::class),
                        $this->getEntityByFixtureName(FornitoreGrossistaFerriFixture::class), CausaleContabile::fatturaAcquisto, '2026-03-16', '1101', ['22' => 400.0], ['altriProdotti' => true]));
    }
}
