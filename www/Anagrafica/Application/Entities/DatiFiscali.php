<?php

namespace Anagrafica\Application\Entities;

use Anagrafica\Application\Enumerations\NaturaGiuridica;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Anagrafica\Application\Interfaces\Storicizzabile;
use Anagrafica\Application\Traits\StoricizzabileTrait;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Versione storicizzata dei dati fiscali di un soggetto: denominazione,
 * forma giuridica, partita IVA e attività esercitata.
 *
 * Per le persone fisiche la natura giuridica non è prevista: con partita
 * IVA si indica il tipo di attività (impresa individuale, impresa
 * familiare, lavoro autonomo) e facoltativamente la denominazione della
 * ditta; una versione senza attività indica che la persona non esercita
 * (più) attività economica, ad esempio dopo la cessazione.
 */
class DatiFiscali extends BaseEntity implements Storicizzabile
{

    use StoricizzabileTrait;

    protected int $id;
    protected Soggetto $soggetto;
    protected SismaDate $validoDal;
    protected ?SismaDate $validoAl = null;
    protected ?string $denominazione = null;
    protected ?NaturaGiuridica $naturaGiuridica = null;
    protected ?TipoAttivita $tipoAttivita = null;
    protected ?string $partitaIva = null;
    protected ?CodiceAteco $codiceAteco = null;
    protected ?string $descrizioneAttivita = null;
    protected bool $enteNonCommerciale = false;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->validoDal = new SismaDate();
    }
}
