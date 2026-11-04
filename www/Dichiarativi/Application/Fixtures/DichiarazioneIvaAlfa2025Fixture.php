<?php

namespace Dichiarativi\Application\Fixtures;

use Anagrafica\Application\Fixtures\AmministratoreAlfaFixture;
use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Deleghe\Application\Fixtures\CreditoAlfaIvaFixture;
use Deleghe\Application\Fixtures\IntermediarioStudioFixture;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Enumerations\StatoDichiarazione;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\Iva\PropostaIva;
use Dichiarativi\Application\Services\Iva\VersamentiIva;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dichiarazione IVA 2026 demo di Alfa per l'anno 2025, proposta dalla
 * contabilità e presentata il 28 aprile 2026: credito di 8.000 euro da
 * compensare (VX5) con il visto di conformità, che adotta il credito IVA 2025 già inserito in
 * Deleghe F24.
 */
class DichiarazioneIvaAlfa2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(LiquidazioniAlfa2025Fixture::class)
                ->addDependency(CreditoAlfaIvaFixture::class)
                ->addDependency(IntermediarioStudioFixture::class)
                ->addDependency(AmministratoreAlfaFixture::class)
                ->addDependency(ProfessionistaMariniFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $alfa = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $alfa->studio;
        $dichiarazione->soggetto = $alfa;
        $dichiarazione->modello = ModelloDichiarativo::iva;
        $dichiarazione->anno = 2025;
        (new Frontespizio($this->dataMapper))->proponi($dichiarazione);
        $dichiarazione->dataImpegno = new SismaDate('2026-04-20');
        $dichiarazione->vistoProfessionista = $this->getEntityByFixtureName(ProfessionistaMariniFixture::class);
        $this->dataMapper->save($dichiarazione);
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $quadri);
        $dichiarazione->stato = StatoDichiarazione::presentata;
        $dichiarazione->dataPresentazione = new SismaDate('2026-04-28');
        $dichiarazione->protocollo = '26042812345678901';
        $this->dataMapper->save($dichiarazione);
        (new VersamentiIva($this->dataMapper))->registraCredito($dichiarazione, $quadri);
        $this->addEntity($dichiarazione);
    }
}
