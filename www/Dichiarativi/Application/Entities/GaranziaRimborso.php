<?php

namespace Dichiarativi\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\Enumerations\TipoGaranzia;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Application\Entities\Studio;

/**
 * Garanzia prestata per un rimborso IVA (art. 38-bis, c. 5, D.P.R. 633/1972):
 * dura tre anni dall'esecuzione del rimborso o, se inferiore, il periodo
 * mancante alla decadenza dell'accertamento (art. 57: 31 dicembre del quinto
 * anno successivo alla presentazione della dichiarazione dell'anno; dal 2027
 * art. 293, c. 1, D.Lgs. 141/2026).
 *
 * Se la consegna dei documenti chiesti per il rimborso supera i quindici giorni
 * dalla notifica, la decadenza è differita del periodo dal sedicesimo giorno alla
 * consegna (art. 57, c. 3; art. 293, c. 5, D.Lgs. 141/2026).
 *
 * Con l'IVA di gruppo la garanzia copre anche le eccedenze compensate nella
 * liquidazione di gruppo (art. 6, c. 3, D.M. 13 dicembre 1979), prestata alla
 * presentazione della dichiarazione: non c'è un rimborso eseguito e la durata
 * decorre da quella data.
 */
class GaranziaRimborso extends BaseEntity
{

    /** Anni dall'esecuzione del rimborso */
    public const DURATA_ANNI = 3;

    /** Anni dall'anno d'imposta alla decadenza: presentazione nell'anno successivo più cinque */
    public const ANNI_DECADENZA = 6;

    /** Giorni dalla notifica della richiesta di documenti entro cui la decadenza non si sposta */
    public const GIORNI_CONSEGNA = 15;

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected int $annoImposta;
    protected TipoGaranzia $tipo;
    protected ?string $garante = null;
    protected ?string $estremi = null;
    protected float $importo = 0.0;
    protected SismaDate $dataRilascio;
    protected bool $compensazioneGruppo = false;
    protected ?SismaDate $dataRimborso = null;
    protected ?SismaDate $dataRichiestaDocumenti = null;
    protected ?SismaDate $dataConsegnaDocumenti = null;
    protected ?SismaDate $dataScadenza = null;
    protected ?SismaDate $dataSvincolo = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    /**
     * Scadenza di legge, nota dall'esecuzione del rimborso.
     */
    public function getScadenzaProposta(): ?SismaDate
    {
        if ($this->dataRimborso === null) {
            return null;
        }
        $durata = (new \DateTimeImmutable($this->dataRimborso->format('Y-m-d')))->modify('+' . self::DURATA_ANNI . ' years')->format('Y-m-d');
        $decadenza = (new \DateTimeImmutable(($this->annoImposta + self::ANNI_DECADENZA) . '-12-31'))->modify('+' . $this->getGiorniDifferimento() . ' days')->format('Y-m-d');
        return new SismaDate(min($durata, $decadenza));
    }

    /**
     * Giorni di differimento della decadenza: dal sedicesimo giorno alla
     * consegna, entrambi compresi (consegna al sedicesimo giorno: un giorno).
     */
    public function getGiorniDifferimento(): int
    {
        if (($this->dataRichiestaDocumenti === null) || ($this->dataConsegnaDocumenti === null)) {
            return 0;
        }
        $giorni = (int) (new \DateTimeImmutable($this->dataRichiestaDocumenti->format('Y-m-d')))->diff(new \DateTimeImmutable($this->dataConsegnaDocumenti->format('Y-m-d')))->format('%r%a');
        return max(0, $giorni - self::GIORNI_CONSEGNA);
    }

    public function isSvincolata(): bool
    {
        return $this->dataSvincolo !== null;
    }
}
