<?php

namespace Dichiarativi\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Enumerations\CodiceCaricaIva;
use Contabilita\Application\Enumerations\ImpegnoPresentazione;
use Deleghe\Application\Entities\Intermediario;
use Dichiarativi\Application\Enumerations\ModalitaSaldo;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Enumerations\StatoDichiarazione;
use Dichiarativi\Application\Enumerations\TipologiaDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;
use Telonio\Application\Entities\Studio;

/**
 * Dichiarazione di un contribuente per modello e anno d'imposta: dati del
 * frontespizio (tipo, dichiarante, intermediario, impegno alla
 * presentazione) e stato di presentazione. I dati dei quadri sono in
 * ValoreDichiarazione, uno per campo del modello.
 *
 * Una sola dichiarazione per contribuente, modello e anno: la correttiva o
 * l'integrativa si predispone riportando in bozza quella presentata e
 * cambiandone il tipo.
 */
class Dichiarazione extends ReferencedEntity
{

    public const ORIGINE = 'Dichiarativi';

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected ModelloDichiarativo $modello;
    protected int $anno;
    protected ?int $trimestre = null;
    protected TipologiaDichiarazione $tipologia = TipologiaDichiarazione::ordinaria;
    protected StatoDichiarazione $stato = StatoDichiarazione::bozza;
    protected ?int $naturaGiuridica = null;
    protected ?Soggetto $dichiarante = null;
    protected ?CodiceCaricaIva $codiceCarica = null;
    protected ?Intermediario $intermediario = null;
    protected ?ImpegnoPresentazione $impegno = null;
    protected ?SismaDate $dataImpegno = null;
    protected bool $flagConferma = false;
    protected ?Soggetto $vistoProfessionista = null;
    protected ?Soggetto $vistoCaf = null;
    protected ?Soggetto $vistoResponsabileCaf = null;
    protected bool $esoneroVisto = false;
    protected bool $attestazionePatrimoniale = false;
    protected bool $adempimentoCollaborativo = false;
    protected bool $concordatoBiennale = false;
    protected ModalitaSaldo $modalitaSaldo = ModalitaSaldo::unicaSoluzione;
    protected ?int $numeroRate = null;
    protected ?SismaDate $dataDifferimento = null;
    protected bool $maggiorazioneUlteriore = false;
    protected ?SismaDate $dataPresentazione = null;
    protected ?string $protocollo = null;
    protected ?SismaDateTime $dataElaborazione = null;
    protected ?string $avvisi = null;
    protected ?string $annotazioni = null;
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

    public function isModificabile(): bool
    {
        return $this->stato === StatoDichiarazione::bozza;
    }

    public function getAnnoModello(): int
    {
        return ModelloDichiarativo::getAnnoModello($this->anno, $this->modello);
    }

    public function getDescrizione(): string
    {
        if ($this->modello->isTrimestrale()) {
            return $this->modello->getSigla() . ' ' . $this->trimestre . '° trimestre ' . $this->anno;
        }
        return $this->modello->getSigla() . ' ' . $this->getAnnoModello() . ' (anno ' . $this->anno . ')';
    }

    public function getScadenza(): SismaDate
    {
        return $this->modello->getScadenza($this->anno, $this->trimestre);
    }

    /**
     * Riferimento dei versamenti e dei crediti registrati in Deleghe (es.
     * "iva-2025").
     */
    public function getRiferimento(): string
    {
        return mb_strtolower($this->modello->value) . '-' . $this->anno . ($this->trimestre === null ? '' : '-' . $this->trimestre);
    }

    /**
     * Visto di conformità apposto da un professionista o da un CAF.
     */
    public function hasVisto(): bool
    {
        return ($this->vistoProfessionista !== null) || ($this->vistoCaf !== null);
    }

    /**
     * @return string[]
     */
    public function getAvvisi(): array
    {
        return $this->avvisi === null ? [] : explode("\n", $this->avvisi);
    }

    /**
     * Codice dell'esonero dalla garanzia per i rimborsi (VX4 campo 7, VS
     * campo 8, TD8 campo 3 del modello IVA TR), dai dati della
     * dichiarazione; null se la garanzia non è esclusa.
     *
     * @param array<string, string> $codici codici del modello per visto con attestazione, adempimento collaborativo, ISA e concordato
     * @param bool $sottoscritta sottoscrizione dell'organo di controllo in alternativa al visto
     */
    public function getCodiceEsoneroGaranzie(array $codici, bool $sottoscritta = false): ?string
    {
        return match (true) {
            ($this->hasVisto() || $sottoscritta) && $this->attestazionePatrimoniale && isset($codici['visto']) => $codici['visto'],
            $this->adempimentoCollaborativo && isset($codici['collaborativo']) => $codici['collaborativo'],
            $this->esoneroVisto && isset($codici['isa']) => $codici['isa'],
            $this->concordatoBiennale && isset($codici['concordato']) => $codici['concordato'],
            default => null,
        };
    }
}
