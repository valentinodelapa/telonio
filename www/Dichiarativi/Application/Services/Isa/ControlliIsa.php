<?php

namespace Dichiarativi\Application\Services\Isa;

use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Controlli del modello ISA. I controlli sui dati (campi obbligatori,
 * somme, coerenze interne) sono quelli del software ufficiale, restituiti
 * dal calcolo (CalcoloIsa): i bloccanti ("***") sono errori, come i
 * bloccanti confermabili ("***C") quando il software non ha elaborato il
 * modello; dopo un calcolo riuscito i "***C" sono segnalazioni da
 * confermare (anche nella dichiarazione Redditi a cui il modello è
 * allegato), le segnalazioni ("*") avvertenze. In più:
 *
 * - il modello va collegato a una dichiarazione Redditi e calcolato sui
 *   dati attuali dei quadri (altrimenti non si allega al file Redditi);
 * - il quadro P del concordato preventivo biennale si compila solo con
 *   l'accettazione della proposta (ConcordatoIsa);
 * - le variabili precalcolate fornite dall'Agenzia sono obbligatorie
 *   (allegato 2 al provvedimento del 27 febbraio 2026, controllo bloccante
 *   confermabile): senza, segnalazione da confermare.
 */
class ControlliIsa
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return string[]
     */
    public function getErrori(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri): array
    {
        if ($dichiarazione->dichiarazioneRedditi === null) {
            return ['Modello ISA non collegato a una dichiarazione Redditi: si allega solo a una dichiarazione dei redditi.'];
        }
        $errori = [];
        foreach (CalcoloIsa::getMessaggi($dichiarazione) as $messaggio) {
            if (($messaggio['tipo'] === '***') || (($messaggio['tipo'] === '***C') && ($dichiarazione->calcoloIsa === null))) {
                $errori[] = sprintf('Il tuo ISA, errore %s: %s', $messaggio['codice'], $messaggio['messaggio']);
            }
        }
        if (ConcordatoIsa::isSenzaAccettazione($quadri)) {
            $errori[] = 'Quadro P (concordato preventivo biennale) compilato senza l\'accettazione della proposta: aderire alla proposta o rinunciare ("Non aderire"), che toglie i dati del quadro.';
        }
        if (count($errori) > 0) {
            return $errori;
        }
        if ($dichiarazione->calcoloIsa === null) {
            $errori[] = 'Modello ISA ' . $dichiarazione->isa . ' da calcolare con il software ufficiale ("Calcola").';
        } elseif ((new CalcoloIsa($this->dataMapper))->isAggiornato($dichiarazione, $quadri) === false) {
            $errori[] = 'Quadri del modello ISA ' . $dichiarazione->isa . ' cambiati dopo il calcolo: ricalcolare ("Calcola").';
        }
        return $errori;
    }

    /**
     * @return string[]
     */
    public function getSegnalazioni(QuadriDichiarazione $quadri): array
    {
        $dichiarazione = $quadri->getDichiarazione();
        $segnalazioni = [];
        if ($dichiarazione->calcoloIsa !== null) {
            foreach (CalcoloIsa::getMessaggi($dichiarazione) as $messaggio) {
                if ($messaggio['tipo'] === '***C') {
                    $segnalazioni[] = sprintf('Il tuo ISA, errore %s: %s', $messaggio['codice'], $messaggio['messaggio']);
                }
            }
        }
        if ($quadri->get('IFU00101', 1) === '3') {
            $segnalazioni[] = 'Variabili precalcolate dell\'Agenzia delle Entrate non importate (tipologia di precalcolo "non disponibile"): il loro utilizzo è obbligatorio per chi applica gli ISA (controllo telematico confermabile).';
        }
        return $segnalazioni;
    }

    /**
     * Avvertenze: segnalazioni del software e quadro contabile di Redditi
     * non più compilato o con un'attività di un altro ISA.
     *
     * @return string[]
     */
    public function getAvvertenze(QuadriDichiarazione $quadri, ?Dichiarazione $dichiarazione = null): array
    {
        $dichiarazione ??= $quadri->getDichiarazione();
        $avvertenze = [];
        foreach (CalcoloIsa::getMessaggi($dichiarazione) as $messaggio) {
            if (str_starts_with($messaggio['tipo'], '***') === false) {
                $avvertenze[] = sprintf('Il tuo ISA, segnalazione %s: %s', $messaggio['codice'], $messaggio['messaggio']);
            }
        }
        if ($dichiarazione->dichiarazioneRedditi === null) {
            return $avvertenze;
        }
        foreach ((new ModelliIsa($this->dataMapper))->getModelli($dichiarazione->dichiarazioneRedditi) as $modello) {
            if ($modello['dichiarazione']?->id === $dichiarazione->id) {
                return $avvertenze;
            }
        }
        $avvertenze[] = sprintf('Nella dichiarazione Redditi collegata nessun quadro contabile ha un\'attività dell\'ISA %s: verificare il codice attività o eliminare il modello.', $dichiarazione->isa);
        return $avvertenze;
    }
}
