<?php

namespace Deleghe\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoRossiFixture;
use Telonio\Application\Fixtures\StudioFixture;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Models\CodiceTributoModel;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Da versare: secondo acconto IRPEF 2026.
 */
class VersamentoRossiSecondoAccontoIrpefFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class)
                ->addDependency(SoggettoRossiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $versamento = new Versamento($this->dataMapper);
        $versamento->studio = $this->getEntityByFixtureName(StudioFixture::class);
        $versamento->soggetto = $this->getEntityByFixtureName(SoggettoRossiFixture::class);
        $versamento->scadenza = new SismaDate('2026-11-30');
        $versamento->descrizione = 'Secondo acconto IRPEF 2026';
        $versamento->sezione = SezioneF24::erario;
        $versamento->codiceTributo = (new CodiceTributoModel($this->dataMapper))->getByCodice(SezioneCodiceTributo::erario, '4034');
        $versamento->annoRiferimento = 2026;
        $versamento->importoDebito = 1290.00;
        $this->addEntity($versamento);
    }
}
