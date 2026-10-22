<?php

namespace Anagrafica\Application\Controllers;

use Anagrafica\Application\BaseClasses\AnagraficaController;
use Anagrafica\Application\Entities\Recapito;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoRecapito;
use Anagrafica\Application\Forms\RecapitoForm;
use Anagrafica\Application\Models\RecapitoModel;
use Anagrafica\Application\Services\SincronizzazioneSoggetto;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

class RecapitoController extends AnagraficaController
{

    public function create(Request $request, Soggetto $soggetto): Response
    {
        $this->autorizza($soggetto);
        $recapito = new Recapito($this->dataMapper);
        $recapito->soggetto = $soggetto;
        return $this->gestisci($request, $recapito, 'Nuovo recapito');
    }

    public function update(Request $request, Recapito $recapito): Response
    {
        $this->autorizza($recapito->soggetto);
        return $this->gestisci($request, $recapito, 'Modifica recapito');
    }

    public function delete(Request $request, Recapito $recapito): Response
    {
        $soggetto = $recapito->soggetto;
        $this->autorizza($soggetto);
        $this->verificaCsrf($request);
        $this->dataMapper->delete($recapito);
        (new SincronizzazioneSoggetto($this->dataMapper))->aggiorna($soggetto);
        $this->aggiungiMessaggio('Recapito eliminato.');
        return $this->vaiAllaScheda($soggetto, 'recapiti');
    }

    private function gestisci(Request $request, Recapito $recapito, string $titolo): Response
    {
        $form = new RecapitoForm($recapito);
        if ($this->isInvioValido($request, $form)) {
            $recapito = $form->resolveEntity();
            $primaTransazione = $this->dataMapper->startTransaction();
            if ($recapito->principale) {
                $this->rimuoviAltriPrincipali($recapito);
            }
            $this->dataMapper->save($recapito);
            (new SincronizzazioneSoggetto($this->dataMapper))->aggiorna($recapito->soggetto);
            $this->dataMapper->commitTransaction($primaTransazione);
            $this->aggiungiMessaggio('Recapito salvato.');
            return $this->vaiAllaScheda($recapito->soggetto, 'recapiti');
        }
        $this->impostaVariabiliForm($form, $recapito);
        $this->vars['soggetto'] = $recapito->soggetto;
        $this->vars['tipiRecapito'] = TipoRecapito::cases();
        $this->vars['nuovo'] = isset($recapito->id) === false;
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBriciole($recapito->soggetto, $titolo);
        return $this->render->generateView('recapito/form', $this->vars, $form->getResponseType());
    }

    private function rimuoviAltriPrincipali(Recapito $recapito): void
    {
        foreach ((new RecapitoModel($this->dataMapper))->getBySoggettoAndTipoRecapitoAndPrincipale($recapito->soggetto, $recapito->tipoRecapito, true) as $altro) {
            if ((isset($recapito->id) === false) || ($altro->id !== $recapito->id)) {
                $altro->principale = false;
                $this->dataMapper->save($altro);
            }
        }
    }
}
