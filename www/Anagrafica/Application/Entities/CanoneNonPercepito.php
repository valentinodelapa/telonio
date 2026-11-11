<?php

namespace Anagrafica\Application\Entities;

use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Canoni di un anno scaduti e non percepiti, accertati nel procedimento
 * giurisdizionale di convalida di sfratto per morosità (art. 26 Tuir):
 * importo dell'intero contratto, come nel quadro RB, e data della
 * convalida. Le imposte pagate su quei canoni danno ai locatori il
 * credito d'imposta del rigo CR8, nella prima dichiarazione utile dopo la
 * convalida.
 */
class CanoneNonPercepito extends BaseEntity
{

    protected int $id;
    protected ContrattoLocazione $contrattoLocazione;
    protected int $anno;
    protected float $importo = 0.0;
    protected SismaDate $dataConvalida;
    protected ?string $note = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataConvalida = new SismaDate();
    }
}
