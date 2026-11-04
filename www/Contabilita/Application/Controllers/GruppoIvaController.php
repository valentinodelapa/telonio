<?php

namespace Contabilita\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Entities\GruppoIva;
use Contabilita\Application\Entities\LiquidazioneGruppoIva;
use Contabilita\Application\Entities\LiquidazioneIva;
use Contabilita\Application\Entities\PartecipanteGruppoIva;
use Contabilita\Application\Forms\GruppoIvaForm;
use Contabilita\Application\Forms\PartecipanteGruppoIvaForm;
use Contabilita\Application\Models\AccontoIvaModel;
use Contabilita\Application\Models\ComunicazioneLipeModel;
use Contabilita\Application\Models\GruppoIvaModel;
use Contabilita\Application\Models\LiquidazioneGruppoIvaModel;
use Contabilita\Application\Models\LiquidazioneIvaModel;
use Contabilita\Application\Models\PartecipanteGruppoIvaModel;
use Contabilita\Application\Services\Lipe\PreparazioneLipe;
use Contabilita\Application\Services\LiquidazioneGruppo;
use Contabilita\Application\Services\PeriodiIva;
use Deleghe\Application\HelperClasses\Importo;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * IVA di gruppo (art. 73, c. 3, D.P.R. 633/1972) dalla pagina della
 * controllante: opzione e società partecipanti, liquidazioni periodiche
 * del gruppo con il versamento, acconto e comunicazioni del gruppo. Dalla
 * pagina di una controllata si vede il gruppo a cui partecipa.
 */
class GruppoIvaController extends ContabilitaController
{

    public function index(Soggetto $soggetto, int $anno = 0): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $anno = $anno > 0 ? $anno : (int) date('Y');
        $this->getEsercizio($soggetto, $anno);
        $servizio = new LiquidazioneGruppo($this->dataMapper);
        $gruppi = (new GruppoIvaModel($this->dataMapper))->getTuttiPerControllante($soggetto);
        $gruppo = (new GruppoIvaModel($this->dataMapper))->getPerControllante($soggetto, $anno);
        $this->vars['annoIva'] = $anno;
        $this->vars['gruppi'] = $gruppi;
        $this->vars['gruppo'] = $gruppo;
        $this->vars['partecipazioni'] = (new PartecipanteGruppoIvaModel($this->dataMapper))->getTuttiPerControllata($soggetto);
        $this->vars['partecipanti'] = [];
        $this->vars['periodi'] = [];
        $this->vars['liquidazioni'] = [];
        $this->vars['requisiti'] = [];
        $this->vars['acconto'] = null;
        $this->vars['comunicazioni'] = [];
        $this->vars['periodicitaGruppo'] = null;
        $this->vars['trimestriSeparati'] = [];
        $this->vars['conTrimestrali'] = false;
        if ($gruppo !== null) {
            $periodicita = $servizio->getPeriodicita($gruppo, $anno);
            $this->vars['periodicitaGruppo'] = $periodicita;
            $this->vars['partecipanti'] = (new PartecipanteGruppoIvaModel($this->dataMapper))->getPartecipanti($gruppo);
            $this->vars['requisiti'] = $servizio->verificaControllo($gruppo, $anno);
            for ($periodo = 1; $periodo <= PeriodiIva::getNumeroPeriodi($periodicita); $periodo++) {
                $this->vars['periodi'][$periodo] = ['descrizione' => LiquidazioneIva::descriviPeriodo($periodicita, $anno, $periodo), 'scadenza' => PeriodiIva::getScadenza($periodicita, $anno, $periodo)];
            }
            $this->vars['liquidazioni'] = (new LiquidazioneGruppoIvaModel($this->dataMapper))->getPerAnno($gruppo, $anno);
            $this->vars['trimestriSeparati'] = (new LiquidazioneGruppoIvaModel($this->dataMapper))->getPerAnno($gruppo, $anno, true);
            $this->vars['conTrimestrali'] = ($periodicita->value === 'M') && $servizio->hasTrimestrali($gruppo, $anno);
            $this->vars['acconto'] = (new AccontoIvaModel($this->dataMapper))->getPerAnno($soggetto, $anno, $gruppo);
            $this->vars['comunicazioni'] = (new ComunicazioneLipeModel($this->dataMapper))->getPerAnno($soggetto, $anno, $gruppo);
        }
        $this->vars['anniIva'] = range((int) date('Y') + 1, (int) date('Y') - 5);
        $this->vars['sezioneAzienda'] = 'gruppoIva';
        $this->vars['pageTitle'] = $soggetto->nominativo . ' · IVA di gruppo ' . $anno;
        $this->impostaBriciole($soggetto, null, 'IVA di gruppo ' . $anno);
        return $this->render->generateView('gruppoIva/index', $this->vars);
    }

    public function create(Request $request, Soggetto $soggetto): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $gruppo = new GruppoIva($this->dataMapper);
        $gruppo->studio = $soggetto->studio;
        $gruppo->controllante = $soggetto;
        $gruppo->annoInizio = (int) date('Y') + 1;
        return $this->gestisci($request, $gruppo, 'Opzione per l\'IVA di gruppo');
    }

    public function update(Request $request, GruppoIva $gruppo): Response
    {
        $this->caricaAzienda($gruppo->controllante);
        return $this->gestisci($request, $gruppo, $gruppo->getEtichetta());
    }

    public function delete(Request $request, GruppoIva $gruppo): Response
    {
        $soggetto = $gruppo->controllante;
        $this->caricaAzienda($soggetto);
        $this->verificaCsrf($request);
        $liquidazioni = (new LiquidazioneIvaModel($this->dataMapper))->countByGruppoIva($gruppo) + (new LiquidazioneGruppoIvaModel($this->dataMapper))->countByGruppoIva($gruppo);
        if ($liquidazioni > 0) {
            $this->aggiungiMessaggio('Il gruppo ha liquidazioni: indicare l\'anno della revoca invece di eliminarlo.', 'warning');
        } else {
            $this->dataMapper->delete($gruppo);
            $this->aggiungiMessaggio('Gruppo IVA eliminato.');
        }
        return $this->router->redirect('gruppo-iva/index/soggetto/' . $soggetto->id);
    }

    public function partecipante(Request $request, GruppoIva $gruppo, int $partecipante = 0): Response
    {
        $soggetto = $gruppo->controllante;
        $this->caricaAzienda($soggetto);
        $entita = $partecipante > 0 ? (new PartecipanteGruppoIvaModel($this->dataMapper))->getEntityById($partecipante) : null;
        if (($entita !== null) && ($entita->gruppoIva->id !== $gruppo->id)) {
            $entita = null;
        }
        if ($entita === null) {
            $entita = new PartecipanteGruppoIva($this->dataMapper);
            $entita->gruppoIva = $gruppo;
            $entita->annoIngresso = max($gruppo->annoInizio, (int) date('Y'));
        }
        $form = new PartecipanteGruppoIvaForm($entita);
        if ($this->isInvioValido($request, $form)) {
            $entita = $form->resolveEntity();
            $this->autorizzaSoggetto($entita->soggetto);
            if (($entita->soggetto->studio->id !== $soggetto->studio->id) || ($entita->soggetto->id === $soggetto->id) || (($entita->detentore !== null) && ($entita->detentore->studio->id !== $soggetto->studio->id))) {
                $this->aggiungiMessaggio('Indicare una società dello studio diversa dalla controllante.', 'danger');
            } else {
                $this->dataMapper->save($entita);
                $this->aggiungiMessaggio($entita->soggetto->nominativo . ' salvata nel gruppo.');
                return $this->router->redirect('gruppo-iva/index/soggetto/' . $soggetto->id);
            }
        }
        $this->impostaVariabiliForm($form, $entita);
        $this->getEsercizio($soggetto, 0);
        $this->vars['gruppo'] = $gruppo;
        $this->vars['partecipante'] = $entita;
        $this->vars['pageTitle'] = isset($entita->id) ? $entita->soggetto->nominativo : 'Nuova società del gruppo';
        $this->impostaBriciole($soggetto, null, $this->vars['pageTitle'], ['IVA di gruppo' => '/gruppo-iva/index/soggetto/' . $soggetto->id]);
        return $this->render->generateView('gruppoIva/partecipante', $this->vars, $form->getResponseType());
    }

    public function eliminaPartecipante(Request $request, PartecipanteGruppoIva $partecipante): Response
    {
        $soggetto = $partecipante->gruppoIva->controllante;
        $this->caricaAzienda($soggetto);
        $this->verificaCsrf($request);
        if ((new LiquidazioneIvaModel($this->dataMapper))->countBySoggettoAndGruppoIva($partecipante->soggetto, $partecipante->gruppoIva) > 0) {
            $this->aggiungiMessaggio('La società ha liquidazioni trasferite al gruppo: indicare l\'anno di uscita invece di eliminarla.', 'warning');
        } else {
            $this->dataMapper->delete($partecipante);
            $this->aggiungiMessaggio('Società tolta dal gruppo.');
        }
        return $this->router->redirect('gruppo-iva/index/soggetto/' . $soggetto->id);
    }

    /**
     * Elabora (o rielabora) la liquidazione di gruppo del periodo.
     */
    public function elabora(Request $request, GruppoIva $gruppo, int $anno, int $periodo): Response
    {
        $soggetto = $gruppo->controllante;
        $this->caricaAzienda($soggetto);
        $this->verificaCsrf($request);
        $credito = (float) Importo::normalizza((string) ($request->input['creditoAnnoPrecedente'] ?? '0'));
        try {
            $servizio = new LiquidazioneGruppo($this->dataMapper);
            $liquidazione = isset($request->input['trimestre']) ? $servizio->elaboraTrimestre($gruppo, $anno, $periodo, $credito)
                    : $servizio->elabora($gruppo, $anno, $periodo, $credito, ($request->input['anticipa'] ?? '1') === '1');
            $this->aggiungiMessaggio('Liquidazione di gruppo di ' . $liquidazione->getDescrizionePeriodo() . ' elaborata: '
                    . ($liquidazione->importoDovuto > 0 ? 'da versare € ' . Importo::euro($liquidazione->importoDovuto) . ' entro il ' . $liquidazione->scadenza->format('d/m/Y') . ' (in Deleghe F24 della controllante).'
                    : ($liquidazione->debitoRinviato > 0 ? 'debito di € ' . Importo::euro($liquidazione->debitoRinviato) . ' rinviato al periodo successivo.' : 'credito del gruppo di € ' . Importo::euro($liquidazione->creditoFinale) . '.')));
            if ($liquidazione->differenze !== null) {
                $this->aggiungiMessaggio($liquidazione->differenze, 'warning');
            }
        } catch (\DomainException | \InvalidArgumentException $errore) {
            $this->aggiungiMessaggio('Liquidazione di gruppo non elaborata: ' . $errore->getMessage(), 'danger');
        }
        return $this->router->redirect('gruppo-iva/index/soggetto/' . $soggetto->id . '/anno/' . $anno);
    }

    public function eliminaLiquidazione(Request $request, LiquidazioneGruppoIva $liquidazione): Response
    {
        $soggetto = $liquidazione->gruppoIva->controllante;
        $this->caricaAzienda($soggetto);
        $this->verificaCsrf($request);
        foreach ((new LiquidazioneGruppo($this->dataMapper))->elimina($liquidazione) as $differenza) {
            $this->aggiungiMessaggio($differenza, 'warning');
        }
        $this->aggiungiMessaggio('Liquidazione di gruppo eliminata con il versamento da versare.');
        return $this->router->redirect('gruppo-iva/index/soggetto/' . $soggetto->id . '/anno/' . $liquidazione->anno);
    }

    /**
     * Prepara la comunicazione delle liquidazioni periodiche del gruppo
     * per il trimestre.
     */
    public function lipe(Request $request, GruppoIva $gruppo, int $anno, int $trimestre): Response
    {
        $soggetto = $gruppo->controllante;
        $this->caricaAzienda($soggetto);
        $this->verificaCsrf($request);
        try {
            $comunicazione = (new PreparazioneLipe($this->dataMapper))->preparaGruppo($gruppo, $anno, $trimestre);
            $this->aggiungiMessaggio('Comunicazione del gruppo del ' . $comunicazione->getDescrizione() . ' preparata.');
            return $this->router->redirect('comunicazione-lipe/show/comunicazione/' . $comunicazione->id);
        } catch (\DomainException $errore) {
            $this->aggiungiMessaggio($errore->getMessage(), 'danger');
            return $this->router->redirect('gruppo-iva/index/soggetto/' . $soggetto->id . '/anno/' . $anno);
        }
    }

    private function gestisci(Request $request, GruppoIva $gruppo, string $titolo): Response
    {
        $soggetto = $gruppo->controllante;
        $form = new GruppoIvaForm($gruppo);
        if ($this->isInvioValido($request, $form)) {
            $gruppo = $form->resolveEntity();
            if (($gruppo->maggioranza !== null) && ($gruppo->maggioranza->studio->id !== $soggetto->studio->id)) {
                $gruppo->maggioranza = null;
            }
            $this->dataMapper->save($gruppo);
            $this->aggiungiMessaggio('Gruppo IVA salvato: aggiungere le società controllate partecipanti.');
            return $this->router->redirect('gruppo-iva/index/soggetto/' . $soggetto->id);
        }
        $this->impostaVariabiliForm($form, $gruppo);
        $this->getEsercizio($soggetto, 0);
        $this->vars['gruppo'] = $gruppo;
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBriciole($soggetto, null, $titolo, ['IVA di gruppo' => '/gruppo-iva/index/soggetto/' . $soggetto->id]);
        return $this->render->generateView('gruppoIva/form', $this->vars, $form->getResponseType());
    }
}
