<?php

namespace Contabilita\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Enumerations\TipoStampaDefinitiva;
use Contabilita\Application\Models\RegistroIvaModel;
use Contabilita\Application\Services\StampaDefinitiva\StampeDefinitive;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Stampa definitiva demo del registro delle vendite di Alfa per gennaio
 * 2026, con le integrazioni degli acquisti in inversione contabile.
 */
class StampaRegistroVenditeAlfaGennaioFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(StampaGiornaleAlfaGennaioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $alfa = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        foreach ((new RegistroIvaModel($this->dataMapper))->getPerTipo($alfa, TipoRegistroIva::vendite) as $registro) {
            if ($registro->sezionale === '') {
                $this->addEntity((new StampeDefinitive($this->dataMapper))->registra($alfa, TipoStampaDefinitiva::registroIva, $registro, 2026, new SismaDate('2026-01-31')));
                return;
            }
        }
        throw new \RuntimeException('Registro delle vendite di Alfa non trovato.');
    }
}
