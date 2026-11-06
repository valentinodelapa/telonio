<?php

namespace Imu\Application\Fixtures;

use Imu\Application\Entities\AliquotaDifferenziata;
use Imu\Application\Enumerations\FattispecieImu;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Aliquota differenziata demo: pensionati residenti all'estero.
 */
class DifferenziataPensionatoBergamoFixture extends BaseFixture
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
        $differenziata->descrizione = 'Aliquota per l\'unica unità immobiliare posseduta dai cittadini italiani pensionati, non residenti nel territorio dello Stato e titolari di pensione maturata in regime di convenzione con l\'Italia, a titolo di proprietà o usufrutto in Italia, a condizione che non risulti locata o comunque occupata';
        $differenziata->aliquota = 0.46;
        $differenziata->ordine = 2;
        $this->addEntity($differenziata);
    }
}
