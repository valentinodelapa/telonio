<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Models\NazioneModel;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Residenza estera: località al posto del comune.
 */
class ResidenzaBianchiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoBianchiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $indirizzo = new Indirizzo($this->dataMapper);
        $indirizzo->soggetto = $this->getEntityByFixtureName(SoggettoBianchiFixture::class);
        $indirizzo->tipoIndirizzo = TipoIndirizzo::residenza;
        $indirizzo->validoDal = new SismaDate('2015-01-01');
        $indirizzo->indirizzo = 'Rue de Rivoli';
        $indirizzo->numeroCivico = '5';
        $indirizzo->cap = '75001';
        $indirizzo->localitaEstera = 'Paris';
        $indirizzo->nazione = (new NazioneModel($this->dataMapper))->getByCodice('Z110');
        $this->addEntity($indirizzo);
    }
}
