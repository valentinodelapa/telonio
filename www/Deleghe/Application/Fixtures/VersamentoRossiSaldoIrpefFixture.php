<?php

namespace Deleghe\Application\Fixtures;

use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Models\CodiceTributoModel;
use SismaFramework\Core\BaseClasses\BaseFixture;

class VersamentoRossiSaldoIrpefFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(DelegaRossiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $riga = new Versamento($this->dataMapper);
        $riga->delega = $this->getEntityByFixtureName(DelegaRossiFixture::class);
        $riga->studio = $riga->delega->studio;
        $riga->soggetto = $riga->delega->soggetto;
        $riga->scadenza = $riga->delega->scadenza;
        $riga->sezione = SezioneF24::erario;
        $riga->ordine = 1;
        $riga->codiceTributo = (new CodiceTributoModel($this->dataMapper))->getByCodice(SezioneCodiceTributo::erario, '4001');
        $riga->rateazione = '0101';
        $riga->annoRiferimento = 2025;
        $riga->importoDebito = 1250.00;
        $this->addEntity($riga);
    }
}
