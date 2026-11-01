<?php

namespace Contabilita\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Models\SoggettoModel;
use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Entities\EsercizioContabile;
use Contabilita\Application\Enumerations\StatoFatturaElettronica;
use Contabilita\Application\Forms\EsercizioContabileForm;
use Contabilita\Application\Models\FatturaElettronicaModel;
use Contabilita\Application\Models\LiquidazioneIvaModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Models\RegistrazioneContabileModel;
use Contabilita\Application\Services\AperturaContabilita;
use Contabilita\Application\Services\PeriodiIva;
use Contabilita\Application\Services\RegimeIva;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Ingresso del modulo: si sceglie l'azienda (soggetto dell'Anagrafica) e
 * si lavora nella sua scheda dell'esercizio; da qui si apre la
 * contabilità e si gestiscono gli esercizi.
 */
class AziendaController extends ContabilitaController
{

    private const RISULTATI_PER_PAGINA = 25;

    /**
     * Soggetti dello studio (di default i soli clienti) con lo stato della
     * contabilità.
     */
    public function index(string $testo = '', bool $tutti = false, int $pagina = 1): Response
    {
        $soggettoModel = new SoggettoModel($this->dataMapper);
        $totale = $soggettoModel->contaRisultati($this->studio, $testo, null, null, $tutti === false);
        $pagine = max(1, (int) ceil($totale / self::RISULTATI_PER_PAGINA));
        $pagina = min(max(1, $pagina), $pagine);
        $pianoModel = new PianoContiModel($this->dataMapper);
        $fatturaModel = new FatturaElettronicaModel($this->dataMapper);
        $aziende = [];
        foreach ($soggettoModel->cerca($this->studio, $testo, null, null, $tutti === false, ($pagina - 1) * self::RISULTATI_PER_PAGINA, self::RISULTATI_PER_PAGINA) as $soggetto) {
            $piano = $pianoModel->getPerAzienda($soggetto);
            $aziende[] = ['soggetto' => $soggetto, 'aperta' => $piano !== null,
                'daRegistrare' => $piano === null ? 0 : $fatturaModel->conta($soggetto, StatoFatturaElettronica::daRegistrare, null, '')];
        }
        $this->vars['aziende'] = $aziende;
        $this->vars['totale'] = $totale;
        $this->vars['pagina'] = $pagina;
        $this->vars['pagine'] = $pagine;
        $this->vars['testo'] = $testo;
        $this->vars['inclusiTutti'] = $tutti;
        $this->vars['sezioneContabilita'] = 'aziende';
        $this->vars['briciole'] = ['Contabilità' => null];
        return $this->render->generateView('azienda/index', $this->vars);
    }

    /**
     * Scheda dell'azienda per l'esercizio: riepilogo, ultime registrazioni,
     * fatture da registrare e liquidazioni dell'anno; se la contabilità
     * non è aperta, il form di apertura.
     */
    public function show(Soggetto $soggetto, int $anno = 0): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->router->redirect('azienda/apri/soggetto/' . $soggetto->id);
        }
        $esercizio = $this->getEsercizio($soggetto, $anno);
        $this->vars['registrazioni'] = $esercizio === null ? [] : iterator_to_array((new RegistrazioneContabileModel($this->dataMapper))->cerca($esercizio, null, '', null, null, 0, 10), false);
        $this->vars['totaleRegistrazioni'] = $esercizio === null ? 0 : (new RegistrazioneContabileModel($this->dataMapper))->contaPerEsercizio($esercizio);
        $this->vars['fattureDaRegistrare'] = (new FatturaElettronicaModel($this->dataMapper))->conta($soggetto, StatoFatturaElettronica::daRegistrare, null, '');
        $annoIva = $esercizio?->anno ?? (int) date('Y');
        try {
            $periodicita = (new RegimeIva($this->dataMapper))->getPeriodicita($soggetto, $annoIva);
            $this->vars['periodicita'] = $periodicita;
            $this->vars['numeroPeriodi'] = PeriodiIva::getNumeroPeriodi($periodicita);
            $this->vars['erroreIva'] = null;
        } catch (\DomainException $errore) {
            $this->vars['periodicita'] = null;
            $this->vars['numeroPeriodi'] = 0;
            $this->vars['erroreIva'] = $errore->getMessage();
        }
        $this->vars['liquidazioni'] = (new LiquidazioneIvaModel($this->dataMapper))->getPerAnno($soggetto, $annoIva);
        $this->vars['annoIva'] = $annoIva;
        $this->vars['datiFiscali'] = (new RegimeIva($this->dataMapper))->getDatiFiscali($soggetto, $annoIva);
        $this->vars['sezioneAzienda'] = 'riepilogo';
        $this->vars['pageTitle'] = $soggetto->nominativo . ' · Contabilità';
        $this->impostaBriciole($soggetto, $esercizio?->anno);
        return $this->render->generateView('azienda/show', $this->vars);
    }

    /**
     * Apertura della contabilità: piano dei conti modello e primo
     * esercizio.
     */
    public function apri(Request $request, Soggetto $soggetto): Response
    {
        if ($this->caricaAzienda($soggetto) !== null) {
            return $this->vaiAllAzienda($soggetto);
        }
        $modelli = (new PianoContiModel($this->dataMapper))->getModelli($this->studio);
        $esercizio = new EsercizioContabile($this->dataMapper);
        [$esercizio->dataInizio, $esercizio->dataFine] = (new AperturaContabilita($this->dataMapper))->proponiEsercizio($soggetto, (int) date('Y'));
        $form = new EsercizioContabileForm($esercizio);
        if ($this->isInvioValido($request, $form)) {
            $modello = null;
            foreach ($modelli as $candidato) {
                if ($candidato->id === (int) ($request->input['modello'] ?? 0)) {
                    $modello = $candidato;
                }
            }
            $esercizio = $form->resolveEntity();
            try {
                if ($modello === null) {
                    throw new \DomainException('Scegliere il piano dei conti modello.');
                }
                $aperto = (new AperturaContabilita($this->dataMapper))->apri($soggetto, $modello, $esercizio->dataInizio, $esercizio->dataFine);
                $this->aggiungiMessaggio('Contabilità aperta con il piano "' . $modello->denominazione . '" e l\'esercizio ' . $aperto->getEtichetta() . '.');
                return $this->vaiAllAzienda($soggetto, $aperto->anno);
            } catch (\DomainException $errore) {
                $this->aggiungiMessaggio($errore->getMessage(), 'danger');
            }
        }
        $this->impostaVariabiliForm($form, $esercizio);
        $this->vars['modelli'] = $modelli;
        $this->vars['modelloScelto'] = (int) ($request->input['modello'] ?? ($modelli[0]->id ?? 0));
        $this->vars['datiFiscali'] = (new RegimeIva($this->dataMapper))->getDatiFiscali($soggetto, (int) date('Y'));
        $this->vars['pageTitle'] = $soggetto->nominativo . ' · Apertura della contabilità';
        $this->impostaBriciole($soggetto, null, 'Apertura della contabilità');
        return $this->render->generateView('azienda/apri', $this->vars, $form->getResponseType());
    }

    /**
     * Nuovo esercizio dell'azienda (di norma il successivo all'ultimo).
     */
    public function esercizio(Request $request, Soggetto $soggetto): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $apertura = new AperturaContabilita($this->dataMapper);
        $esercizio = new EsercizioContabile($this->dataMapper);
        [$esercizio->dataInizio, $esercizio->dataFine] = $apertura->proponiEsercizio($soggetto, (int) date('Y'));
        $form = new EsercizioContabileForm($esercizio);
        if ($this->isInvioValido($request, $form)) {
            $esercizio = $form->resolveEntity();
            try {
                $nuovo = $apertura->apriEsercizio($soggetto, $esercizio->dataInizio, $esercizio->dataFine);
                $this->aggiungiMessaggio('Esercizio ' . $nuovo->getEtichetta() . ' aperto.');
                return $this->vaiAllAzienda($soggetto, $nuovo->anno);
            } catch (\DomainException $errore) {
                $form->getFilterErrors()->dataInizioCustomMessage = $errore->getMessage();
            }
        }
        $this->impostaVariabiliForm($form, $esercizio);
        $this->getEsercizio($soggetto, 0);
        $this->vars['pageTitle'] = 'Nuovo esercizio';
        $this->impostaBriciole($soggetto, null, 'Nuovo esercizio');
        return $this->render->generateView('azienda/esercizio', $this->vars, $form->getResponseType());
    }

    /**
     * Chiude o riapre l'esercizio: chiuso, le registrazioni non si
     * modificano.
     */
    public function chiudi(Request $request, EsercizioContabile $esercizio, bool $riapri = false): Response
    {
        $this->autorizzaSoggetto($esercizio->soggetto);
        $this->verificaCsrf($request);
        $esercizio->chiuso = $riapri === false;
        $this->dataMapper->save($esercizio);
        $this->aggiungiMessaggio('Esercizio ' . $esercizio->getEtichetta() . ($riapri ? ' riaperto.' : ' chiuso: le registrazioni non sono più modificabili.'));
        return $this->vaiAllAzienda($esercizio->soggetto, $esercizio->anno);
    }
}
