<?php

namespace GestioneStudio\Application\Controllers;

use GestioneStudio\Application\BaseClasses\GestioneStudioController;
use GestioneStudio\Application\Entities\ProrogaAdempimento;
use GestioneStudio\Application\Entities\TermineAdempimento;
use GestioneStudio\Application\Forms\ProrogaAdempimentoForm;
use GestioneStudio\Application\Enumerations\CondizioneProroga;
use SismaFramework\Core\Exceptions\AccessDeniedException;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Proroghe dei termini inserite dallo studio (anche sugli adempimenti del
 * calendario fiscale), per tutti o per i soli soggetti con i requisiti
 * della condizione; prevalgono su quelle del calendario.
 */
class ProrogaAdempimentoController extends GestioneStudioController
{

    public function create(Request $request, TermineAdempimento $termine, int $anno): Response
    {
        $this->autorizzaAdempimento($termine->adempimento, false);
        $proroga = new ProrogaAdempimento($this->dataMapper);
        $proroga->studio = $this->studio;
        $proroga->termineAdempimento = $termine;
        $proroga->anno = $anno;
        $proroga->data = $termine->getTermineOrdinario($anno);
        return $this->gestisci($request, $proroga, 'Proroga del termine');
    }

    public function update(Request $request, ProrogaAdempimento $proroga): Response
    {
        $this->autorizzaProroga($proroga);
        return $this->gestisci($request, $proroga, 'Modifica della proroga');
    }

    public function delete(Request $request, ProrogaAdempimento $proroga): Response
    {
        $this->autorizzaProroga($proroga);
        $this->verificaCsrf($request);
        $adempimento = $proroga->termineAdempimento->adempimento;
        $anno = $proroga->anno;
        $this->dataMapper->delete($proroga);
        $this->aggiungiMessaggio('Proroga dello studio eliminata.');
        return $this->router->redirect('adempimento/show/adempimento/' . $adempimento->id . '/anno/' . $anno);
    }

    private function gestisci(Request $request, ProrogaAdempimento $proroga, string $titolo): Response
    {
        $form = new ProrogaAdempimentoForm($proroga);
        $adempimento = $proroga->termineAdempimento->adempimento;
        $urlAdempimento = '/adempimento/show/adempimento/' . $adempimento->id . '/anno/' . $proroga->anno;
        if ($this->isInvioValido($request, $form)) {
            $this->dataMapper->save($form->resolveEntity());
            $this->aggiungiMessaggio('Proroga salvata.');
            return $this->router->redirect(ltrim($urlAdempimento, '/'));
        }
        $this->impostaVariabiliForm($form, $proroga);
        $this->vars['proroga'] = $proroga;
        $this->vars['adempimento'] = $adempimento;
        $this->vars['ordinario'] = $proroga->termineAdempimento->getTermineOrdinario($proroga->anno);
        $this->vars['condizioni'] = CondizioneProroga::cases();
        $this->vars['urlAnnulla'] = $urlAdempimento;
        $this->vars['pageTitle'] = $titolo;
        $this->vars['sezione'] = 'adempimenti';
        $this->impostaBriciole(['Calendario degli adempimenti' => '/adempimento/index', $adempimento->denominazione => $urlAdempimento, $titolo => null]);
        return $this->render->generateView('prorogaAdempimento/form', $this->vars, $form->getResponseType());
    }

    /**
     * Le proroghe del calendario fiscale sono in sola lettura.
     */
    private function autorizzaProroga(ProrogaAdempimento $proroga): void
    {
        if (($proroga->studio === null) || ($proroga->studio->id !== $this->studio->id)) {
            throw new AccessDeniedException('Proroga non modificabile');
        }
    }
}
