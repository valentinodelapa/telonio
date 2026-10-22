<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\Legame;
use Anagrafica\Application\Enumerations\RuoloLegame;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Neri collaboratrice nell'impresa familiare di Verdi, con quota di
 * partecipazione agli utili.
 */
class CollaboratoreNeriVerdiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoVerdiFixture::class)
                ->addDependency(SoggettoNeriFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $legame = new Legame($this->dataMapper);
        $legame->soggetto = $this->getEntityByFixtureName(SoggettoVerdiFixture::class);
        $legame->soggettoCollegato = $this->getEntityByFixtureName(SoggettoNeriFixture::class);
        $legame->ruolo = RuoloLegame::collaboratoreFamiliare;
        $legame->validoDal = new SismaDate('2023-01-01');
        $legame->quotaPartecipazione = 49.0;
        $this->addEntity($legame);
    }
}
