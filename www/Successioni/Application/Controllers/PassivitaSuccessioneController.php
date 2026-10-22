<?php

namespace Successioni\Application\Controllers;

use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use Successioni\Application\BaseClasses\SuccessioniController;
use Successioni\Application\Entities\DichiarazioneSuccessione;
use Successioni\Application\Entities\PassivitaSuccessione;
use Successioni\Application\Entities\RipartizionePassivita;
use Successioni\Application\Enumerations\TipoPassivita;
use Successioni\Application\Forms\PassivitaSuccessioneForm;
use Successioni\Application\Models\BeneficiarioSuccessioneModel;
use Successioni\Application\Models\BeneSuccessioneModel;
use Successioni\Application\Models\PassivitaSuccessioneModel;
use Successioni\Application\Models\RipartizionePassivitaModel;
use Successioni\Application\Services\Frazione;
use Successioni\Application\Services\PropostaAttivo;

/**
 * Passività e oneri del quadro ED con la ripartizione tra i beneficiari
 * (di default in parti uguali tra gli eredi).
 */
class PassivitaSuccessioneController extends SuccessioniController
{

    public function create(Request $request, DichiarazioneSuccessione $dichiarazione): Response
    {
        $this->autorizzaDichiarazione($dichiarazione);
        if (($bloccata = $this->verificaModificabile($dichiarazione, 'attivo')) !== null) {
            return $bloccata;
        }
        $passivita = new PassivitaSuccessione($this->dataMapper);
        $passivita->dichiarazioneSuccessione = $dichiarazione;
        $passivita->ordine = (new PassivitaSuccessioneModel($this->dataMapper))->getProssimoOrdine($dichiarazione);
        return $this->gestisci($request, $passivita, 'Nuova passività');
    }

    public function update(Request $request, PassivitaSuccessione $passivita): Response
    {
        $this->autorizzaDichiarazione($passivita->dichiarazioneSuccessione);
        if (($bloccata = $this->verificaModificabile($passivita->dichiarazioneSuccessione, 'attivo')) !== null) {
            return $bloccata;
        }
        return $this->gestisci($request, $passivita, $passivita->descrizione);
    }

    private function gestisci(Request $request, PassivitaSuccessione $passivita, string $titolo): Response
    {
        $dichiarazione = $passivita->dichiarazioneSuccessione;
        $beneficiari = [];
        foreach ((new BeneficiarioSuccessioneModel($this->dataMapper))->getElenco($dichiarazione) as $beneficiario) {
            if ($beneficiario->isDestinatario()) {
                $beneficiari[$beneficiario->id] = $beneficiario;
            }
        }
        $ripartizioneModel = new RipartizionePassivitaModel($this->dataMapper);
        $quote = [];
        if (isset($passivita->id)) {
            foreach ($ripartizioneModel->getElenco($passivita) as $ripartizione) {
                $quote[$ripartizione->beneficiarioSuccessione->id] = new Frazione($ripartizione->quotaNumeratore, $ripartizione->quotaDenominatore);
            }
        }
        $form = new PassivitaSuccessioneForm($passivita);
        $erroreRipartizione = null;
        if ($this->isInvioValido($request, $form)) {
            $quote = $this->leggiQuote($request, $beneficiari, $erroreRipartizione);
            if ($erroreRipartizione === null) {
                $passivita = $form->resolveEntity();
                $primaTransazione = $this->dataMapper->startTransaction();
                $this->dataMapper->save($passivita);
                foreach ($ripartizioneModel->getElenco($passivita) as $esistente) {
                    $this->dataMapper->delete($esistente);
                }
                foreach (PropostaAttivo::ripartisciValore($passivita->valore, $quote) as $id => $valore) {
                    $ripartizione = new RipartizionePassivita($this->dataMapper);
                    $ripartizione->passivitaSuccessione = $passivita;
                    $ripartizione->beneficiarioSuccessione = $beneficiari[$id];
                    $ripartizione->quotaNumeratore = $quote[$id]->numeratore;
                    $ripartizione->quotaDenominatore = $quote[$id]->denominatore;
                    $ripartizione->valoreQuota = $valore;
                    $this->dataMapper->save($ripartizione);
                }
                $this->dataMapper->commitTransaction($primaTransazione);
                $this->aggiungiMessaggio('Passività salvata.');
                return $this->vaiAllaDichiarazione($dichiarazione, 'attivo');
            }
        }
        if ((count($quote) === 0) && (count($beneficiari) > 0)) {
            foreach ($beneficiari as $id => $beneficiario) {
                if ($beneficiario->tipoBeneficiario->isErede()) {
                    $quote[$id] = new Frazione(1, count(array_filter($beneficiari, fn($altro) => $altro->tipoBeneficiario->isErede())));
                }
            }
        }
        $beni = [];
        foreach ((new BeneSuccessioneModel($this->dataMapper))->getElenco($dichiarazione) as $bene) {
            $beni[$bene->id] = $bene->quadro->value . ' - ' . $bene->getDescrizioneBreve();
        }
        $this->impostaVariabiliForm($form, $passivita);
        $this->vars['passivita'] = $passivita;
        $this->vars['dichiarazione'] = $dichiarazione;
        $this->vars['beneficiari'] = $beneficiari;
        $this->vars['quote'] = $quote;
        $this->vars['quoteInviate'] = is_array($request->input['quote'] ?? null) ? $request->input['quote'] : [];
        $this->vars['erroreRipartizione'] = $erroreRipartizione;
        $this->vars['beni'] = $beni;
        $this->vars['tipi'] = TipoPassivita::cases();
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBriciole($dichiarazione, $titolo, 'attivo');
        return $this->render->generateView('passivitaSuccessione/form', $this->vars, $form->getResponseType());
    }

    /**
     * Quote di ripartizione dal form ("quote[id] = n/d").
     *
     * @return array<int, Frazione>
     */
    private function leggiQuote(Request $request, array $beneficiari, ?string &$errore): array
    {
        $quote = [];
        $totale = new Frazione(0);
        foreach ((array) ($request->input['quote'] ?? []) as $id => $testo) {
            $testo = trim((string) $testo);
            if (($testo === '') || ($testo === '0')) {
                continue;
            }
            if ((isset($beneficiari[(int) $id]) === false) || (preg_match('#^(\d+)\s*/\s*(\d+)$#', $testo, $parti) !== 1) || ((int) $parti[2] === 0) || ((int) $parti[1] === 0)) {
                $errore = 'Quote di ripartizione nel formato numeratore/denominatore (es. 1/3).';
                return [];
            }
            $quote[(int) $id] = new Frazione((int) $parti[1], (int) $parti[2]);
            $totale = $totale->piu($quote[(int) $id]);
        }
        if ($totale->isUguale(new Frazione(1)) === false) {
            $errore = 'La somma delle quote di ripartizione deve essere pari all\'intero (1/1): ora è ' . $totale . '.';
        }
        return $quote;
    }

    public function delete(Request $request, PassivitaSuccessione $passivita): Response
    {
        $dichiarazione = $passivita->dichiarazioneSuccessione;
        $this->autorizzaDichiarazione($dichiarazione);
        $this->verificaCsrf($request);
        if (($bloccata = $this->verificaModificabile($dichiarazione, 'attivo')) !== null) {
            return $bloccata;
        }
        $primaTransazione = $this->dataMapper->startTransaction();
        foreach ((new RipartizionePassivitaModel($this->dataMapper))->getElenco($passivita) as $ripartizione) {
            $this->dataMapper->delete($ripartizione);
        }
        $this->dataMapper->delete($passivita);
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio('Passività eliminata.');
        return $this->vaiAllaDichiarazione($dichiarazione, 'attivo');
    }
}
