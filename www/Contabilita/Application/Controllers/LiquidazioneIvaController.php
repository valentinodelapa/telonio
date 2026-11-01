<?php

namespace Contabilita\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Entities\LiquidazioneIva;
use Contabilita\Application\Models\AccontoIvaModel;
use Contabilita\Application\Models\LiquidazioneIvaModel;
use Contabilita\Application\Services\CalcoloLiquidazioneIva;
use Contabilita\Application\Services\ElaborazioneLiquidazione;
use Contabilita\Application\Services\PeriodiIva;
use Contabilita\Application\Services\RegimeIva;
use Contabilita\Application\Services\RequisitiVentilazione;
use Contabilita\Application\Services\VentilazioneCorrispettivi;
use Deleghe\Application\HelperClasses\Importo;
use Deleghe\Application\Models\VersamentoModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Liquidazioni periodiche IVA dell'azienda per anno: elenco dei periodi,
 * elaborazione (con i versamenti per le deleghe F24 e il giroconto),
 * prospetto e acconto.
 */
class LiquidazioneIvaController extends ContabilitaController
{

    public function index(Soggetto $soggetto, int $anno = 0): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $anno = $anno > 0 ? $anno : (int) date('Y');
        $this->getEsercizio($soggetto, $anno);
        $this->vars['annoIva'] = $anno;
        try {
            $periodicita = (new RegimeIva($this->dataMapper))->getPeriodicita($soggetto, $anno);
            $this->vars['erroreIva'] = null;
        } catch (\DomainException $errore) {
            $periodicita = null;
            $this->vars['erroreIva'] = $errore->getMessage();
        }
        $periodi = [];
        if ($periodicita !== null) {
            for ($periodo = 1; $periodo <= PeriodiIva::getNumeroPeriodi($periodicita); $periodo++) {
                $periodi[$periodo] = ['descrizione' => LiquidazioneIva::descriviPeriodo($periodicita, $anno, $periodo), 'scadenza' => PeriodiIva::getScadenza($periodicita, $anno, $periodo),
                    'fine' => PeriodiIva::getFine($periodicita, $anno, $periodo)];
            }
        }
        $this->vars['periodicita'] = $periodicita;
        $this->vars['requisitiVentilazione'] = ($periodicita !== null) && (new RegimeIva($this->dataMapper))->isVentilazione($soggetto, $anno)
                ? (new RequisitiVentilazione($this->dataMapper))->verifica($soggetto, $anno) : null;
        $this->vars['periodi'] = $periodi;
        $this->vars['liquidazioni'] = (new LiquidazioneIvaModel($this->dataMapper))->getPerAnno($soggetto, $anno);
        $this->vars['acconto'] = (new AccontoIvaModel($this->dataMapper))->getPerAnno($soggetto, $anno);
        $this->vars['scadenzaAcconto'] = PeriodiIva::getScadenzaAcconto($anno);
        $this->vars['anniIva'] = range((int) date('Y') + 1, (int) date('Y') - 5);
        $this->vars['sezioneAzienda'] = 'liquidazioni';
        $this->vars['pageTitle'] = $soggetto->nominativo . ' · Liquidazioni IVA ' . $anno;
        $this->impostaBriciole($soggetto, null, 'Liquidazioni IVA ' . $anno);
        return $this->render->generateView('liquidazioneIva/index', $this->vars);
    }

    /**
     * Elabora (o rielabora) la liquidazione del periodo.
     */
    public function elabora(Request $request, Soggetto $soggetto, int $anno, int $periodo): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $this->verificaCsrf($request);
        $credito = (float) Importo::normalizza((string) ($request->input['creditoAnnoPrecedente'] ?? '0'));
        $versamentiAuto = (float) Importo::normalizza((string) ($request->input['versamentiAuto'] ?? '0'));
        $creditiImposta = (float) Importo::normalizza((string) ($request->input['creditiImposta'] ?? '0'));
        try {
            $liquidazione = (new ElaborazioneLiquidazione($this->dataMapper))->elabora($soggetto, $anno, $periodo, $credito, isset($request->input['giroconto']), $versamentiAuto, $creditiImposta);
            $this->aggiungiMessaggio('Liquidazione di ' . $liquidazione->getDescrizionePeriodo() . ' elaborata: '
                    . ($liquidazione->importoDovuto > 0 ? 'da versare € ' . Importo::euro($liquidazione->importoDovuto) . ' entro il ' . $liquidazione->scadenza->format('d/m/Y') . ' (in Deleghe F24).'
                    : ($liquidazione->debitoRinviato > 0 ? 'debito di € ' . Importo::euro($liquidazione->debitoRinviato) . ' rinviato al periodo successivo.' : 'credito di € ' . Importo::euro($liquidazione->creditoFinale) . '.')));
            if ($liquidazione->differenze !== null) {
                $this->aggiungiMessaggio($liquidazione->differenze, 'warning');
            }
            return $this->router->redirect('liquidazione-iva/show/liquidazione/' . $liquidazione->id);
        } catch (\DomainException | \InvalidArgumentException $errore) {
            $this->aggiungiMessaggio('Liquidazione non elaborata: ' . $errore->getMessage(), 'danger');
            return $this->router->redirect('liquidazione-iva/index/soggetto/' . $soggetto->id . '/anno/' . $anno);
        }
    }

    /**
     * Prospetto della liquidazione con il riepilogo per registro e codice
     * IVA e il versamento registrato.
     */
    public function show(LiquidazioneIva $liquidazione, bool $stampa = false): Response
    {
        $soggetto = $liquidazione->soggetto;
        $this->caricaAzienda($soggetto);
        $this->getEsercizio($soggetto, $liquidazione->anno);
        $risultato = (new CalcoloLiquidazioneIva($this->dataMapper))->calcola($soggetto, PeriodiIva::getInizio($liquidazione->periodicita, $liquidazione->anno, $liquidazione->periodo),
                PeriodiIva::getFine($liquidazione->periodicita, $liquidazione->anno, $liquidazione->periodo));
        $this->vars['liquidazione'] = $liquidazione;
        $this->vars['riepilogo'] = $risultato->riepilogo;
        try {
            $this->vars['ventilazione'] = (new VentilazioneCorrispettivi($this->dataMapper))->calcola($soggetto, PeriodiIva::getInizio($liquidazione->periodicita, $liquidazione->anno, $liquidazione->periodo),
                    PeriodiIva::getFine($liquidazione->periodicita, $liquidazione->anno, $liquidazione->periodo));
        } catch (\DomainException $errore) {
            $this->vars['ventilazione'] = null;
        }
        $this->vars['registriVariati'] = (abs($risultato->ivaEsigibile + ($this->vars['ventilazione']?->imposta ?? 0) - $liquidazione->ivaEsigibile) > 0.005) || (abs($risultato->ivaDetraibile - $liquidazione->ivaDetraibile) > 0.005);
        $this->vars['versamenti'] = iterator_to_array((new VersamentoModel($this->dataMapper))->getGenerati($soggetto, LiquidazioneIva::ORIGINE, LiquidazioneIva::getRiferimento($liquidazione->anno, $liquidazione->periodo)), false);
        $this->vars['sezioneAzienda'] = 'liquidazioni';
        $this->vars['pageTitle'] = 'Liquidazione IVA ' . $liquidazione->getDescrizionePeriodo();
        $this->impostaBriciole($soggetto, null, $liquidazione->getDescrizionePeriodo(), ['Liquidazioni IVA ' . $liquidazione->anno => '/liquidazione-iva/index/soggetto/' . $soggetto->id . '/anno/' . $liquidazione->anno]);
        return $this->render->generateView($stampa ? 'liquidazioneIva/stampa' : 'liquidazioneIva/show', $this->vars);
    }

    public function delete(Request $request, LiquidazioneIva $liquidazione): Response
    {
        $soggetto = $liquidazione->soggetto;
        $this->caricaAzienda($soggetto);
        $this->verificaCsrf($request);
        $successiva = (new LiquidazioneIvaModel($this->dataMapper))->getPerPeriodo($soggetto, $liquidazione->anno, $liquidazione->periodo + 1);
        $differenze = (new ElaborazioneLiquidazione($this->dataMapper))->elimina($liquidazione);
        $this->aggiungiMessaggio('Liquidazione eliminata con il versamento da versare e il giroconto' . ($successiva === null ? '.' : ': rielaborare le liquidazioni successive.'));
        foreach ($differenze as $differenza) {
            $this->aggiungiMessaggio($differenza, 'warning');
        }
        return $this->router->redirect('liquidazione-iva/index/soggetto/' . $soggetto->id . '/anno/' . $liquidazione->anno);
    }
}
