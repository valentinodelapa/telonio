<?php

namespace GestioneStudio\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Anagrafica\Application\Services\StoricoService;
use GestioneStudio\Application\BaseClasses\GestioneStudioController;
use GestioneStudio\Application\Entities\AssegnazioneAdempimento;
use GestioneStudio\Application\Forms\AssegnazioneAdempimentoForm;
use GestioneStudio\Application\Models\AdempimentoModel;
use GestioneStudio\Application\Models\AssegnazioneAdempimentoModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Adempimenti a cui è tenuto il cliente, storicizzati come i dati
 * dell'Anagrafica: assegnazione, rettifica, variazione da una data (es.
 * nuovo responsabile) e annullamento.
 */
class AssegnazioneAdempimentoController extends GestioneStudioController
{

    public function create(Request $request, Soggetto $soggetto): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $assegnazione = new AssegnazioneAdempimento($this->dataMapper);
        $assegnazione->studio = $this->studio;
        $assegnazione->soggetto = $soggetto;
        $assegnazione->validoDal = new SismaDate(date('Y') . '-01-01');
        return $this->gestisci($request, $assegnazione, 'Nuovo adempimento del cliente', null);
    }

    public function update(Request $request, AssegnazioneAdempimento $assegnazione): Response
    {
        $this->autorizzaSoggetto($assegnazione->soggetto);
        return $this->gestisci($request, $assegnazione, 'Rettifica: ' . $assegnazione->adempimento->denominazione, $assegnazione->validoDal);
    }

    /**
     * Nuova versione dalla versione più recente: si indicano la decorrenza
     * e il nuovo responsabile, la precedente si chiude il giorno prima.
     */
    public function variazione(Request $request, AssegnazioneAdempimento $assegnazione): Response
    {
        $this->autorizzaSoggetto($assegnazione->soggetto);
        $variazione = new AssegnazioneAdempimento($this->dataMapper);
        foreach (['studio', 'soggetto', 'adempimento', 'responsabile', 'note'] as $proprieta) {
            $variazione->$proprieta = $assegnazione->$proprieta;
        }
        $variazione->validoDal = new SismaDate();
        return $this->gestisci($request, $variazione, 'Variazione: ' . $assegnazione->adempimento->denominazione, null);
    }

    public function delete(Request $request, AssegnazioneAdempimento $assegnazione): Response
    {
        $this->autorizzaSoggetto($assegnazione->soggetto);
        $this->verificaCsrf($request);
        $soggetto = $assegnazione->soggetto;
        (new StoricoService($this->dataMapper))->annulla($assegnazione, (new AssegnazioneAdempimentoModel($this->dataMapper))->getAmbito($assegnazione));
        $this->aggiungiMessaggio('Versione dell\'adempimento eliminata.');
        return $this->vaiAlCliente($soggetto);
    }

    private function gestisci(Request $request, AssegnazioneAdempimento $assegnazione, string $titolo, ?SismaDate $decorrenzaOriginale): Response
    {
        $form = new AssegnazioneAdempimentoForm($assegnazione);
        $assegnazioneModel = new AssegnazioneAdempimentoModel($this->dataMapper);
        if ($this->isInvioValido($request, $form)) {
            $assegnazione = $form->resolveEntity();
            $storico = new StoricoService($this->dataMapper);
            $ambito = $assegnazioneModel->getAmbito($assegnazione);
            $errore = $storico->verifica($assegnazione, $ambito);
            if ($errore === null) {
                $storico->registra($assegnazione, $ambito, $decorrenzaOriginale);
                $this->aggiungiMessaggio('Adempimento del cliente salvato.');
                return $this->vaiAlCliente($assegnazione->soggetto);
            }
            $form->getFilterErrors()->validoDalCustomMessage = $errore;
        }
        $this->impostaVariabiliForm($form, $assegnazione);
        $this->vars['assegnazione'] = $assegnazione;
        $this->vars['soggetto'] = $assegnazione->soggetto;
        $this->vars['nuova'] = isset($assegnazione->adempimento) === false;
        $this->vars['adempimenti'] = $this->getAdempimentiAssegnabili($assegnazione->soggetto);
        $this->vars['operatori'] = $this->getOperatori();
        $this->vars['storico'] = isset($assegnazione->adempimento) ? iterator_to_array($assegnazioneModel->getAmbito($assegnazione), false) : [];
        $this->vars['urlAnnulla'] = '/scadenzario/soggetto/soggetto/' . $assegnazione->soggetto->id;
        $this->vars['pageTitle'] = $titolo;
        $this->vars['sezione'] = 'clienti';
        $this->impostaBriciole(['Clienti' => '/scadenzario/clienti', $assegnazione->soggetto->nominativo => $this->vars['urlAnnulla'], $titolo => null]);
        return $this->render->generateView('assegnazioneAdempimento/form', $this->vars, $form->getResponseType());
    }

    /**
     * Adempimenti attivi per la scelta, con quelli proposti per il soggetto
     * (ambito, con i dati fiscali in vigore) per primi.
     *
     * @return array<string, array<int, string>> gruppo => id => denominazione
     */
    private function getAdempimentiAssegnabili(Soggetto $soggetto): array
    {
        $gruppi = ['Proposti per il soggetto' => [], 'Altri adempimenti' => []];
        $dati = (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($soggetto, new SismaDate());
        foreach ((new AdempimentoModel($this->dataMapper))->getElenco($this->studio) as $adempimento) {
            $gruppi[$adempimento->ambito->isProponibile($soggetto, $dati) ? 'Proposti per il soggetto' : 'Altri adempimenti'][$adempimento->id] = $adempimento->denominazione . ($adempimento->isDelCalendario() ? '' : ' (studio)');
        }
        return array_filter($gruppi);
    }

    private function vaiAlCliente(Soggetto $soggetto): Response
    {
        return $this->router->redirect('scadenzario/soggetto/soggetto/' . $soggetto->id);
    }
}
