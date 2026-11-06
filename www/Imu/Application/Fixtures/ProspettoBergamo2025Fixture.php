<?php

namespace Imu\Application\Fixtures;

use Imu\Application\Entities\ProspettoAliquote;
use SismaFramework\Core\BaseClasses\BaseFixture;
use Telonio\Application\Fixtures\StudioFixture;

/**
 * Aliquote demo inserite dallo studio per Bergamo, solo per il 2025: per il
 * 2026 il calcolo applica queste (comma 767, nessun prospetto dell'anno).
 */
class ProspettoBergamo2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $prospetto = new ProspettoAliquote($this->dataMapper);
        $prospetto->studio = $this->getEntityByFixtureName(StudioFixture::class);
        $prospetto->codiceCatastale = 'A794';
        $prospetto->denominazioneComune = 'Bergamo';
        $prospetto->anno = 2025;
        $prospetto->aliquotaAbitazionePrincipale = 0.6;
        $prospetto->aliquotaRuraliStrumentali = 0.1;
        $prospetto->aliquotaGruppoD = 1.06;
        $prospetto->aliquotaTerreniAgricoli = 1.06;
        $prospetto->aliquotaAreeFabbricabili = 1.06;
        $prospetto->aliquotaAltriFabbricati = 1.06;
        $prospetto->note = 'Aliquote demo: differenziate per il comodato ai parenti e per i pensionati residenti all\'estero';
        $this->addEntity($prospetto);
    }
}
