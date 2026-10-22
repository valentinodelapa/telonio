<?php

namespace Successioni\Application\Entities;

use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use Successioni\Application\Enumerations\TipoAllegato;

/**
 * Documento allegato alla dichiarazione (quadro EG): PDF/A o TIFF, inserito
 * nel file telematico. Il contenuto è in ContenutoAllegato.
 */
class AllegatoSuccessione extends BaseEntity
{

    public const DIMENSIONE_MASSIMA = 5242880;

    protected int $id;
    protected DichiarazioneSuccessione $dichiarazioneSuccessione;
    protected TipoAllegato $tipoAllegato;
    protected string $nomeFile;
    protected ?string $descrizione = null;
    protected string $tipoMime;
    protected int $dimensione;
    protected ContenutoAllegato $contenutoAllegato;
    protected SismaDateTime $dataInserimento;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataInserimento = new SismaDateTime();
    }
}
