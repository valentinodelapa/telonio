<?php

namespace Anagrafica\Application\Controllers;

use Anagrafica\Application\BaseClasses\AnagraficaController;
use Anagrafica\Application\Entities\CanoneNonPercepito;
use Anagrafica\Application\Entities\ContrattoLocazione;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\PertinenzaLocata;
use Anagrafica\Application\Entities\VariazioneCanone;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TipoContrattoLocazione;
use Anagrafica\Application\Forms\CanoneNonPercepitoForm;
use Anagrafica\Application\Forms\ContrattoLocazioneForm;
use Anagrafica\Application\Forms\PertinenzaLocataForm;
use Anagrafica\Application\Forms\VariazioneCanoneForm;
use Anagrafica\Application\Models\CanoneNonPercepitoModel;
use Anagrafica\Application\Models\PertinenzaLocataModel;
use Anagrafica\Application\Models\VariazioneCanoneModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Contratti di locazione e di affitto di un immobile, con le pertinenze
 * locate insieme e le variazioni del canone. Si gestiscono dalla scheda
 * dell'immobile.
 */
class ContrattoLocazioneController extends AnagraficaController
{

    public function create(Request $request, Immobile $immobile): Response
    {
        $this->autorizzaImmobile($immobile);
        $contratto = new ContrattoLocazione($this->dataMapper);
        $contratto->immobile = $immobile;
        $contratto->tipo = $immobile->tipoCatasto === TipoCatasto::terreni ? TipoContrattoLocazione::affittoLibero : TipoContrattoLocazione::canoneLibero;
        return $this->gestisci($request, $contratto, 'Nuovo contratto di locazione');
    }

    public function update(Request $request, ContrattoLocazione $contrattoLocazione): Response
    {
        $this->autorizzaImmobile($contrattoLocazione->immobile);
        return $this->gestisci($request, $contrattoLocazione, 'Modifica contratto di locazione');
    }

    public function show(ContrattoLocazione $contrattoLocazione): Response
    {
        $immobile = $contrattoLocazione->immobile;
        $this->autorizzaImmobile($immobile);
        $this->vars['contratto'] = $contrattoLocazione;
        $this->vars['immobile'] = $immobile;
        $this->vars['pertinenze'] = (new PertinenzaLocataModel($this->dataMapper))->getByContrattoLocazione($contrattoLocazione);
        $this->vars['variazioni'] = (new VariazioneCanoneModel($this->dataMapper))->getElenco($contrattoLocazione);
        $this->vars['canoniNonPercepiti'] = (new CanoneNonPercepitoModel($this->dataMapper))->getElenco($contrattoLocazione);
        $this->vars['pageTitle'] = 'Contratto di locazione';
        $this->vars['briciole'] = ['Anagrafica' => '/soggetto/index', 'Immobili' => '/immobile/index', $immobile->getIdentificativo() => '/immobile/show/immobile/' . $immobile->id, 'Contratto' => null];
        return $this->render->generateView('contrattoLocazione/show', $this->vars);
    }

    public function delete(Request $request, ContrattoLocazione $contrattoLocazione): Response
    {
        $immobile = $contrattoLocazione->immobile;
        $this->autorizzaImmobile($immobile);
        $this->verificaCsrf($request);
        $transazione = $this->dataMapper->startTransaction();
        (new PertinenzaLocataModel($this->dataMapper))->deleteByContrattoLocazione($contrattoLocazione);
        (new VariazioneCanoneModel($this->dataMapper))->deleteByContrattoLocazione($contrattoLocazione);
        (new CanoneNonPercepitoModel($this->dataMapper))->deleteByContrattoLocazione($contrattoLocazione);
        $this->dataMapper->delete($contrattoLocazione);
        $this->dataMapper->commitTransaction($transazione);
        $this->aggiungiMessaggio('Contratto di locazione eliminato.');
        return $this->vaiAllImmobile($immobile);
    }

    public function pertinenza(Request $request, ContrattoLocazione $contrattoLocazione): Response
    {
        $this->autorizzaImmobile($contrattoLocazione->immobile);
        $pertinenza = new PertinenzaLocata($this->dataMapper);
        $pertinenza->contrattoLocazione = $contrattoLocazione;
        $form = new PertinenzaLocataForm($pertinenza);
        if ($this->isInvioValido($request, $form)) {
            $pertinenza = $form->resolveEntity();
            foreach ((new PertinenzaLocataModel($this->dataMapper))->getByContrattoLocazione($contrattoLocazione) as $altra) {
                if ($altra->immobile->id === $pertinenza->immobile->id) {
                    $this->aggiungiMessaggio('La pertinenza è già nel contratto.');
                    return $this->vaiAlContratto($contrattoLocazione);
                }
            }
            $this->dataMapper->save($pertinenza);
            $this->aggiungiMessaggio('Pertinenza aggiunta al contratto.');
            return $this->vaiAlContratto($contrattoLocazione);
        }
        $this->impostaVariabiliForm($form, $pertinenza);
        return $this->formFiglio($contrattoLocazione, 'Pertinenza locata', 'contrattoLocazione/pertinenza', $form->getResponseType());
    }

    public function eliminaPertinenza(Request $request, PertinenzaLocata $pertinenzaLocata): Response
    {
        $contratto = $pertinenzaLocata->contrattoLocazione;
        $this->autorizzaImmobile($contratto->immobile);
        $this->verificaCsrf($request);
        $this->dataMapper->delete($pertinenzaLocata);
        $this->aggiungiMessaggio('Pertinenza tolta dal contratto.');
        return $this->vaiAlContratto($contratto);
    }

    public function variazione(Request $request, ContrattoLocazione $contrattoLocazione): Response
    {
        $this->autorizzaImmobile($contrattoLocazione->immobile);
        $variazione = new VariazioneCanone($this->dataMapper);
        $variazione->contrattoLocazione = $contrattoLocazione;
        return $this->gestisciVariazione($request, $variazione, 'Variazione del canone');
    }

    public function modificaVariazione(Request $request, VariazioneCanone $variazioneCanone): Response
    {
        $this->autorizzaImmobile($variazioneCanone->contrattoLocazione->immobile);
        return $this->gestisciVariazione($request, $variazioneCanone, 'Modifica variazione del canone');
    }

    public function canoneNonPercepito(Request $request, ContrattoLocazione $contrattoLocazione): Response
    {
        $this->autorizzaImmobile($contrattoLocazione->immobile);
        $canone = new CanoneNonPercepito($this->dataMapper);
        $canone->contrattoLocazione = $contrattoLocazione;
        return $this->gestisciCanone($request, $canone, 'Canoni non percepiti');
    }

    public function modificaCanoneNonPercepito(Request $request, CanoneNonPercepito $canoneNonPercepito): Response
    {
        $this->autorizzaImmobile($canoneNonPercepito->contrattoLocazione->immobile);
        return $this->gestisciCanone($request, $canoneNonPercepito, 'Modifica canoni non percepiti');
    }

    public function eliminaCanoneNonPercepito(Request $request, CanoneNonPercepito $canoneNonPercepito): Response
    {
        $contratto = $canoneNonPercepito->contrattoLocazione;
        $this->autorizzaImmobile($contratto->immobile);
        $this->verificaCsrf($request);
        $this->dataMapper->delete($canoneNonPercepito);
        $this->aggiungiMessaggio('Canoni non percepiti eliminati.');
        return $this->vaiAlContratto($contratto);
    }

    public function eliminaVariazione(Request $request, VariazioneCanone $variazioneCanone): Response
    {
        $contratto = $variazioneCanone->contrattoLocazione;
        $this->autorizzaImmobile($contratto->immobile);
        $this->verificaCsrf($request);
        $this->dataMapper->delete($variazioneCanone);
        $this->aggiungiMessaggio('Variazione del canone eliminata.');
        return $this->vaiAlContratto($contratto);
    }

    private function gestisci(Request $request, ContrattoLocazione $contratto, string $titolo): Response
    {
        $form = new ContrattoLocazioneForm($contratto);
        if ($this->isInvioValido($request, $form)) {
            $contratto = $form->resolveEntity();
            $this->dataMapper->save($contratto);
            $this->aggiungiMessaggio('Contratto di locazione salvato.');
            return $this->vaiAlContratto($contratto);
        }
        $immobile = $contratto->immobile;
        $this->impostaVariabiliForm($form, $contratto);
        $this->vars['immobile'] = $immobile;
        $this->vars['tipi'] = array_values(array_filter(TipoContrattoLocazione::cases(), fn(TipoContrattoLocazione $tipo) => $tipo->isTerreno() === ($immobile->tipoCatasto === TipoCatasto::terreni)));
        $this->vars['terreno'] = $immobile->tipoCatasto === TipoCatasto::terreni;
        $this->vars['nuovo'] = isset($contratto->id) === false;
        $this->vars['pageTitle'] = $titolo;
        $this->vars['urlAnnulla'] = isset($contratto->id) ? '/contratto-locazione/show/contratto-locazione/' . $contratto->id : '/immobile/show/immobile/' . $immobile->id;
        $this->vars['briciole'] = ['Anagrafica' => '/soggetto/index', 'Immobili' => '/immobile/index', $immobile->getIdentificativo() => '/immobile/show/immobile/' . $immobile->id, $titolo => null];
        return $this->render->generateView('contrattoLocazione/form', $this->vars, $form->getResponseType());
    }

    private function gestisciVariazione(Request $request, VariazioneCanone $variazione, string $titolo): Response
    {
        $form = new VariazioneCanoneForm($variazione);
        if ($this->isInvioValido($request, $form)) {
            $variazione = $form->resolveEntity();
            foreach ((new VariazioneCanoneModel($this->dataMapper))->getElenco($variazione->contrattoLocazione) as $altra) {
                if (((isset($variazione->id) === false) || ($altra->id !== $variazione->id)) && ($altra->validoDal->format('Y-m-d') === $variazione->validoDal->format('Y-m-d'))) {
                    $this->vars['messaggiFlash'][] = ['testo' => 'C\'è già una variazione del canone dalla stessa data.', 'tipo' => 'danger'];
                    $this->impostaVariabiliForm($form, $variazione);
                    return $this->formFiglio($variazione->contrattoLocazione, $titolo, 'contrattoLocazione/variazione', $form->getResponseType());
                }
            }
            $this->dataMapper->save($variazione);
            $this->aggiungiMessaggio('Variazione del canone salvata.');
            return $this->vaiAlContratto($variazione->contrattoLocazione);
        }
        $this->impostaVariabiliForm($form, $variazione);
        return $this->formFiglio($variazione->contrattoLocazione, $titolo, 'contrattoLocazione/variazione', $form->getResponseType());
    }

    private function gestisciCanone(Request $request, CanoneNonPercepito $canone, string $titolo): Response
    {
        $form = new CanoneNonPercepitoForm($canone);
        if ($this->isInvioValido($request, $form)) {
            $canone = $form->resolveEntity();
            $this->dataMapper->save($canone);
            $this->aggiungiMessaggio('Canoni non percepiti salvati.');
            return $this->vaiAlContratto($canone->contrattoLocazione);
        }
        $this->impostaVariabiliForm($form, $canone);
        return $this->formFiglio($canone->contrattoLocazione, $titolo, 'contrattoLocazione/canone', $form->getResponseType());
    }

    private function formFiglio(ContrattoLocazione $contratto, string $titolo, string $vista, $responseType): Response
    {
        $immobile = $contratto->immobile;
        $this->vars['contratto'] = $contratto;
        $this->vars['immobile'] = $immobile;
        $this->vars['pageTitle'] = $titolo;
        $this->vars['urlAnnulla'] = '/contratto-locazione/show/contratto-locazione/' . $contratto->id;
        $this->vars['briciole'] = ['Anagrafica' => '/soggetto/index', 'Immobili' => '/immobile/index', $immobile->getIdentificativo() => '/immobile/show/immobile/' . $immobile->id, 'Contratto' => '/contratto-locazione/show/contratto-locazione/' . $contratto->id, $titolo => null];
        return $this->render->generateView($vista, $this->vars, $responseType);
    }

    private function vaiAlContratto(ContrattoLocazione $contratto): Response
    {
        return $this->router->redirect('contratto-locazione/show/contratto-locazione/' . $contratto->id);
    }
}
