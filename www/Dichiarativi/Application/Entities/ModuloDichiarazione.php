<?php

namespace Dichiarativi\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\Attivita;
use Dichiarativi\Application\Enumerations\TipoModulo;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Modulo della dichiarazione oltre al primo (attività con contabilità
 * separata, soggetto partecipante a un'operazione straordinaria, prima
 * frazione d'anno della procedura concorsuale), con il soggetto della
 * suite dalla cui contabilità si propone. Il modulo 1 è il contribuente e
 * non ha bisogno di una riga. I moduli delle attività con contabilità
 * separata della Contabilità sono creati e numerati dalla proposta
 * (ModuliAttivita): il primo è il modulo 1.
 */
class ModuloDichiarazione extends BaseEntity
{

    protected int $id;
    protected Dichiarazione $dichiarazione;
    protected int $numero;
    protected TipoModulo $tipo = TipoModulo::attivitaSeparata;
    protected ?Soggetto $soggettoFonte = null;
    protected ?SismaDate $dataOperazione = null;
    protected ?string $descrizione = null;
    protected ?Attivita $attivita = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function getEtichetta(): string
    {
        return 'Modulo ' . $this->numero . ' · ' . ($this->descrizione ?? $this->attivita?->getEtichetta() ?? $this->soggettoFonte?->nominativo ?? $this->tipo->getLabel());
    }
}
