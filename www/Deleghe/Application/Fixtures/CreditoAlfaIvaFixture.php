<?php

namespace Deleghe\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Telonio\Application\Fixtures\StudioFixture;
use Deleghe\Application\Entities\Credito;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Models\CodiceTributoModel;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Credito IVA annuale 2025 della società, oltre 5.000 euro: utilizzabile dal
 * decimo giorno successivo alla presentazione della dichiarazione.
 */
class CreditoAlfaIvaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class)
                ->addDependency(SoggettoAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $credito = new Credito($this->dataMapper);
        $credito->studio = $this->getEntityByFixtureName(StudioFixture::class);
        $credito->soggetto = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $credito->sezione = SezioneF24::erario;
        $credito->codiceTributo = (new CodiceTributoModel($this->dataMapper))->getByCodice(SezioneCodiceTributo::erario, '6099');
        $credito->rateazione = '0101';
        $credito->annoRiferimento = 2025;
        $credito->importo = 8000.00;
        $credito->utilizzabileDal = new SismaDate('2026-05-11');
        $credito->descrizione = 'Credito IVA 2025 da dichiarazione annuale';
        $this->addEntity($credito);
    }
}
