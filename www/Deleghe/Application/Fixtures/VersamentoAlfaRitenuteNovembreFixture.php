<?php

namespace Deleghe\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use CommSuite\Application\Fixtures\StudioFixture;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Models\CodiceTributoModel;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Da versare: ritenute di novembre della società, per la delega di dicembre.
 */
class VersamentoAlfaRitenuteNovembreFixture extends BaseFixture
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
        $versamento = new Versamento($this->dataMapper);
        $versamento->studio = $this->getEntityByFixtureName(StudioFixture::class);
        $versamento->soggetto = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $versamento->scadenza = new SismaDate('2026-12-16');
        $versamento->descrizione = 'Ritenute lavoro autonomo di novembre';
        $versamento->sezione = SezioneF24::erario;
        $versamento->codiceTributo = (new CodiceTributoModel($this->dataMapper))->getByCodice(SezioneCodiceTributo::erario, '1040');
        $versamento->rateazione = '0011';
        $versamento->annoRiferimento = 2026;
        $versamento->importoDebito = 1650.00;
        $this->addEntity($versamento);
    }
}
