<?php

namespace GestioneStudio\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoVerdiFixture;
use Telonio\Application\Fixtures\UtenteOperatoreFixture;
use GestioneStudio\Application\Entities\AssegnazioneAdempimento;
use GestioneStudio\Application\Models\AdempimentoModel;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * IVA trimestrale dell'impresa familiare Verdi. L'adempimento è del calendario fiscale: richiede
 * GestioneStudio/Database/dati-riferimento.sql.
 */
class AssegnazioneIvaTrimestraleVerdiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoVerdiFixture::class)
                ->addDependency(UtenteOperatoreFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $soggetto = $this->getEntityByFixtureName(SoggettoVerdiFixture::class);
        $assegnazione = new AssegnazioneAdempimento($this->dataMapper);
        $assegnazione->studio = $soggetto->studio;
        $assegnazione->soggetto = $soggetto;
        $assegnazione->adempimento = (new AdempimentoModel($this->dataMapper))->getByStudioAndCodice(null, 'IVA_TRIMESTRALE')[0];
        $assegnazione->responsabile = $this->getEntityByFixtureName(UtenteOperatoreFixture::class);
        $assegnazione->validoDal = new SismaDate('2024-01-01');
        $this->addEntity($assegnazione);
    }
}
