<?php

namespace GestioneStudio\Application\Controllers;

use GestioneStudio\Application\BaseClasses\GestioneStudioController;
use GestioneStudio\Application\Entities\Adempimento;
use GestioneStudio\Application\Entities\TermineAdempimento;
use GestioneStudio\Application\Forms\TermineAdempimentoForm;
use GestioneStudio\Application\Models\TermineAdempimentoModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Termini degli adempimenti dello studio (quelli del calendario fiscale
 * vengono dalle sorgenti del modulo).
 */
class TermineAdempimentoController extends GestioneStudioController
{

    public function create(Request $request, Adempimento $adempimento): Response
    {
        $this->autorizzaAdempimento($adempimento, true);
        $termine = new TermineAdempimento($this->dataMapper);
        $termine->adempimento = $adempimento;
        $termine->ordine = (new TermineAdempimentoModel($this->dataMapper))->getProssimoOrdine($adempimento);
        return $this->gestisci($request, $termine, 'Nuovo termine');
    }

    public function update(Request $request, TermineAdempimento $termine): Response
    {
        $this->autorizzaAdempimento($termine->adempimento, true);
        return $this->gestisci($request, $termine, 'Modifica del termine');
    }

    public function delete(Request $request, TermineAdempimento $termine): Response
    {
        $this->autorizzaAdempimento($termine->adempimento, true);
        $this->verificaCsrf($request);
        $adempimento = $termine->adempimento;
        $this->dataMapper->delete($termine);
        $this->aggiungiMessaggio('Termine eliminato con le sue proroghe.');
        return $this->router->redirect('adempimento/show/adempimento/' . $adempimento->id);
    }

    private function gestisci(Request $request, TermineAdempimento $termine, string $titolo): Response
    {
        $form = new TermineAdempimentoForm($termine);
        if ($this->isInvioValido($request, $form)) {
            $termine = $form->resolveEntity();
            $this->dataMapper->save($termine);
            $this->aggiungiMessaggio('Termine salvato.');
            return $this->router->redirect('adempimento/show/adempimento/' . $termine->adempimento->id);
        }
        $this->impostaVariabiliForm($form, $termine);
        $this->vars['adempimento'] = $termine->adempimento;
        $this->vars['mesi'] = array_combine(range(1, 12), ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre']);
        $this->vars['urlAnnulla'] = '/adempimento/show/adempimento/' . $termine->adempimento->id;
        $this->vars['pageTitle'] = $titolo;
        $this->vars['sezione'] = 'adempimenti';
        $this->impostaBriciole(['Calendario degli adempimenti' => '/adempimento/index', $termine->adempimento->denominazione => $this->vars['urlAnnulla'], $titolo => null]);
        return $this->render->generateView('termineAdempimento/form', $this->vars, $form->getResponseType());
    }
}
