<?php

namespace Imu\Application\Fixtures;

use Imu\Application\Entities\UtilizzoMistoEnc;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Utilizzo 2025 demo: attività didattiche e culturali, aule usate per corsi a pagamento per il 15% della superficie; corrispettivo medio inferiore al costo medio per studente.
 */
class UtilizzoSedeAssociazione2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAssociazioneFixture::class)
                ->addDependency(ImmobileSedeAssociazioneFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $utilizzo = new UtilizzoMistoEnc($this->dataMapper);
        $utilizzo->soggetto = $this->getEntityByFixtureName(SoggettoAssociazioneFixture::class);
        $utilizzo->immobile = $this->getEntityByFixtureName(ImmobileSedeAssociazioneFixture::class);
        $utilizzo->anno = 2025;
        $utilizzo->attivita = 'DI,CU';
        $utilizzo->didatticaCorrispettivoMedio = 3800.0;
        $utilizzo->didatticaCostoMedioStudente = 5800.0;
        $utilizzo->didatticaSuperficie = 15.0;
        $this->addEntity($utilizzo);
    }
}
