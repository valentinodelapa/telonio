<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Enumerations\CodiceCaricaIva;
use Contabilita\Application\Enumerations\ImpegnoPresentazione;
use Contabilita\Application\Enumerations\StatoComunicazioneLipe;
use Deleghe\Application\Entities\Intermediario;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;
use Telonio\Application\Entities\Studio;

/**
 * Comunicazione delle liquidazioni periodiche IVA del trimestre (art.
 * 21-bis D.L. 78/2010), da trasmettere entro l'ultimo giorno del secondo
 * mese successivo al trimestre (il quarto entro la fine di febbraio):
 * frontespizio con dichiarante e intermediario e un modulo per mese
 * (mensili) o uno per il trimestre (ModuloLipe), con i dati delle
 * liquidazioni elaborate.
 *
 * Una sola comunicazione per trimestre: per ritrasmetterla (sostitutiva
 * entro il termine) la si riporta in bozza e la si riprepara.
 */
class ComunicazioneLipe extends ReferencedEntity
{

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected int $anno;
    protected int $trimestre;
    protected int $progressivo = 1;
    protected StatoComunicazioneLipe $stato = StatoComunicazioneLipe::bozza;
    protected ?Soggetto $dichiarante = null;
    protected ?CodiceCaricaIva $codiceCarica = null;
    protected ?Intermediario $intermediario = null;
    protected ?ImpegnoPresentazione $impegno = null;
    protected ?SismaDate $dataImpegno = null;
    protected ?SismaDate $dataInvio = null;
    protected ?string $protocollo = null;
    protected SismaDateTime $dataElaborazione;
    protected ?string $avvisi = null;
    protected ?GruppoIva $gruppoIva = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataElaborazione = new SismaDateTime();
    }

    public function isModificabile(): bool
    {
        return $this->stato === StatoComunicazioneLipe::bozza;
    }

    public function getDescrizione(): string
    {
        return $this->trimestre . '° trimestre ' . $this->anno . ($this->gruppoIva === null ? '' : ' (liquidazione del gruppo)');
    }

    /**
     * Identificativo della comunicazione nella fornitura (5 cifre, non nullo).
     */
    public function getIdentificativo(): string
    {
        return sprintf('%05d', (($this->progressivo - 1) % 99999) + 1);
    }
}
