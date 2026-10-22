<?php

namespace CommSuite\Application\Controllers;

use CommSuite\Application\BaseClasses\SuiteController;
use CommSuite\Application\Entities\Password;
use CommSuite\Application\Entities\Utente;
use CommSuite\Application\Enumerations\ModuloSuite;
use CommSuite\Application\Enumerations\RuoloUtente;
use CommSuite\Application\Forms\PasswordForm;
use CommSuite\Application\Forms\UtenteForm;
use CommSuite\Application\Models\UtenteModel;
use CommSuite\Application\Permissions\StudioPermission;
use CommSuite\Application\Permissions\UtentePermission;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\ResponseType;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Security\Enumerations\AccessControlEntry;

/**
 * Gestione degli operatori dello studio (riservata agli amministratori dello studio).
 */
class UtenteController extends SuiteController
{

    #[\Override]
    protected function getModulo(): ?ModuloSuite
    {
        return null;
    }

    public function index(): Response
    {
        StudioPermission::isAllowed($this->studio, AccessControlEntry::allow, $this->utente);
        $this->vars['utenti'] = (new UtenteModel($this->dataMapper))->getElenco($this->studio);
        $this->vars['briciole'] = ['Studio' => '/studio/update', 'Utenti' => null];
        return $this->render->generateView('utente/index', $this->vars);
    }

    public function create(Request $request): Response
    {
        StudioPermission::isAllowed($this->studio, AccessControlEntry::allow, $this->utente);
        $nuovoUtente = new Utente($this->dataMapper);
        $nuovoUtente->studio = $this->studio;
        $nuovoUtente->ruolo = RuoloUtente::operatore;
        $nuovoUtente->attivo = true;
        $password = new Password($this->dataMapper);
        $password->utente = $nuovoUtente;
        $utenteForm = new UtenteForm($nuovoUtente, $this->utente, $this->dataMapper);
        $passwordForm = new PasswordForm($password, false, $this->dataMapper);
        if ($this->isInvioValido($request, $utenteForm, $passwordForm)) {
            $primaTransazione = $this->dataMapper->startTransaction();
            $nuovoUtente = $utenteForm->resolveEntity();
            $password = $passwordForm->resolveEntity();
            $password->utente = $nuovoUtente;
            $this->dataMapper->save($nuovoUtente);
            $this->dataMapper->save($password);
            $this->dataMapper->commitTransaction($primaTransazione);
            $this->aggiungiMessaggio('Utente creato.');
            return $this->router->redirect('utente/index');
        }
        $this->vars['formEntity'] = $utenteForm->isSubmitted() ? $utenteForm->getEntityDataToStandardEntity() : $nuovoUtente;
        $this->vars['errors'] = $utenteForm->getFilterErrors();
        $this->vars['passwordErrors'] = $passwordForm->getFilterErrors();
        $this->vars['nuovo'] = true;
        $this->vars['pageTitle'] = 'Nuovo utente';
        $this->vars['briciole'] = ['Studio' => '/studio/update', 'Utenti' => '/utente/index', 'Nuovo utente' => null];
        return $this->generaVistaForm($utenteForm, $passwordForm);
    }

    public function update(Request $request, Utente $utente): Response
    {
        UtentePermission::isAllowed($utente, AccessControlEntry::allow, $this->utente);
        $utenteForm = new UtenteForm($utente, $this->utente, $this->dataMapper);
        if ($this->isInvioValido($request, $utenteForm)) {
            $utente = $utenteForm->resolveEntity();
            $this->dataMapper->save($utente);
            $this->aggiungiMessaggio('Dati dell\'utente aggiornati.');
            return $this->router->redirect('utente/index');
        }
        $this->vars['formEntity'] = $utenteForm->isSubmitted() ? $utenteForm->getEntityDataToStandardEntity() : $utente;
        $this->vars['errors'] = $utenteForm->getFilterErrors();
        $this->vars['nuovo'] = false;
        $this->vars['utenteModificato'] = $utente;
        $this->vars['pageTitle'] = 'Modifica utente';
        $this->vars['briciole'] = ['Studio' => '/studio/update', 'Utenti' => '/utente/index', $utente->getNomeCompleto() => null];
        return $this->generaVistaForm($utenteForm);
    }

    private function generaVistaForm(BaseForm ...$forms): Response
    {
        $this->vars['ruoli'] = RuoloUtente::cases();
        $responseType = ResponseType::httpOk;
        foreach ($forms as $form) {
            if ($form->getResponseType() !== ResponseType::httpOk) {
                $responseType = $form->getResponseType();
            }
        }
        return $this->render->generateView('utente/form', $this->vars, $responseType);
    }

    public function password(Request $request, Utente $utente): Response
    {
        UtentePermission::isAllowed($utente, AccessControlEntry::allow, $this->utente);
        $password = new Password($this->dataMapper);
        $password->utente = $utente;
        $form = new PasswordForm($password, false, $this->dataMapper);
        if ($this->isInvioValido($request, $form)) {
            $password = $form->resolveEntity();
            $this->dataMapper->save($password);
            $this->aggiungiMessaggio('Password reimpostata per ' . $utente->getNomeCompleto() . '.');
            return $this->router->redirect('utente/index');
        }
        $this->vars['utenteModificato'] = $utente;
        $this->vars['errors'] = $form->getFilterErrors();
        $this->vars['briciole'] = ['Studio' => '/studio/update', 'Utenti' => '/utente/index', $utente->getNomeCompleto() => '/utente/update/utente/' . $utente->id, 'Reimposta password' => null];
        return $this->render->generateView('utente/password', $this->vars, $form->getResponseType());
    }
}
