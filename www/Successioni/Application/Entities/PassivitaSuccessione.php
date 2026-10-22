<?php

namespace Successioni\Application\Entities;

use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;
use Successioni\Application\Enumerations\TipoPassivita;

/**
 * Passività od onere del quadro ED (artt. 20-24 TUS), con il documento che
 * lo prova (da allegare nel quadro EG), la quota del defunto e il valore
 * deducibile; la ripartizione tra i beneficiari è in RipartizionePassivita.
 * Mutui e debiti bancari o d'impresa si collegano al cespite.
 */
class PassivitaSuccessione extends ReferencedEntity
{

    protected int $id;
    protected DichiarazioneSuccessione $dichiarazioneSuccessione;
    protected int $ordine = 1;
    protected TipoPassivita $tipoPassivita;
    protected string $descrizione;
    protected string $estremiDocumento;
    protected SismaDate $dataDocumento;
    protected ?int $quotaNumeratore = 1;
    protected ?int $quotaDenominatore = 1;
    protected int $valore = 0;
    protected ?BeneSuccessione $beneSuccessione = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
