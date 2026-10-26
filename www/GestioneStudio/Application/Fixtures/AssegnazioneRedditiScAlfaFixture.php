<?php

namespace GestioneStudio\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Telonio\Application\Fixtures\UtenteOperatoreFixture;
use GestioneStudio\Application\Entities\AssegnazioneAdempimento;
use GestioneStudio\Application\Models\AdempimentoModel;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dichiarazione dei redditi di Alfa, seguita dall'operatore. L'adempimento è del calendario fiscale: richiede
 * GestioneStudio/Database/dati-riferimento.sql.
 */
class AssegnazioneRedditiScAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(UtenteOperatoreFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $soggetto = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $assegnazione = new AssegnazioneAdempimento($this->dataMapper);
        $assegnazione->studio = $soggetto->studio;
        $assegnazione->soggetto = $soggetto;
        $assegnazione->adempimento = (new AdempimentoModel($this->dataMapper))->getByStudioAndCodice(null, 'REDDITI_SC')[0];
        $assegnazione->responsabile = $this->getEntityByFixtureName(UtenteOperatoreFixture::class);
        $assegnazione->validoDal = new SismaDate('2024-01-01');
        $this->addEntity($assegnazione);
    }
}
