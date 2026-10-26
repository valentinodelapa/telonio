<?php

namespace Telonio\Application\Controllers;

use Telonio\Application\BaseClasses\SuiteController;
use Telonio\Application\Entities\Password;
use Telonio\Application\Enumerations\ModuloSuite;
use Telonio\Application\Forms\PasswordForm;
use Telonio\Application\Forms\ProfiloForm;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Gestione del profilo personale e cambio della propria password per
 * qualsiasi operatore autenticato.
 */
class ProfiloController extends SuiteController
{

    #[\Override]
    protected function getModulo(): ?ModuloSuite
    {
        return null;
    }

    public function update(Request $request): Response
    {
        $form = new ProfiloForm($this->utente, $this->dataMapper);
        if ($this->isInvioValido($request, $form)) {
            $this->utente = $form->resolveEntity();
            $this->dataMapper->save($this->utente);
            $this->aggiungiMessaggio('Profilo aggiornato.');
            return $this->router->redirect('profilo/update');
        }
        $this->vars['formEntity'] = $form->isSubmitted() ? $form->getEntityDataToStandardEntity() : $this->utente;
        $this->vars['errors'] = $form->getFilterErrors();
        $this->vars['briciole'] = ['Profilo personale' => null];
        return $this->render->generateView('profilo/update', $this->vars, $form->getResponseType());
    }

    public function password(Request $request): Response
    {
        $password = new Password($this->dataMapper);
        $password->utente = $this->utente;
        $form = new PasswordForm($password, true, $this->dataMapper);
        if ($this->isInvioValido($request, $form)) {
            $password = $form->resolveEntity();
            $this->dataMapper->save($password);
            $this->aggiungiMessaggio('Password aggiornata.');
            return $this->router->redirect('profilo/update');
        }
        $this->vars['errors'] = $form->getFilterErrors();
        $this->vars['briciole'] = ['Profilo personale' => '/profilo/update', 'Cambio password' => null];
        return $this->render->generateView('profilo/password', $this->vars, $form->getResponseType());
    }
}
