<?php

namespace Deleghe\Application\Entities;

use Deleghe\Application\Enumerations\ModalitaUtilizzo;
use Deleghe\Application\Enumerations\ObbligoCampo;
use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Causale contributo della sezione INPS o della sezione altri enti
 * previdenziali (tabelle dell'Agenzia delle Entrate), con le regole del
 * periodo di riferimento e, per l'INPS, i formati ammessi della matricola.
 * Le regole testuali della tabella sono conservate per gli aiuti.
 */
class CausaleContributo extends BaseEntity
{

    public const ENTE_INPS = 'INPS';

    protected int $id;
    protected string $ente;
    protected string $causale;
    protected string $descrizione;
    protected ?string $formatiMatricola = null;
    protected ?string $regolaPosizione = null;
    protected ObbligoCampo $periodoDa = ObbligoCampo::obbligatorio;
    protected ObbligoCampo $periodoA = ObbligoCampo::obbligatorio;
    protected ?string $regolaPeriodoDa = null;
    protected ?string $regolaPeriodoA = null;
    protected ModalitaUtilizzo $modalita = ModalitaUtilizzo::debito;
    protected bool $compensabileCreditiPa = false;
    protected bool $inUso = true;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    /**
     * @return string[] identificativi dei formati (tabella FINP)
     */
    public function getFormatiMatricola(): array
    {
        return $this->formatiMatricola === null ? [] : explode(',', $this->formatiMatricola);
    }

    public function getEtichetta(): string
    {
        return $this->causale . ' - ' . $this->descrizione;
    }
}
