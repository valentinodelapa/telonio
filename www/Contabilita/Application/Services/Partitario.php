<?php

namespace Contabilita\Application\Services;

use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Entities\PianoConti;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Enumerations\LivelloConto;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\PianoContiModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Sottoconti del partitario clienti e fornitori: ogni controparte ha un
 * sottoconto per ruolo sotto il conto con funzione clienti o fornitori
 * del piano dell'azienda, aperto alla prima necessità con il primo codice
 * libero e la denominazione della controparte.
 */
class Partitario
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Sottoconto della controparte per il ruolo, aperto se manca.
     *
     * @throws \DomainException se il piano non ha il conto con funzione clienti o fornitori
     */
    public function getConto(Controparte $controparte, bool $cliente): Conto
    {
        $esistente = $controparte->getConto($cliente);
        if ($esistente !== null) {
            return $esistente;
        }
        $piano = (new PianoContiModel($this->dataMapper))->getPerAzienda($controparte->soggetto) ?? throw new \DomainException('La contabilità dell\'azienda non è aperta.');
        $conto = $this->apri($piano, $cliente ? FunzioneConto::clienti : FunzioneConto::fornitori, $controparte->denominazione);
        if ($cliente) {
            $controparte->cliente = true;
            $controparte->contoCliente = $conto;
        } else {
            $controparte->fornitore = true;
            $controparte->contoFornitore = $conto;
        }
        $this->dataMapper->save($controparte);
        return $conto;
    }

    /**
     * Apre i sottoconti mancanti per i ruoli della controparte.
     */
    public function allinea(Controparte $controparte): void
    {
        if ($controparte->cliente) {
            $this->getConto($controparte, true);
        }
        if ($controparte->fornitore) {
            $this->getConto($controparte, false);
        }
    }

    private function apri(PianoConti $piano, FunzioneConto $funzione, string $denominazione): Conto
    {
        $contoModel = new ContoModel($this->dataMapper);
        $padre = $contoModel->getPerFunzione($piano, $funzione);
        if (($padre === null) || ($padre->livello !== LivelloConto::conto)) {
            throw new \DomainException('Nel piano dei conti manca il conto con funzione "' . $funzione->getLabel() . '".');
        }
        $conto = new Conto($this->dataMapper);
        $conto->pianoConti = $piano;
        $conto->parentConto = $padre;
        $conto->codice = $contoModel->getProssimoCodice($padre);
        $conto->descrizione = mb_substr($denominazione, 0, 150);
        $conto->livello = LivelloConto::sottoconto;
        $conto->natura = $padre->natura;
        $conto->voceCee = $padre->voceCee;
        $this->dataMapper->save($conto);
        return $conto;
    }
}
