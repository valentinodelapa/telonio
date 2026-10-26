<?php

namespace GestioneStudio\Application\Fixtures;

use CommSuite\Application\Fixtures\StudioFixture;
use GestioneStudio\Application\Entities\Adempimento;
use GestioneStudio\Application\Enumerations\AmbitoAdempimento;
use GestioneStudio\Application\Enumerations\CategoriaAdempimento;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Adempimento proprio dello studio demo: termine interno per la consegna
 * dei documenti della dichiarazione dei redditi.
 */
class AdempimentoDocumentiDichiarazioneFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $adempimento = new Adempimento($this->dataMapper);
        $adempimento->studio = $this->getEntityByFixtureName(StudioFixture::class);
        $adempimento->codice = 'DOCUMENTI_DICHIARAZIONE';
        $adempimento->denominazione = 'Consegna dei documenti per la dichiarazione dei redditi';
        $adempimento->categoria = CategoriaAdempimento::altro;
        $adempimento->ambito = AmbitoAdempimento::personeFisiche;
        $adempimento->note = 'Termine interno dello studio, comunicato ai clienti con la lettera di inizio campagna.';
        $this->addEntity($adempimento);
    }
}
