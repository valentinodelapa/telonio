<?php

namespace Deleghe\Application\Entities;

use Deleghe\Application\Enumerations\DichiarazioneRiferimento;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Codici con cui versare sanzione e interessi del ravvedimento di un
 * tributo (es. IRPEF 4001: sanzione 8901, interessi 1989), con la
 * dichiarazione che ne delimita la riduzione a 1/8. Un codice null indica
 * che l'importo si somma al tributo (interessi sulle ritenute, IMU).
 *
 * Abbinamento curato a mano in Database/sorgenti/regole-ravvedimento.csv con
 * la fonte (risoluzioni dell'Agenzia): nessuna tabella ufficiale lo riporta.
 */
class RegolaRavvedimento extends BaseEntity
{

    protected int $id;
    protected SezioneCodiceTributo $sezione;
    protected string $codice;
    protected ?string $codiceSanzione = null;
    protected ?string $codiceInteressi = null;
    protected DichiarazioneRiferimento $dichiarazione;
    protected string $fonte;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
