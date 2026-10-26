<?php

namespace Anagrafica\Application\Entities;

use Anagrafica\Application\Enumerations\NaturaGiuridica;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeFiscale;
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
 *
 * Con la partita IVA si indicano il regime fiscale e, se il regime prevede
 * le liquidazioni dell'IVA, la periodicità; inoltre se il soggetto applica
 * gli ISA (anche con cause di esclusione), se è sostituto d'imposta e, per
 * le società di capitali, se ha optato per la trasparenza fiscale (artt.
 * 115-116 TUIR).
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
    protected ?RegimeFiscale $regimeFiscale = null;
    protected ?PeriodicitaIva $periodicitaIva = null;
    protected bool $soggettoIsa = false;
    protected bool $sostitutoImposta = false;
    protected bool $trasparenzaFiscale = false;

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
