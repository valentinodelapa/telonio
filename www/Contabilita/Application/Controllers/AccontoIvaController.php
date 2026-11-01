<?php

namespace Contabilita\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Entities\AccontoIva;
use Contabilita\Application\Enumerations\MetodoAccontoIva;
use Contabilita\Application\Forms\AccontoIvaForm;
use Contabilita\Application\Models\AccontoIvaModel;
use Contabilita\Application\Services\ElaborazioneAccontoIva;
use Contabilita\Application\Services\PeriodiIva;
use Contabilita\Application\Services\RegimeIva;
use Deleghe\Application\HelperClasses\Importo;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Acconto IVA dell'anno: scelta del metodo con la base proposta e
 * registrazione del versamento per le deleghe F24.
 */
class AccontoIvaController extends ContabilitaController
{

    public function form(Request $request, Soggetto $soggetto, int $anno, string $metodo = ''): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $elaborazione = new ElaborazioneAccontoIva($this->dataMapper);
        try {
            $periodicita = (new RegimeIva($this->dataMapper))->getPeriodicita($soggetto, $anno);
        } catch (\DomainException $errore) {
            $this->aggiungiMessaggio($errore->getMessage(), 'danger');
            return $this->router->redirect('liquidazione-iva/index/soggetto/' . $soggetto->id . '/anno/' . $anno);
        }
        $acconto = (new AccontoIvaModel($this->dataMapper))->getPerAnno($soggetto, $anno) ?? new AccontoIva($this->dataMapper);
        $acconto->studio = $soggetto->studio;
        $acconto->soggetto = $soggetto;
        $acconto->anno = $anno;
        $acconto->periodicita = $periodicita;
        $metodoScelto = MetodoAccontoIva::tryFrom($metodo);
        if ($metodoScelto !== null) {
            $acconto->metodo = $metodoScelto;
        }
        [$baseProposta, $provenienza] = $elaborazione->proponiBase($soggetto, $anno, $periodicita, $acconto->metodo);
        if (($baseProposta !== null) && (isset($acconto->id) === false || $metodoScelto !== null)) {
            $acconto->base = $baseProposta;
        }
        $form = new AccontoIvaForm($acconto);
        if ($this->isInvioValido($request, $form)) {
            $acconto = $form->resolveEntity();
            try {
                $acconto = $elaborazione->elabora($soggetto, $anno, $acconto->metodo, $acconto->base, $acconto->note);
                $this->aggiungiMessaggio($acconto->isDovuto()
                        ? 'Acconto IVA ' . $anno . ' di € ' . Importo::euro($acconto->importo) . ' registrato per il versamento entro il ' . PeriodiIva::getScadenzaAcconto($anno)->format('d/m/Y') . '.'
                        : 'Acconto IVA ' . $anno . ' non dovuto (inferiore a € 103,29): nessun versamento.');
                if ($acconto->differenze !== null) {
                    $this->aggiungiMessaggio($acconto->differenze, 'warning');
                }
                return $this->router->redirect('liquidazione-iva/index/soggetto/' . $soggetto->id . '/anno/' . $anno);
            } catch (\DomainException | \InvalidArgumentException $errore) {
                $this->aggiungiMessaggio($errore->getMessage(), 'danger');
            }
        }
        $this->impostaVariabiliForm($form, $acconto);
        $this->getEsercizio($soggetto, $anno);
        $this->vars['acconto'] = $acconto;
        $this->vars['annoIva'] = $anno;
        $this->vars['metodi'] = MetodoAccontoIva::cases();
        $this->vars['baseProposta'] = $baseProposta;
        $this->vars['provenienza'] = $provenienza;
        $this->vars['codiceTributo'] = PeriodiIva::getCodiceTributoAcconto($periodicita);
        $this->vars['sezioneAzienda'] = 'liquidazioni';
        $this->vars['pageTitle'] = 'Acconto IVA ' . $anno;
        $this->impostaBriciole($soggetto, null, 'Acconto IVA ' . $anno, ['Liquidazioni IVA ' . $anno => '/liquidazione-iva/index/soggetto/' . $soggetto->id . '/anno/' . $anno]);
        return $this->render->generateView('accontoIva/form', $this->vars, $form->getResponseType());
    }

    public function delete(Request $request, AccontoIva $acconto): Response
    {
        $soggetto = $acconto->soggetto;
        $this->caricaAzienda($soggetto);
        $this->verificaCsrf($request);
        foreach ((new ElaborazioneAccontoIva($this->dataMapper))->elimina($acconto) as $differenza) {
            $this->aggiungiMessaggio($differenza, 'warning');
        }
        $this->aggiungiMessaggio('Acconto eliminato: rielaborare la liquidazione dell\'ultimo periodo dell\'anno.');
        return $this->router->redirect('liquidazione-iva/index/soggetto/' . $soggetto->id . '/anno/' . $acconto->anno);
    }
}
