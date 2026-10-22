<?php

namespace Deleghe\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoVerdiFixture;
use CommSuite\Application\Fixtures\StudioFixture;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Models\CodiceTributoModel;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Da versare e scaduto: acconto IMU 2026 del negozio, non versato a giugno
 * (si regolarizza con il ravvedimento).
 */
class VersamentoVerdiImuAccontoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class)
                ->addDependency(SoggettoVerdiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $versamento = new Versamento($this->dataMapper);
        $versamento->studio = $this->getEntityByFixtureName(StudioFixture::class);
        $versamento->soggetto = $this->getEntityByFixtureName(SoggettoVerdiFixture::class);
        $versamento->scadenza = new SismaDate('2026-06-16');
        $versamento->descrizione = 'IMU acconto 2026 negozio';
        $versamento->sezione = SezioneF24::tributiLocali;
        $versamento->codiceTributo = (new CodiceTributoModel($this->dataMapper))->getByCodice(SezioneCodiceTributo::tributiLocali, '3918');
        $versamento->codiceEnte = 'F205';
        $versamento->acconto = true;
        $versamento->numeroImmobili = 1;
        $versamento->annoRiferimento = 2026;
        $versamento->importoDebito = 374.94;
        $versamento->origine = 'Imu';
        $versamento->riferimentoOrigine = 'imu-2026';
        $this->addEntity($versamento);
    }
}
