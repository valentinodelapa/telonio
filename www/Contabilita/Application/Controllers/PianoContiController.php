<?php

namespace Contabilita\Application\Controllers;

use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Entities\PianoConti;
use Contabilita\Application\Forms\PianoContiForm;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Services\CopiaPianoConti;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Piani dei conti modello (della suite, in sola lettura, e dello studio)
 * e piano dell'azienda. Un modello della suite si personalizza
 * copiandolo nello studio; il piano dell'azienda è una copia
 * indipendente fatta all'apertura della contabilità.
 */
class PianoContiController extends ContabilitaController
{

    public function index(): Response
    {
        $this->vars['modelli'] = (new PianoContiModel($this->dataMapper))->getModelli($this->studio);
        $this->vars['sezioneContabilita'] = 'piani';
        $this->vars['briciole'] = ['Contabilità' => '/azienda/index', 'Piani dei conti' => null];
        return $this->render->generateView('pianoConti/index', $this->vars);
    }

    public function show(PianoConti $piano): Response
    {
        $this->autorizza($piano);
        $conti = iterator_to_array((new ContoModel($this->dataMapper))->getElenco($piano), false);
        $this->vars['pianoConti'] = $piano;
        $this->vars['conti'] = $conti;
        $this->vars['modificabile'] = $piano->isDellaSuite() === false;
        $this->vars['pageTitle'] = $piano->denominazione;
        if ($piano->soggetto !== null) {
            $this->caricaAzienda($piano->soggetto);
            $this->getEsercizio($piano->soggetto, 0);
            $this->vars['sezioneAzienda'] = 'piano';
            $this->impostaBriciole($piano->soggetto, null, 'Piano dei conti');
        } else {
            $this->vars['briciole'] = ['Contabilità' => '/azienda/index', 'Piani dei conti' => '/piano-conti/index', $piano->denominazione => null];
        }
        return $this->render->generateView('pianoConti/show', $this->vars);
    }

    /**
     * Copia un modello in un nuovo modello dello studio.
     */
    public function copia(Request $request, PianoConti $piano): Response
    {
        $this->autorizza($piano);
        $this->verificaCsrf($request);
        $copia = (new CopiaPianoConti($this->dataMapper))->copia($piano, $this->studio, null, 'Copia di ' . $piano->denominazione);
        $this->aggiungiMessaggio('Piano copiato nello studio: ora si può personalizzare.');
        return $this->router->redirect('piano-conti/show/piano/' . $copia->id);
    }

    public function update(Request $request, PianoConti $piano): Response
    {
        $this->autorizza($piano, true);
        $form = new PianoContiForm($piano);
        if ($this->isInvioValido($request, $form)) {
            $piano = $form->resolveEntity();
            $this->dataMapper->save($piano);
            $this->aggiungiMessaggio('Piano dei conti salvato.');
            return $this->router->redirect('piano-conti/show/piano/' . $piano->id);
        }
        $this->impostaVariabiliForm($form, $piano);
        $this->vars['pianoConti'] = $piano;
        $this->vars['pageTitle'] = $piano->denominazione;
        $this->vars['briciole'] = ['Contabilità' => '/azienda/index', 'Piani dei conti' => '/piano-conti/index', $piano->denominazione => null];
        return $this->render->generateView('pianoConti/form', $this->vars, $form->getResponseType());
    }

    /**
     * Elimina un modello dello studio (i piani delle aziende copiati da
     * lì restano).
     */
    public function delete(Request $request, PianoConti $piano): Response
    {
        $this->autorizza($piano, true);
        $this->verificaCsrf($request);
        if ($piano->isModello() === false) {
            $this->aggiungiMessaggio('Il piano di un\'azienda non si elimina.', 'warning');
            return $this->router->redirect('piano-conti/show/piano/' . $piano->id);
        }
        foreach ((new PianoContiModel($this->dataMapper))->getByPianoOrigine($piano) as $copia) {
            $copia->pianoOrigine = null;
            $this->dataMapper->save($copia);
        }
        $primaTransazione = $this->dataMapper->startTransaction();
        $conti = iterator_to_array((new ContoModel($this->dataMapper))->getElenco($piano), false);
        foreach (array_reverse($conti) as $conto) {
            $this->dataMapper->delete($conto);
        }
        $this->dataMapper->delete($piano);
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio('Modello eliminato.');
        return $this->router->redirect('piano-conti/index');
    }

    private function autorizza(PianoConti $piano, bool $modifica = false): void
    {
        if ($piano->soggetto !== null) {
            $this->autorizzaSoggetto($piano->soggetto);
            return;
        }
        $this->autorizzaStudio($piano->studio, $modifica);
    }
}
