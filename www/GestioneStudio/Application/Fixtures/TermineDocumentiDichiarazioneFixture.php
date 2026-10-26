<?php

namespace GestioneStudio\Application\Fixtures;

use GestioneStudio\Application\Entities\TermineAdempimento;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Documenti dell'anno di riferimento entro il 15 maggio dell'anno
 * successivo.
 */
class TermineDocumentiDichiarazioneFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(AdempimentoDocumentiDichiarazioneFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $termine = new TermineAdempimento($this->dataMapper);
        $termine->adempimento = $this->getEntityByFixtureName(AdempimentoDocumentiDichiarazioneFixture::class);
        $termine->ordine = 1;
        $termine->periodo = 'anno';
        $termine->mese = 5;
        $termine->giorno = 15;
        $termine->anniSuccessivi = 1;
        $this->addEntity($termine);
    }
}
