<?php

namespace GestioneStudio\Application\Entities;

use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Termine ordinario di un adempimento per un periodo dell'anno di
 * riferimento A (periodo d'imposta): giorno e mese dell'anno A +
 * anniSuccessivi. Es. IVA mensile di dicembre: 16 gennaio, un anno dopo;
 * dichiarazione dei redditi: 31 ottobre, un anno dopo.
 *
 * Giorno 0 indica l'ultimo giorno del mese (es. fine febbraio).
 */
class TermineAdempimento extends ReferencedEntity
{

    public const ULTIMO_GIORNO = 0;

    protected int $id;
    protected Adempimento $adempimento;
    protected int $ordine = 1;
    protected string $periodo;
    protected int $mese;
    protected int $giorno;
    protected int $anniSuccessivi = 0;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    /**
     * Termine ordinario per l'anno di riferimento, prima di slittamenti e
     * proroghe.
     */
    public function getTermineOrdinario(int $anno): SismaDate
    {
        $annoTermine = $anno + $this->anniSuccessivi;
        $ultimo = (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $annoTermine, $this->mese)))->format('t');
        $giorno = $this->giorno === self::ULTIMO_GIORNO ? $ultimo : min($this->giorno, $ultimo);
        return new SismaDate(sprintf('%04d-%02d-%02d', $annoTermine, $this->mese, $giorno));
    }

    public function getDescrizioneTermine(): string
    {
        $mesi = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
        return ($this->giorno === self::ULTIMO_GIORNO ? 'fine' : $this->giorno) . ' ' . $mesi[$this->mese - 1]
                . ($this->anniSuccessivi === 0 ? '' : ($this->anniSuccessivi === 1 ? ' dell\'anno successivo' : ' di ' . $this->anniSuccessivi . ' anni dopo'));
    }
}
