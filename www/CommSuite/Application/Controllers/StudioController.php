<?php

namespace CommSuite\Application\Controllers;

use CommSuite\Application\BaseClasses\SuiteController;
use CommSuite\Application\Enumerations\ModuloSuite;
use CommSuite\Application\Forms\StudioForm;
use CommSuite\Application\Permissions\StudioPermission;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Security\Enumerations\AccessControlEntry;

/**
 * Configurazione dei dati identificativi dello studio corrente
 * (riservata agli amministratori dello studio).
 */
class StudioController extends SuiteController
{

    #[\Override]
    protected function getModulo(): ?ModuloSuite
    {
        return null;
    }

    public function update(Request $request): Response
    {
        StudioPermission::isAllowed($this->studio, AccessControlEntry::allow, $this->utente);
        $form = new StudioForm($this->studio, $this->dataMapper);
        if ($this->isInvioValido($request, $form)) {
            $this->studio = $form->resolveEntity();
            $this->dataMapper->save($this->studio);
            $this->aggiungiMessaggio('Dati dello studio aggiornati.');
            return $this->router->redirect('studio/update');
        }
        $this->vars['formEntity'] = $form->isSubmitted() ? $form->getEntityDataToStandardEntity() : $this->studio;
        $this->vars['errors'] = $form->getFilterErrors();
        $this->vars['briciole'] = ['Studio' => null];
        return $this->render->generateView('studio/update', $this->vars, $form->getResponseType());
    }
}
