<?php

namespace Deleghe\Application\Controllers;

use Anagrafica\Application\Models\ContoCorrenteModel;
use Deleghe\Application\BaseClasses\DelegheController;
use Deleghe\Application\Entities\Intermediario;
use Deleghe\Application\Forms\IntermediarioForm;
use Deleghe\Application\Models\IntermediarioModel;
use SismaFramework\Core\Exceptions\AccessDeniedException;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Intermediari Entratel dello studio, fornitori dei flussi telematici F24.
 */
class IntermediarioController extends DelegheController
{

    public function index(): Response
    {
        $this->vars['intermediari'] = (new IntermediarioModel($this->dataMapper))->getElenco($this->studio);
        $this->vars['briciole'] = ['Deleghe F24' => '/contribuente/index', 'Intermediari' => null];
        return $this->render->generateView('intermediario/index', $this->vars);
    }

    public function create(Request $request): Response
    {
        $intermediario = new Intermediario($this->dataMapper);
        $intermediario->studio = $this->studio;
        $intermediario->predefinito = (new IntermediarioModel($this->dataMapper))->getPredefinito($this->studio) === null;
        return $this->gestisci($request, $intermediario, 'Nuovo intermediario');
    }

    public function update(Request $request, Intermediario $intermediario): Response
    {
        $this->autorizzaIntermediario($intermediario);
        return $this->gestisci($request, $intermediario, 'Modifica intermediario');
    }

    private function autorizzaIntermediario(Intermediario $intermediario): void
    {
        if ($intermediario->studio->id !== $this->studio->id) {
            throw new AccessDeniedException();
        }
    }

    private function gestisci(Request $request, Intermediario $intermediario, string $titolo): Response
    {
        $form = new IntermediarioForm($intermediario);
        if ($this->isInvioValido($request, $form)) {
            $intermediario = $form->resolveEntity();
            $primaTransazione = $this->dataMapper->startTransaction();
            if ($intermediario->predefinito) {
                foreach ((new IntermediarioModel($this->dataMapper))->getByStudioAndPredefinito($this->studio, true) as $altro) {
                    if ((isset($intermediario->id) === false) || ($altro->id !== $intermediario->id)) {
                        $altro->predefinito = false;
                        $this->dataMapper->save($altro);
                    }
                }
            }
            $this->dataMapper->save($intermediario);
            $this->dataMapper->commitTransaction($primaTransazione);
            $this->aggiungiMessaggio('Intermediario salvato.');
            return $this->router->redirect('intermediario/index');
        }
        $this->impostaVariabiliForm($form, $intermediario);
        $soggetto = isset($intermediario->soggetto) ? $intermediario->soggetto : null;
        $this->vars['soggettoCorrente'] = $soggetto === null ? null : $soggetto->nominativo . ' - ' . $soggetto->codiceFiscale;
        $conti = [];
        if ($soggetto !== null) {
            foreach ((new ContoCorrenteModel($this->dataMapper))->getAttivi($soggetto) as $conto) {
                $conti[$conto->id] = $conto->getEtichetta();
            }
        }
        $this->vars['conti'] = $conti;
        $this->vars['nuovo'] = isset($intermediario->id) === false;
        $this->vars['pageTitle'] = $titolo;
        $this->vars['briciole'] = ['Deleghe F24' => '/contribuente/index', 'Intermediari' => '/intermediario/index', $titolo => null];
        return $this->render->generateView('intermediario/form', $this->vars, $form->getResponseType());
    }

    public function delete(Request $request, Intermediario $intermediario): Response
    {
        $this->autorizzaIntermediario($intermediario);
        $this->verificaCsrf($request);
        try {
            $this->dataMapper->delete($intermediario);
            $this->aggiungiMessaggio('Intermediario eliminato.');
        } catch (\Throwable) {
            $this->aggiungiMessaggio('L\'intermediario ha già effettuato invii telematici e non può essere eliminato.', 'danger');
        }
        return $this->router->redirect('intermediario/index');
    }
}
