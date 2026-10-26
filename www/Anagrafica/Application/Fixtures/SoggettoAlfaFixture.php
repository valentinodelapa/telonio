<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Telonio\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Società di capitali cliente dello studio, con storico di denominazione,
 * sede legale e compagine sociale.
 *
 * Nominativo e partita IVA sono i valori correnti, coerenti con
 * DatiFiscaliAlfaVariazioneFixture.
 */
class SoggettoAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->studio = $this->getEntityByFixtureName(StudioFixture::class);
        $soggetto->tipoSoggetto = TipoSoggetto::societaCapitali;
        $soggetto->codice = 'C002';
        $soggetto->codiceFiscale = '01234567897';
        $soggetto->nominativo = 'Alfa Consulting S.r.l.';
        $soggetto->partitaIva = '01234567897';
        $soggetto->clienteStudio = true;
        $soggetto->dataCostituzione = new SismaDate('2020-01-15');
        $this->addEntity($soggetto);
    }
}
