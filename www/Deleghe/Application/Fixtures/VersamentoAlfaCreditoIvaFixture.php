<?php

namespace Deleghe\Application\Fixtures;

use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Models\CodiceTributoModel;
use SismaFramework\Core\BaseClasses\BaseFixture;

class VersamentoAlfaCreditoIvaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(DelegaAlfaFixture::class)
                ->addDependency(CreditoAlfaIvaFixture::class);
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
        $riga->ordine = 2;
        $riga->codiceTributo = (new CodiceTributoModel($this->dataMapper))->getByCodice(SezioneCodiceTributo::erario, '6099');
        $riga->rateazione = '0101';
        $riga->annoRiferimento = 2025;
        $riga->importoCredito = 1500.00;
        $riga->credito = $this->getEntityByFixtureName(CreditoAlfaIvaFixture::class);
        $riga->descrizione = 'Credito IVA 2025 da dichiarazione annuale';
        $this->addEntity($riga);
    }
}
