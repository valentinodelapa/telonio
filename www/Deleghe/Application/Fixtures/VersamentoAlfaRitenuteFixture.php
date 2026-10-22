<?php

namespace Deleghe\Application\Fixtures;

use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Models\CodiceTributoModel;
use SismaFramework\Core\BaseClasses\BaseFixture;

class VersamentoAlfaRitenuteFixture extends BaseFixture
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
        $riga->sezione = SezioneF24::erario;
        $riga->ordine = 1;
        $riga->codiceTributo = (new CodiceTributoModel($this->dataMapper))->getByCodice(SezioneCodiceTributo::erario, '1040');
        $riga->rateazione = '0010';
        $riga->annoRiferimento = 2026;
        $riga->importoDebito = 1800.00;
        $this->addEntity($riga);
    }
}
