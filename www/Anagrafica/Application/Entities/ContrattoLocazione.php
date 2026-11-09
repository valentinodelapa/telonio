<?php

namespace Anagrafica\Application\Entities;

use Anagrafica\Application\Enumerations\TipoContrattoLocazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Contratto di locazione di un fabbricato o di affitto di un terreno
 * dell'archivio immobili: i locatori sono i possessori dell'immobile nel
 * periodo, che dichiarano il canone per intero indipendentemente dalla
 * quota (istruzioni Redditi PF, quadro RB, col. 6). Le pertinenze locate
 * congiuntamente sono in PertinenzaLocata (il canone si ripartisce in
 * proporzione alle rendite), le variazioni del canone (aggiornamento
 * ISTAT, rinegoziazione) in VariazioneCanone.
 *
 * Cedolare secca (art. 3 D.Lgs. 23/2011): l'opzione vale per tutta la
 * durata del contratto, salva la revoca. Per le locazioni brevi il canone
 * è il corrispettivo lordo dell'anno e l'aliquota è del 21 per cento per
 * la sola unità scelta dal contribuente, del 26 per cento per le altre.
 */
class ContrattoLocazione extends ReferencedEntity
{

    protected int $id;
    protected Immobile $immobile;
    protected TipoContrattoLocazione $tipo;
    protected ?string $conduttore = null;
    protected ?string $codiceFiscaleConduttore = null;
    protected SismaDate $dataInizio;
    protected ?SismaDate $dataFine = null;
    protected float $canoneAnnuo = 0.0;
    protected bool $cedolareSecca = false;
    protected ?SismaDate $cedolareRevocataDal = null;
    protected bool $cedolareRidotta = false;
    protected bool $veneziaCentro = false;
    protected bool $emergenzaCalamita = false;
    protected bool $giovaneAgricoltore = false;
    protected ?SismaDate $dataRegistrazione = null;
    protected ?string $serieRegistrazione = null;
    protected ?string $numeroRegistrazione = null;
    protected ?string $codiceUfficio = null;
    protected ?string $codiceIdentificativo = null;
    protected ?string $cin = null;
    protected ?string $note = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataInizio = new SismaDate();
    }

    /**
     * Il contratto copre almeno un giorno dell'intervallo.
     */
    public function isInCorsoTra(\DateTimeInterface $dal, \DateTimeInterface $al): bool
    {
        return ($this->dataInizio->format('Y-m-d') <= $al->format('Y-m-d'))
                && (($this->dataFine === null) || ($this->dataFine->format('Y-m-d') >= $dal->format('Y-m-d')));
    }

    /**
     * Cedolare secca in vigore alla data: opzione esercitata e non ancora
     * revocata.
     */
    public function isCedolareAl(\DateTimeInterface $data): bool
    {
        return $this->cedolareSecca && (($this->cedolareRevocataDal === null) || ($this->cedolareRevocataDal->format('Y-m-d') > $data->format('Y-m-d')));
    }

    /**
     * Aliquota della cedolare secca: 10 per cento per il canone concordato,
     * 26 per cento per le locazioni brevi diverse dall'unità scelta, 21 per
     * cento negli altri casi.
     */
    public function getAliquotaCedolare(): int
    {
        return match (true) {
            $this->tipo === TipoContrattoLocazione::canoneConcordato => 10,
            ($this->tipo === TipoContrattoLocazione::locazioneBreve) && ($this->cedolareRidotta === false) => 26,
            default => 21,
        };
    }

    public function getDescrizione(): string
    {
        return $this->tipo->getLabel() . ' dal ' . $this->dataInizio->format('d/m/Y')
                . ($this->dataFine !== null ? ' al ' . $this->dataFine->format('d/m/Y') : '')
                . ($this->conduttore !== null ? ' - ' . $this->conduttore : '');
    }
}
