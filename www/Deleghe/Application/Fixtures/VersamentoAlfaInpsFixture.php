<?php

namespace Deleghe\Application\Fixtures;

use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneF24;
use SismaFramework\Core\BaseClasses\BaseFixture;

class VersamentoAlfaInpsFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(DelegaAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $riga = new Versamento($this->dataMapper);
        $riga->delega = $this->getEntityByFixtureName(DelegaAlfaFixture::class);
        $riga->studio = $riga->delega->studio;
        $riga->soggetto = $riga->delega->soggetto;
        $riga->scadenza = $riga->delega->scadenza;
        $riga->sezione = SezioneF24::inps;
        $riga->ordine = 1;
        $riga->codiceSede = '7000';
        $riga->causale = 'DM10';
        $riga->posizione = '7012345678';
        $riga->periodoDa = '10/2026';
        $riga->importoDebito = 2340.50;
        $this->addEntity($riga);
    }
}
