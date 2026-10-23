<?php

namespace Anagrafica\Application\Controllers;

use Anagrafica\Application\BaseClasses\AnagraficaController;
use Anagrafica\Application\Entities\IdentificativoGraffato;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Forms\IdentificativoGraffatoForm;
use Anagrafica\Application\Models\IdentificativoGraffatoModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

class IdentificativoGraffatoController extends AnagraficaController
{

    public function create(Request $request, Immobile $immobile): Response
    {
        $this->autorizzaImmobile($immobile);
        $graffato = new IdentificativoGraffato($this->dataMapper);
        $graffato->immobile = $immobile;
        $graffato->sezione = $immobile->sezione;
        $graffato->foglio = $immobile->foglio;
        return $this->gestisci($request, $graffato, 'Nuovo identificativo graffato');
    }

    public function update(Request $request, IdentificativoGraffato $identificativoGraffato): Response
    {
        $this->autorizzaImmobile($identificativoGraffato->immobile);
        return $this->gestisci($request, $identificativoGraffato, 'Modifica identificativo graffato');
    }

    public function delete(Request $request, IdentificativoGraffato $identificativoGraffato): Response
    {
        $immobile = $identificativoGraffato->immobile;
        $this->autorizzaImmobile($immobile);
        $this->verificaCsrf($request);
        $this->dataMapper->delete($identificativoGraffato);
        $this->aggiungiMessaggio('Identificativo graffato eliminato.');
        return $this->vaiAllImmobile($immobile);
    }

    private function gestisci(Request $request, IdentificativoGraffato $graffato, string $titolo): Response
    {
        $form = new IdentificativoGraffatoForm($graffato);
        if ($this->isInvioValido($request, $form)) {
            $graffato = $form->resolveEntity();
            if ($this->isDuplicato($graffato)) {
                $this->vars['messaggiFlash'][] = ['testo' => 'Identificativo già presente per questo immobile.', 'tipo' => 'danger'];
            } else {
                $this->dataMapper->save($graffato);
                $this->aggiungiMessaggio('Identificativo graffato salvato.');
                return $this->vaiAllImmobile($graffato->immobile);
            }
        }
        $immobile = $graffato->immobile;
        $this->impostaVariabiliForm($form, $graffato);
        $this->vars['immobile'] = $immobile;
        $this->vars['nuovo'] = isset($graffato->id) === false;
        $this->vars['pageTitle'] = $titolo;
        $this->vars['briciole'] = ['Anagrafica' => '/soggetto/index', 'Immobili' => '/immobile/index', $immobile->getIdentificativo() => '/immobile/show/immobile/' . $immobile->id, $titolo => null];
        return $this->render->generateView('identificativoGraffato/form', $this->vars, $form->getResponseType());
    }

    /**
     * Lo stesso identificativo non può comparire due volte nell'immobile,
     * né coincidere con quello principale.
     */
    private function isDuplicato(IdentificativoGraffato $graffato): bool
    {
        $immobile = $graffato->immobile;
        if ($graffato->getIdentificativo() === $immobile->getIdentificativo()) {
            return true;
        }
        foreach ((new IdentificativoGraffatoModel($this->dataMapper))->getElenco($immobile) as $altro) {
            if ((isset($graffato->id) === false || ($altro->id !== $graffato->id)) && ($altro->getIdentificativo() === $graffato->getIdentificativo())) {
                return true;
            }
        }
        return false;
    }
}
