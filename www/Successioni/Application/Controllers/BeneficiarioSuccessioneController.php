<?php

namespace Successioni\Application\Controllers;

use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use Successioni\Application\BaseClasses\SuccessioniController;
use Successioni\Application\Entities\BeneficiarioSuccessione;
use Successioni\Application\Entities\DichiarazioneSuccessione;
use Successioni\Application\Enumerations\GradoParentela;
use Successioni\Application\Enumerations\TipoBeneficiario;
use Successioni\Application\Forms\BeneficiarioSuccessioneForm;
use Successioni\Application\Models\BeneficiarioSuccessioneModel;
use Successioni\Application\Models\DevoluzioneSuccessioneModel;
use Successioni\Application\Models\RipartizionePassivitaModel;

/**
 * Beneficiari del quadro EA (soggetti dell'Anagrafica).
 */
class BeneficiarioSuccessioneController extends SuccessioniController
{

    public function create(Request $request, DichiarazioneSuccessione $dichiarazione): Response
    {
        $this->autorizzaDichiarazione($dichiarazione);
        if (($bloccata = $this->verificaModificabile($dichiarazione, 'beneficiari')) !== null) {
            return $bloccata;
        }
        $beneficiario = new BeneficiarioSuccessione($this->dataMapper);
        $beneficiario->dichiarazioneSuccessione = $dichiarazione;
        $beneficiario->ordine = (new BeneficiarioSuccessioneModel($this->dataMapper))->getProssimoOrdine($dichiarazione);
        return $this->gestisci($request, $beneficiario, 'Nuovo beneficiario');
    }

    public function update(Request $request, BeneficiarioSuccessione $beneficiario): Response
    {
        $this->autorizzaDichiarazione($beneficiario->dichiarazioneSuccessione);
        if (($bloccata = $this->verificaModificabile($beneficiario->dichiarazioneSuccessione, 'beneficiari')) !== null) {
            return $bloccata;
        }
        return $this->gestisci($request, $beneficiario, $beneficiario->soggetto->nominativo);
    }

    private function gestisci(Request $request, BeneficiarioSuccessione $beneficiario, string $titolo): Response
    {
        $dichiarazione = $beneficiario->dichiarazioneSuccessione;
        $form = new BeneficiarioSuccessioneForm($beneficiario);
        if ($this->isInvioValido($request, $form)) {
            $beneficiario = $form->resolveEntity();
            if ($this->isDelloStudio($beneficiario->soggetto) && $this->isDelloStudio($beneficiario->rappresentato)) {
                $this->dataMapper->save($beneficiario);
                $this->aggiungiMessaggio('Beneficiario salvato.');
            }
            return $this->vaiAllaDichiarazione($dichiarazione, 'beneficiari');
        }
        $this->impostaVariabiliForm($form, $beneficiario);
        $this->vars['beneficiario'] = $beneficiario;
        $this->vars['dichiarazione'] = $dichiarazione;
        $this->vars['tipi'] = TipoBeneficiario::getGestiti();
        $this->vars['gradi'] = GradoParentela::cases();
        $this->vars['gradiRappresentato'] = [GradoParentela::figlio, GradoParentela::fratelloGermano, GradoParentela::fratelloUnilaterale];
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBriciole($dichiarazione, $titolo, 'beneficiari');
        return $this->render->generateView('beneficiarioSuccessione/form', $this->vars, $form->getResponseType());
    }

    /**
     * Elimina il beneficiario se non ha devoluzioni o ripartizioni e
     * rinumera i righi del quadro EA.
     */
    public function delete(Request $request, BeneficiarioSuccessione $beneficiario): Response
    {
        $dichiarazione = $beneficiario->dichiarazioneSuccessione;
        $this->autorizzaDichiarazione($dichiarazione);
        $this->verificaCsrf($request);
        if (($bloccata = $this->verificaModificabile($dichiarazione, 'beneficiari')) !== null) {
            return $bloccata;
        }
        if (((new DevoluzioneSuccessioneModel($this->dataMapper))->contaPerBeneficiario($beneficiario) > 0) || ((new RipartizionePassivitaModel($this->dataMapper))->contaPerBeneficiario($beneficiario) > 0)) {
            $this->aggiungiMessaggio('Il beneficiario riceve beni o sostiene passività: toglilo prima dalle devoluzioni e dalle ripartizioni.', 'warning');
            return $this->vaiAllaDichiarazione($dichiarazione, 'beneficiari');
        }
        $primaTransazione = $this->dataMapper->startTransaction();
        $this->dataMapper->delete($beneficiario);
        $ordine = 1;
        foreach ((new BeneficiarioSuccessioneModel($this->dataMapper))->getElenco($dichiarazione) as $altro) {
            $altro->ordine = $ordine++;
            $this->dataMapper->save($altro);
        }
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio('Beneficiario eliminato.');
        return $this->vaiAllaDichiarazione($dichiarazione, 'beneficiari');
    }
}
