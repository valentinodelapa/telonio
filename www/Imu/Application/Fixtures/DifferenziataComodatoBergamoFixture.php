<?php

namespace Imu\Application\Fixtures;

use Imu\Application\Entities\AliquotaDifferenziata;
use Imu\Application\Enumerations\FattispecieImu;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Aliquota differenziata demo: comodato ai parenti.
 */
class DifferenziataComodatoBergamoFixture extends BaseFixture
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
        $differenziata->descrizione = 'Aliquota per l\'abitazione concessa in comodato gratuito, ai parenti in linea retta, che la utilizzano come abitazione principale';
        $differenziata->aliquota = 0.76;
        $differenziata->ordine = 1;
        $this->addEntity($differenziata);
    }
}
