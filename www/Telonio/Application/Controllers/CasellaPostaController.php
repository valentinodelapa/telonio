<?php

namespace Telonio\Application\Controllers;

use Telonio\Application\BaseClasses\SuiteController;
use Telonio\Application\Entities\CasellaPosta;
use Telonio\Application\Enumerations\CifraturaSmtp;
use Telonio\Application\Enumerations\ModuloSuite;
use Telonio\Application\Enumerations\TipoCasellaPosta;
use Telonio\Application\Forms\CasellaPostaForm;
use Telonio\Application\Models\CasellaPostaModel;
use Telonio\Application\Permissions\StudioPermission;
use Telonio\Application\Services\Posta\InvioPosta;
use Telonio\Application\Services\Posta\InvioPostaException;
use Telonio\Application\Services\Posta\Messaggio;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Security\Enumerations\AccessControlEntry;

/**
 * Posta in uscita dello studio (riservata agli amministratori): le due
 * caselle da cui si inviano i documenti ai clienti, e-mail ordinaria e PEC,
 * ognuna con il suo server SMTP, e l'invio di un messaggio di prova
 * all'utente collegato.
 */
class CasellaPostaController extends SuiteController
{

    #[\Override]
    protected function getModulo(): ?ModuloSuite
    {
        return null;
    }

    public function index(): Response
    {
        StudioPermission::isAllowed($this->studio, AccessControlEntry::allow, $this->utente);
        $model = new CasellaPostaModel($this->dataMapper);
        $caselle = [];
        foreach (TipoCasellaPosta::cases() as $tipo) {
            $caselle[$tipo->value] = $model->getCasella($this->studio, $tipo);
        }
        $this->vars['caselle'] = $caselle;
        $this->vars['casellaAmbiente'] = InvioPosta::getCasellaAmbiente($this->studio, $this->dataMapper);
        $this->vars['briciole'] = ['Studio' => '/studio/update', 'Posta in uscita' => null];
        return $this->render->generateView('casellaPosta/index', $this->vars);
    }

    public function update(Request $request, TipoCasellaPosta $tipo): Response
    {
        StudioPermission::isAllowed($this->studio, AccessControlEntry::allow, $this->utente);
        $casella = (new CasellaPostaModel($this->dataMapper))->getCasella($this->studio, $tipo);
        $nuova = $casella === null;
        if ($nuova) {
            $casella = new CasellaPosta($this->dataMapper);
            $casella->studio = $this->studio;
            $casella->tipoCasella = $tipo;
            $casella->cifratura = CifraturaSmtp::sslTls;
            $casella->porta = CifraturaSmtp::sslTls->getPortaPredefinita();
        }
        $passwordSalvata = $casella->password;
        $form = new CasellaPostaForm($casella, $this->dataMapper);
        if ($this->isInvioValido($request, $form)) {
            $casella = $form->resolveEntity();
            $casella->studio = $this->studio;
            $casella->tipoCasella = $tipo;
            if ($casella->utente === null) {
                $casella->password = null;
            } elseif ($casella->password === null) {
                $casella->password = $passwordSalvata;
            }
            $this->dataMapper->save($casella);
            $this->aggiungiMessaggio(sprintf('Casella %s salvata: con "Invia una prova" puoi verificare la configurazione.', $tipo->getLabel()));
            return $this->router->redirect('casella-posta/index');
        }
        $this->vars['formEntity'] = $form->isSubmitted() ? $form->getEntityDataToStandardEntity() : $casella;
        $this->vars['errors'] = $form->getFilterErrors();
        $this->vars['tipo'] = $tipo;
        $this->vars['conPassword'] = $passwordSalvata !== null;
        $this->vars['cifrature'] = CifraturaSmtp::cases();
        $this->vars['pageTitle'] = 'Casella ' . $tipo->getLabel();
        $this->vars['briciole'] = ['Studio' => '/studio/update', 'Posta in uscita' => '/casella-posta/index', $tipo->getLabel() => null];
        return $this->render->generateView('casellaPosta/form', $this->vars, $form->getResponseType());
    }

    public function delete(Request $request, TipoCasellaPosta $tipo): Response
    {
        StudioPermission::isAllowed($this->studio, AccessControlEntry::allow, $this->utente);
        $this->verificaCsrf($request);
        $casella = (new CasellaPostaModel($this->dataMapper))->getCasella($this->studio, $tipo);
        if ($casella !== null) {
            $this->dataMapper->delete($casella);
            $this->aggiungiMessaggio(sprintf('Configurazione della casella %s eliminata.', $tipo->getLabel()));
        }
        return $this->router->redirect('casella-posta/index');
    }

    /**
     * Messaggio di prova all'indirizzo dell'utente collegato.
     */
    public function prova(Request $request, TipoCasellaPosta $tipo): Response
    {
        StudioPermission::isAllowed($this->studio, AccessControlEntry::allow, $this->utente);
        $this->verificaCsrf($request);
        $messaggio = new Messaggio($this->utente->email, sprintf('Telonio: prova della casella %s', $tipo->getLabel()),
                sprintf("Messaggio di prova inviato da Telonio con la casella %s dello studio %s.\n\nSe lo hai ricevuto, la configurazione è corretta.\n", $tipo->getLabel(), $this->studio->denominazione));
        try {
            (new InvioPosta($this->dataMapper))->invia($this->studio, $tipo, $messaggio);
            $this->aggiungiMessaggio(sprintf('Messaggio di prova inviato a %s.', $this->utente->email));
        } catch (InvioPostaException $errore) {
            $this->aggiungiMessaggio($errore->getMessage(), 'danger');
        }
        return $this->router->redirect('casella-posta/index');
    }
}
