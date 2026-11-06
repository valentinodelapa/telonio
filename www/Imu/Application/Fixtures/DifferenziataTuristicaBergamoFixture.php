<?php

namespace Imu\Application\Fixtures;

use Imu\Application\Entities\AliquotaDifferenziata;
use Imu\Application\Enumerations\FattispecieImu;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Aliquota differenziata demo: condizione non ricavabile dai dati, suggerita negli avvisi.
 */
class DifferenziataTuristicaBergamoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ProspettoBergamo2025Fixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $differenziata = new AliquotaDifferenziata($this->dataMapper);
        $differenziata->prospettoAliquote = $this->getEntityByFixtureName(ProspettoBergamo2025Fixture::class);
        $differenziata->fattispecie = FattispecieImu::altriFabbricati;
        $differenziata->descrizione = 'Abitazione destinata a struttura turistico-ricettiva';
        $differenziata->aliquota = 0.96;
        $differenziata->ordine = 3;
        $this->addEntity($differenziata);
    }
}
