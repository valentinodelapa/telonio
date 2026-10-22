<?php

namespace Anagrafica\Application\Controllers;

use Anagrafica\Application\BaseClasses\AnagraficaController;
use Anagrafica\Application\Entities\ContoCorrente;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Forms\ContoCorrenteForm;
use Anagrafica\Application\Models\ContoCorrenteModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Conti correnti del soggetto. Un conto usato da altri moduli (es. come
 * conto di addebito di una delega) non si elimina: si segna come estinto.
 */
class ContoCorrenteController extends AnagraficaController
{

    public function create(Request $request, Soggetto $soggetto): Response
    {
        $this->autorizza($soggetto);
        $conto = new ContoCorrente($this->dataMapper);
        $conto->soggetto = $soggetto;
        $conto->predefinito = (new ContoCorrenteModel($this->dataMapper))->getPredefinito($soggetto) === null;
        return $this->gestisci($request, $conto, 'Nuovo conto corrente');
    }

    public function update(Request $request, ContoCorrente $conto): Response
    {
        $this->autorizza($conto->soggetto);
        return $this->gestisci($request, $conto, 'Modifica conto corrente');
    }

    public function delete(Request $request, ContoCorrente $conto): Response
    {
        $soggetto = $conto->soggetto;
        $this->autorizza($soggetto);
        $this->verificaCsrf($request);
        try {
            $this->dataMapper->delete($conto);
            $this->aggiungiMessaggio('Conto corrente eliminato.');
        } catch (\Throwable) {
            $this->aggiungiMessaggio('Il conto è usato in altri documenti (es. deleghe F24): segnalo come non attivo invece di eliminarlo.', 'danger');
        }
        return $this->vaiAllaScheda($soggetto, 'conti');
    }

    private function gestisci(Request $request, ContoCorrente $conto, string $titolo): Response
    {
        $form = new ContoCorrenteForm($conto);
        if ($this->isInvioValido($request, $form)) {
            $conto = $form->resolveEntity();
            $primaTransazione = $this->dataMapper->startTransaction();
            if ($conto->predefinito) {
                foreach ((new ContoCorrenteModel($this->dataMapper))->getBySoggettoAndPredefinito($conto->soggetto, true) as $altro) {
                    if ((isset($conto->id) === false) || ($altro->id !== $conto->id)) {
                        $altro->predefinito = false;
                        $this->dataMapper->save($altro);
                    }
                }
            }
            $this->dataMapper->save($conto);
            $this->dataMapper->commitTransaction($primaTransazione);
            $this->aggiungiMessaggio('Conto corrente salvato.');
            return $this->vaiAllaScheda($conto->soggetto, 'conti');
        }
        $this->impostaVariabiliForm($form, $conto);
        $this->vars['soggetto'] = $conto->soggetto;
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBriciole($conto->soggetto, $titolo);
        return $this->render->generateView('contoCorrente/form', $this->vars, $form->getResponseType());
    }
}
