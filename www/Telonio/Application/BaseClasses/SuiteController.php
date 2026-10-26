<?php

namespace Telonio\Application\BaseClasses;

use Telonio\Application\Entities\Studio;
use Telonio\Application\Entities\Utente;
use Telonio\Application\Enumerations\ModuloSuite;
use Telonio\Application\Exceptions\AutenticazioneRichiestaException;
use Telonio\Application\Models\UtenteModel;
use SismaFramework\Core\BaseClasses\BaseController;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Exceptions\AccessDeniedException;
use SismaFramework\Core\HelperClasses\Debugger;
use SismaFramework\Core\HelperClasses\Encryptor;
use SismaFramework\Core\HelperClasses\Session;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Controller base delle aree riservate di tutti i moduli della suite:
 * richiede un utente autenticato, espone lo studio corrente (tenant) e
 * prepara le variabili del layout comune.
 */
abstract class SuiteController extends BaseController
{

    public const SESSIONE_UTENTE = 'utenteId';
    private const SESSIONE_MESSAGGI = 'messaggiFlash';

    protected Utente $utente;
    protected Studio $studio;

    public function __construct(DataMapper $dataMapper = new DataMapper(), ?Debugger $debugger = null)
    {
        parent::__construct($dataMapper, $debugger);
        $this->caricaUtente();
        $this->vars['utente'] = $this->utente;
        $this->vars['studio'] = $this->studio;
        $this->vars['moduli'] = ModuloSuite::cases();
        $this->vars['moduloAttivo'] = $this->getModulo();
        $this->vars['csrfToken'] = self::getCsrfToken();
        $this->vars['messaggiFlash'] = $this->estraiMessaggi();
        $this->vars['briciole'] = [];
    }

    private function caricaUtente(): void
    {
        if (Session::hasItem(self::SESSIONE_UTENTE) === false) {
            throw new AutenticazioneRichiestaException();
        }
        $utente = (new UtenteModel($this->dataMapper))->getEntityById((int) Session::getItem(self::SESSIONE_UTENTE));
        if (($utente instanceof Utente === false) || ($utente->attivo === false) || ($utente->studio->attivo === false)) {
            Session::unsetItem(self::SESSIONE_UTENTE);
            throw new AutenticazioneRichiestaException();
        }
        $this->utente = $utente;
        $this->studio = $utente->studio;
    }

    abstract protected function getModulo(): ?ModuloSuite;

    public static function getCsrfToken(): string
    {
        if (Session::hasItem(\Config\CSRF_TOKEN_KEY) === false) {
            Session::setItem(\Config\CSRF_TOKEN_KEY, Encryptor::getSimpleRandomToken());
        }
        return Session::getItem(\Config\CSRF_TOKEN_KEY);
    }

    protected function verificaCsrf(Request $request): void
    {
        $token = $request->input[\Config\CSRF_TOKEN_KEY] ?? null;
        if ((is_string($token) === false) || (hash_equals(self::getCsrfToken(), $token) === false)) {
            throw new AccessDeniedException('Token di sicurezza non valido');
        }
    }

    /**
     * Gestisce la richiesta per uno o più form della stessa pagina e indica
     * se sono stati inviati e sono tutti validi. Tutti i form vengono
     * validati, così da mostrare in un'unica volta tutti gli errori. Ogni
     * form riceve una copia della richiesta: le normalizzazioni fatte da un
     * form (injectRequest) non si riflettono sugli altri.
     */
    protected function isInvioValido(Request $request, BaseForm ...$forms): bool
    {
        foreach ($forms as $form) {
            $form->handleRequest(clone $request);
        }
        if ($forms[0]->isSubmitted() === false) {
            return false;
        }
        $this->verificaCsrf($request);
        $validi = true;
        foreach ($forms as $form) {
            $validi = $form->isValid() && $validi;
        }
        return $validi;
    }

    protected function aggiungiMessaggio(string $testo, string $tipo = 'success'): void
    {
        $messaggi = Session::hasItem(self::SESSIONE_MESSAGGI) ? Session::getItem(self::SESSIONE_MESSAGGI, true) : [];
        $messaggi[] = ['testo' => $testo, 'tipo' => $tipo];
        Session::setItem(self::SESSIONE_MESSAGGI, $messaggi, true);
    }

    private function estraiMessaggi(): array
    {
        if (Session::hasItem(self::SESSIONE_MESSAGGI) === false) {
            return [];
        }
        $messaggi = Session::getItem(self::SESSIONE_MESSAGGI, true);
        Session::unsetItem(self::SESSIONE_MESSAGGI);
        return $messaggi;
    }
}
