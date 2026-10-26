<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Models\NazioneModel;
use Telonio\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Persona fisica nata e residente all'estero, non cliente dello studio:
 * presente in anagrafica come socia di Alfa.
 */
class SoggettoBianchiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $francia = (new NazioneModel($this->dataMapper))->getByCodice('Z110');
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->studio = $this->getEntityByFixtureName(StudioFixture::class);
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->codiceFiscale = 'BNCLRA90E60Z110X';
        $soggetto->nominativo = 'BIANCHI LAURA';
        $soggetto->clienteStudio = false;
        $soggetto->cognome = 'Bianchi';
        $soggetto->nome = 'Laura';
        $soggetto->sesso = Sesso::femmina;
        $soggetto->dataNascita = new SismaDate('1990-05-20');
        $soggetto->nazioneNascita = $francia;
        $soggetto->cittadinanza = $francia;
        $this->addEntity($soggetto);
    }
}
