<?php

namespace CommSuite\Application\Exceptions;

use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Core\Services\RouterService;
use SismaFramework\Security\ExtendedClasses\RedirectException;

/**
 * Sollevata dalle aree riservate quando non c'è un utente in sessione:
 * il front controller la intercetta e reindirizza alla pagina di accesso.
 */
class AutenticazioneRichiestaException extends RedirectException
{

    #[\Override]
    public function redirect(): Response
    {
        return RouterService::getInstance()->redirect('auth/login');
    }
}
