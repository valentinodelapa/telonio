<?php

namespace GestioneStudio\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoVerdiFixture;
use GestioneStudio\Application\Entities\AssegnazioneAdempimento;
use GestioneStudio\Application\Models\AdempimentoModel;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Contributi IVS di Verdi, senza responsabile. L'adempimento è del calendario fiscale: richiede
 * GestioneStudio/Database/dati-riferimento.sql.
 */
class AssegnazioneInpsVerdiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoVerdiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $soggetto = $this->getEntityByFixtureName(SoggettoVerdiFixture::class);
        $assegnazione = new AssegnazioneAdempimento($this->dataMapper);
        $assegnazione->studio = $soggetto->studio;
        $assegnazione->soggetto = $soggetto;
        $assegnazione->adempimento = (new AdempimentoModel($this->dataMapper))->getByStudioAndCodice(null, 'INPS_IVS')[0];
        $assegnazione->validoDal = new SismaDate('2024-01-01');
        $this->addEntity($assegnazione);
    }
}
