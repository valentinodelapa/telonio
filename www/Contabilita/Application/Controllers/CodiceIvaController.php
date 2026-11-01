<?php

namespace Contabilita\Application\Controllers;

use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Entities\CodiceIva;
use Contabilita\Application\Enumerations\NaturaIva;
use Contabilita\Application\Forms\CodiceIvaForm;
use Contabilita\Application\Models\CodiceIvaModel;
use Contabilita\Application\Models\RigaIvaModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Codici IVA: quelli della suite (aggiornati da "sisma
 * tabelle-contabilita", in sola lettura) e quelli aggiunti dallo studio.
 */
class CodiceIvaController extends ContabilitaController
{

    public function index(): Response
    {
        $this->vars['codici'] = (new CodiceIvaModel($this->dataMapper))->getElenco($this->studio, false);
        $this->vars['sezioneContabilita'] = 'codiciIva';
        $this->vars['briciole'] = ['Contabilità' => '/azienda/index', 'Codici IVA' => null];
        return $this->render->generateView('codiceIva/index', $this->vars);
    }

    public function create(Request $request): Response
    {
        $codice = new CodiceIva($this->dataMapper);
        $codice->studio = $this->studio;
        return $this->gestisci($request, $codice, 'Nuovo codice IVA');
    }

    public function update(Request $request, CodiceIva $codice): Response
    {
        $this->autorizzaStudio($codice->studio, true);
        return $this->gestisci($request, $codice, 'Codice IVA ' . $codice->codice);
    }

    public function delete(Request $request, CodiceIva $codice): Response
    {
        $this->autorizzaStudio($codice->studio, true);
        $this->verificaCsrf($request);
        if ((new RigaIvaModel($this->dataMapper))->countByCodiceIva($codice) > 0) {
            $this->aggiungiMessaggio('Il codice è usato nelle registrazioni: renderlo non attivo invece di eliminarlo.', 'warning');
        } else {
            $this->dataMapper->delete($codice);
            $this->aggiungiMessaggio('Codice IVA eliminato.');
        }
        return $this->router->redirect('codice-iva/index');
    }

    private function gestisci(Request $request, CodiceIva $codice, string $titolo): Response
    {
        $form = new CodiceIvaForm($codice);
        if ($this->isInvioValido($request, $form)) {
            $codice = $form->resolveEntity();
            $this->dataMapper->save($codice);
            $this->aggiungiMessaggio('Codice IVA ' . $codice->codice . ' salvato.');
            return $this->router->redirect('codice-iva/index');
        }
        $this->impostaVariabiliForm($form, $codice);
        $this->vars['codice'] = $codice;
        $this->vars['nature'] = NaturaIva::cases();
        $this->vars['pageTitle'] = $titolo;
        $this->vars['briciole'] = ['Contabilità' => '/azienda/index', 'Codici IVA' => '/codice-iva/index', $titolo => null];
        return $this->render->generateView('codiceIva/form', $this->vars, $form->getResponseType());
    }
}
