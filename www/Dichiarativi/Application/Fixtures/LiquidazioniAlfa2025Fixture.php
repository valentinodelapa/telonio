<?php

namespace Dichiarativi\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Contabilita\Application\Enumerations\StatoComunicazioneLipe;
use Contabilita\Application\Services\ElaborazioneLiquidazione;
use Contabilita\Application\Services\Lipe\PreparazioneLipe;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Liquidazioni IVA mensili demo di Alfa per il 2025 (tutte a credito,
 * senza giroconto) e comunicazioni delle liquidazioni periodiche dei primi
 * tre trimestri inviate: la dichiarazione compila il quadro VH solo per il
 * quarto trimestre, la cui comunicazione non risulta inviata.
 */
class LiquidazioniAlfa2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(OperazioniAlfa2025Fixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $alfa = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $elaborazione = new ElaborazioneLiquidazione($this->dataMapper);
        $liquidazione = null;
        for ($mese = 1; $mese <= 12; $mese++) {
            $liquidazione = $elaborazione->elabora($alfa, 2025, $mese, 0.0, false);
        }
        $preparazione = new PreparazioneLipe($this->dataMapper);
        foreach ([1 => ['2025-05-28', '25052812345678901'], 2 => ['2025-09-29', '25092912345678902'], 3 => ['2025-11-27', '25112712345678903']] as $trimestre => [$data, $protocollo]) {
            $comunicazione = $preparazione->prepara($alfa, 2025, $trimestre);
            $comunicazione->stato = StatoComunicazioneLipe::inviata;
            $comunicazione->dataInvio = new SismaDate($data);
            $comunicazione->protocollo = $protocollo;
            $this->dataMapper->save($comunicazione);
        }
        $this->addEntity($liquidazione);
    }
}
