<?php

namespace Anagrafica\Application\Controllers;

use Anagrafica\Application\BaseClasses\AnagraficaController;
use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Forms\IndirizzoForm;
use Anagrafica\Application\Models\IndirizzoModel;
use Anagrafica\Application\Models\NazioneModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

class IndirizzoController extends AnagraficaController
{

    public function create(Request $request, Soggetto $soggetto, string $tipo = ''): Response
    {
        $this->autorizza($soggetto);
        $indirizzo = new Indirizzo($this->dataMapper);
        $indirizzo->soggetto = $soggetto;
        $indirizzo->nazione = (new NazioneModel($this->dataMapper))->getItalia();
        $tipoIndirizzo = TipoIndirizzo::tryFrom($tipo);
        if (($tipoIndirizzo !== null) && $tipoIndirizzo->isApplicabile($soggetto->tipoSoggetto)) {
            $indirizzo->tipoIndirizzo = $tipoIndirizzo;
        }
        return $this->gestisci($request, $indirizzo, 'Nuovo indirizzo', 'Indirizzo registrato.');
    }

    public function update(Request $request, Indirizzo $indirizzo): Response
    {
        $this->autorizza($indirizzo->soggetto);
        return $this->gestisci($request, $indirizzo, 'Rettifica indirizzo', 'Indirizzo rettificato.');
    }

    public function delete(Request $request, Indirizzo $indirizzo): Response
    {
        $this->autorizza($indirizzo->soggetto);
        $ambito = (new IndirizzoModel($this->dataMapper))->getBySoggettoAndTipoIndirizzo($indirizzo->soggetto, $indirizzo->tipoIndirizzo);
        return $this->annullaVersione($request, $indirizzo, $ambito, 'indirizzi', 'Indirizzo eliminato.');
    }

    private function gestisci(Request $request, Indirizzo $indirizzo, string $titolo, string $messaggio): Response
    {
        $form = new IndirizzoForm($indirizzo);
        $ambito = fn(Indirizzo $versione) => (new IndirizzoModel($this->dataMapper))->getBySoggettoAndTipoIndirizzo($versione->soggetto, $versione->tipoIndirizzo);
        $risposta = $this->salvaVersione($request, $form, $indirizzo, $ambito, 'indirizzi', $messaggio);
        if ($risposta !== null) {
            return $risposta;
        }
        $this->impostaVariabiliForm($form, $indirizzo);
        $this->vars['soggetto'] = $indirizzo->soggetto;
        $this->vars['tipiIndirizzo'] = $indirizzo->soggetto->tipoSoggetto->getTipiIndirizzo();
        $this->vars['nuovo'] = isset($indirizzo->id) === false;
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBriciole($indirizzo->soggetto, $titolo);
        return $this->render->generateView('indirizzo/form', $this->vars, $form->getResponseType());
    }
}
