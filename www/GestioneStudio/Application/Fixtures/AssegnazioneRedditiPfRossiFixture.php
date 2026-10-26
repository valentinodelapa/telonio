<?php

namespace GestioneStudio\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoRossiFixture;
use CommSuite\Application\Fixtures\UtenteFixture;
use GestioneStudio\Application\Entities\AssegnazioneAdempimento;
use GestioneStudio\Application\Models\AdempimentoModel;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dichiarazione dei redditi di Rossi. L'adempimento è del calendario fiscale: richiede
 * GestioneStudio/Database/dati-riferimento.sql.
 */
class AssegnazioneRedditiPfRossiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoRossiFixture::class)
                ->addDependency(UtenteFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $soggetto = $this->getEntityByFixtureName(SoggettoRossiFixture::class);
        $assegnazione = new AssegnazioneAdempimento($this->dataMapper);
        $assegnazione->studio = $soggetto->studio;
        $assegnazione->soggetto = $soggetto;
        $assegnazione->adempimento = (new AdempimentoModel($this->dataMapper))->getByStudioAndCodice(null, 'REDDITI_PF')[0];
        $assegnazione->responsabile = $this->getEntityByFixtureName(UtenteFixture::class);
        $assegnazione->validoDal = new SismaDate('2024-01-01');
        $this->addEntity($assegnazione);
    }
}
