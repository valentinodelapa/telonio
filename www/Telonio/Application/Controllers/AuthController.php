<?php

namespace Telonio\Application\Controllers;

use Telonio\Application\BaseClasses\SuiteController;
use Telonio\Application\Models\PasswordModel;
use Telonio\Application\Models\UtenteModel;
use SismaFramework\Core\BaseClasses\BaseController;
use SismaFramework\Core\HelperClasses\Session;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Security\HttpClasses\Authentication;

class AuthController extends BaseController
{

    public function login(Request $request, Authentication $authentication): Response
    {
        if (Session::hasItem(SuiteController::SESSIONE_UTENTE)) {
            return $this->router->redirect('home/index');
        }
        $this->vars['identificativo'] = '';
        $this->vars['accessoFallito'] = false;
        if ($authentication->isSubmitted()) {
            $authentication->setAuthenticableModelInterface(new UtenteModel($this->dataMapper));
            $authentication->setPasswordModelInterface(new PasswordModel($this->dataMapper));
            if ($authentication->checkAuthenticable()) {
                $utente = $authentication->getAuthenticableInterface();
                $utente->ultimoAccesso = new SismaDateTime();
                $this->dataMapper->save($utente);
                Session::setItem(SuiteController::SESSIONE_UTENTE, $utente->id);
                return $this->router->redirect('home/index');
            }
            $this->vars['identificativo'] = (string) ($request->input[\Config\AUTHENTICATION_IDENTIFIER_FIELD] ?? '');
            $this->vars['accessoFallito'] = true;
        }
        $this->vars['csrfToken'] = SuiteController::getCsrfToken();
        return $this->render->generateView('auth/login', $this->vars);
    }

    public function logout(Request $request): Response
    {
        $token = $request->input[\Config\CSRF_TOKEN_KEY] ?? null;
        if (($request->server['REQUEST_METHOD'] !== 'POST') || (is_string($token) === false) || (hash_equals(SuiteController::getCsrfToken(), $token) === false)) {
            return $this->router->redirect('home/index');
        }
        Session::end();
        return $this->router->redirect('auth/login');
    }
}
