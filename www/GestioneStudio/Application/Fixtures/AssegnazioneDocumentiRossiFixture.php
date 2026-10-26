<?php

namespace GestioneStudio\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoRossiFixture;
use Telonio\Application\Fixtures\UtenteOperatoreFixture;
use GestioneStudio\Application\Entities\AssegnazioneAdempimento;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Adempimento dello studio assegnato a Rossi.
 */
class AssegnazioneDocumentiRossiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoRossiFixture::class)
                ->addDependency(UtenteOperatoreFixture::class)
                ->addDependency(AdempimentoDocumentiDichiarazioneFixture::class)
                ->addDependency(TermineDocumentiDichiarazioneFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $soggetto = $this->getEntityByFixtureName(SoggettoRossiFixture::class);
        $assegnazione = new AssegnazioneAdempimento($this->dataMapper);
        $assegnazione->studio = $soggetto->studio;
        $assegnazione->soggetto = $soggetto;
        $assegnazione->adempimento = $this->getEntityByFixtureName(AdempimentoDocumentiDichiarazioneFixture::class);
        $assegnazione->responsabile = $this->getEntityByFixtureName(UtenteOperatoreFixture::class);
        $assegnazione->validoDal = new SismaDate('2025-01-01');
        $this->addEntity($assegnazione);
    }
}
