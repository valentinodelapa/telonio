<?php

namespace Deleghe\Application\Fixtures;

use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneF24;
use SismaFramework\Core\BaseClasses\BaseFixture;

class VersamentoVerdiInpsArtigianiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(DelegaVerdiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $riga = new Versamento($this->dataMapper);
        $riga->delega = $this->getEntityByFixtureName(DelegaVerdiFixture::class);
        $riga->studio = $riga->delega->studio;
        $riga->soggetto = $riga->delega->soggetto;
        $riga->scadenza = $riga->delega->scadenza;
        $riga->sezione = SezioneF24::inps;
        $riga->ordine = 1;
        $riga->codiceSede = '4900';
        $riga->causale = 'AF';
        $riga->posizione = '49123456789000000';
        $riga->periodoDa = '08/2026';
        $riga->periodoA = '10/2026';
        $riga->importoDebito = 1146.00;
        $this->addEntity($riga);
    }
}
