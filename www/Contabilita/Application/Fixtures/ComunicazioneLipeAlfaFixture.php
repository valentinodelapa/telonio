<?php

namespace Contabilita\Application\Fixtures;

use Anagrafica\Application\Fixtures\AmministratoreAlfaFixture;
use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Contabilita\Application\Enumerations\StatoComunicazioneLipe;
use Contabilita\Application\Services\Lipe\PreparazioneLipe;
use Deleghe\Application\Fixtures\IntermediarioStudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Comunicazione demo delle liquidazioni periodiche di Alfa (mensile) per
 * il primo trimestre 2026: tre moduli, dichiarante l'amministratore unico
 * (codice carica 1) e invio dell'intermediario dello studio, già inviata.
 */
class ComunicazioneLipeAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(AmministratoreAlfaFixture::class)
                ->addDependency(IntermediarioStudioFixture::class)
                ->addDependency(LiquidazioneAlfaMarzoFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $comunicazione = (new PreparazioneLipe($this->dataMapper))->prepara($this->getEntityByFixtureName(SoggettoAlfaFixture::class), 2026, 1);
        $comunicazione->dataImpegno = new SismaDate('2026-05-20');
        $comunicazione->stato = StatoComunicazioneLipe::inviata;
        $comunicazione->dataInvio = new SismaDate('2026-05-25');
        $comunicazione->protocollo = '26052512345678901';
        $this->addEntity($comunicazione);
    }
}
