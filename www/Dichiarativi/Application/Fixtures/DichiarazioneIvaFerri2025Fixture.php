<?php

namespace Dichiarativi\Application\Fixtures;

use Contabilita\Application\Fixtures\SoggettoFerriFixture;
use Contabilita\Application\Fixtures\StoricoFerri2025Fixture;
use Contabilita\Application\Services\ElaborazioneLiquidazione;
use Deleghe\Application\Fixtures\IntermediarioStudioFixture;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\Iva\PropostaIva;
use Dichiarativi\Application\Services\Iva\VersamentiIva;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Dichiarazione IVA 2026 demo del negozio Ferri (trimestrale, con la
 * ventilazione dei corrispettivi) per l'anno 2025, in bozza: liquidazioni
 * trimestrali elaborate senza giroconto, corrispettivi ventilati con la
 * ventilazione annuale, quadro VH per i trimestri senza comunicazione
 * inviata e saldo 6099 registrato in Deleghe F24 al posto del versamento
 * della liquidazione del quarto trimestre.
 */
class DichiarazioneIvaFerri2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoFerriFixture::class)
                ->addDependency(StoricoFerri2025Fixture::class)
                ->addDependency(SedeFerriFixture::class)
                ->addDependency(IntermediarioStudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $ferri = $this->getEntityByFixtureName(SoggettoFerriFixture::class);
        $elaborazione = new ElaborazioneLiquidazione($this->dataMapper);
        for ($trimestre = 1; $trimestre <= 4; $trimestre++) {
            $elaborazione->elabora($ferri, 2025, $trimestre, 0.0, false);
        }
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $ferri->studio;
        $dichiarazione->soggetto = $ferri;
        $dichiarazione->modello = ModelloDichiarativo::iva;
        $dichiarazione->anno = 2025;
        (new Frontespizio($this->dataMapper))->proponi($dichiarazione);
        $this->dataMapper->save($dichiarazione);
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $quadri);
        (new VersamentiIva($this->dataMapper))->registraSaldo($dichiarazione, $quadri);
        $this->addEntity($dichiarazione);
    }
}
