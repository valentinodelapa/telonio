<?php

namespace Deleghe\Application\Fixtures;

use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Models\CodiceTributoModel;
use SismaFramework\Core\BaseClasses\BaseFixture;

class VersamentoVerdiImuFixture extends BaseFixture
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
        $riga->sezione = SezioneF24::tributiLocali;
        $riga->ordine = 1;
        $riga->codiceTributo = (new CodiceTributoModel($this->dataMapper))->getByCodice(SezioneCodiceTributo::tributiLocali, '3918');
        $riga->codiceEnte = 'F205';
        $riga->saldo = true;
        $riga->numeroImmobili = 1;
        $riga->annoRiferimento = 2026;
        $riga->importoDebito = 374.94;
        $riga->origine = 'Imu';
        $riga->riferimentoOrigine = 'imu-2026';
        $this->addEntity($riga);
    }
}
