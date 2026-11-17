<?php

namespace Dichiarativi\Application\Fixtures;

use Dichiarativi\Application\Services\Isa\CalcoloIsa;
use Dichiarativi\Application\Services\Isa\ConcordatoIsa;
use Dichiarativi\Application\Services\Isa\ModelliIsa;
use Dichiarativi\Application\Services\Isa\MotoreIsa;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Modello ISA EM01A (commercio al dettaglio alimentare) di Lucia Ferri per
 * il 2025, allegato alla dichiarazione Redditi PF: frontespizio, quadro F
 * e collaboratori dell'impresa familiare proposti dalla dichiarazione e
 * dall'anagrafica; a mano i dati che non sono negli archivi (apporto di
 * lavoro del figlio dall'aprile, il negozio di 60 mq a Roma, una superette,
 * la vendita in negozio e l'offerta). Senza variabili precalcolate (la
 * tipologia di precalcolo è "non disponibile"); calcolato con il software
 * ufficiale se installato (immagine PHP), per l'allegato al file Redditi,
 * con l'adesione alla proposta di concordato preventivo biennale 2026-2027.
 */
class IsaFerri2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(RedditiFerri2025Fixture::class)
                ->addDependency(SedeFerriFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $redditi = $this->getEntityByFixtureName(RedditiFerri2025Fixture::class);
        $isa = (new ModelliIsa($this->dataMapper))->crea($redditi, 'RG');
        $quadri = new QuadriDichiarazione($isa, $this->dataMapper);
        foreach ([
            // A06, colonna 2: collaboratore a tempo pieno da aprile (9 mesi su 12)
            'A0100602' => 75,
            // unità locale (proposta dalla sede operativa di Roma): 60 mq di vendita, superette o minimercato
            'B0100301' => 60, 'B0100401' => 2,
            // vendita in negozio; offerta: alimentari confezionati e prodotti non alimentari
            'C0100801' => 100, 'C0103201' => 70, 'C0104401' => 30,
        ] as $codice => $valore) {
            $quadri->imposta($codice, $valore);
        }
        $quadri->salva();
        // note aggiuntive per l'Agenzia (record R)
        $isa->annotazioni = 'Negozio chiuso per lavori di ristrutturazione dal 1 al 15 marzo 2025.';
        $this->dataMapper->save($isa);
        if ((new MotoreIsa())->isDisponibile($isa->getAnnoModello())) {
            // concordato preventivo biennale 2026-2027: requisiti e assenza di cause di esclusione, calcolo della proposta, adesione
            $quadri = new QuadriDichiarazione($isa, $this->dataMapper);
            $quadri->imposta('P0100101', true);
            $quadri->imposta('P0100201', true);
            $quadri->salva();
            $calcolo = new CalcoloIsa($this->dataMapper);
            $calcolo->calcola($isa);
            if (ConcordatoIsa::getProposta($isa) !== null) {
                (new ConcordatoIsa($this->dataMapper))->aderisci($isa);
            }
            $calcolo->calcola($isa);
        }
        // senza le variabili precalcolate la dichiarazione Redditi si trasmette confermando la segnalazione (controllo telematico ***C)
        $redditi->flagConferma = true;
        $this->dataMapper->save($redditi);
        $this->addEntity($isa);
    }
}
