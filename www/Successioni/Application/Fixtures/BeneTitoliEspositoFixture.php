<?php

namespace Successioni\Application\Fixtures;

use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Enumerations\CodiceDiritto;
use Successioni\Application\Enumerations\QuadroBene;
use Successioni\Application\Enumerations\TipoDocumentoTitolo;
use Successioni\Application\Enumerations\TipoTitolo;

/**
 * Azioni quotate legate al nipote con il testamento.
 */
class BeneTitoliEspositoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(DichiarazioneEspositoFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $bene = new BeneSuccessione($this->dataMapper);
        $bene->dichiarazioneSuccessione = $this->getEntityByFixtureName(DichiarazioneEspositoFixture::class);
        $bene->quadro = QuadroBene::titoli;
        $bene->ordine = 1;
        $bene->tipoTitolo = TipoTitolo::quotato;
        $bene->codiceFiscaleEmittente = '00484960588';
        $bene->quantita = 3500;
        $bene->codiceTitolo = 'IT0003132476';
        $bene->descrizione = 'Azioni ordinarie ENI S.p.A., valore di borsa al 12/09/2025';
        $bene->tipoDocumento = TipoDocumentoTitolo::altro;
        $bene->dataDocumento = new SismaDate('2025-09-12');
        $bene->valore = 50000;
        $this->addEntity($bene);
    }
}
