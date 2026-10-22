<?php

namespace Successioni\Application\Fixtures;

use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Successioni\Application\Entities\PassivitaSuccessione;
use Successioni\Application\Enumerations\TipoPassivita;

/**
 * Spese funerarie demo (deducibili fino a 1.033 euro).
 */
class PassivitaFuneraleEspositoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(DichiarazioneEspositoFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $passivita = new PassivitaSuccessione($this->dataMapper);
        $passivita->dichiarazioneSuccessione = $this->getEntityByFixtureName(DichiarazioneEspositoFixture::class);
        $passivita->ordine = 1;
        $passivita->tipoPassivita = TipoPassivita::speseFunerarie;
        $passivita->descrizione = 'Onoranze funebri';
        $passivita->estremiDocumento = 'Fattura n. 245/2025';
        $passivita->dataDocumento = new SismaDate('2025-09-16');
        $passivita->valore = 1033;
        $this->addEntity($passivita);
    }
}
