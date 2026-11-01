<?php

namespace Contabilita\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Entities\RegistroIva;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Forms\RegistroIvaForm;
use Contabilita\Application\Models\RegistrazioneContabileModel;
use Contabilita\Application\Models\RegistroIvaModel;
use Contabilita\Application\Models\StampaDefinitivaModel;
use Contabilita\Application\Services\StampaRegistroIva;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Registri IVA e sezionali dell'azienda e loro stampa per periodo.
 */
class RegistroIvaController extends ContabilitaController
{

    public function index(Soggetto $soggetto): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $this->getEsercizio($soggetto, 0);
        $this->vars['registri'] = iterator_to_array((new RegistroIvaModel($this->dataMapper))->getElenco($soggetto), false);
        $this->vars['mesi'] = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
        $this->vars['sezioneAzienda'] = 'registri';
        $this->vars['pageTitle'] = $soggetto->nominativo . ' · Registri IVA';
        $this->impostaBriciole($soggetto, null, 'Registri IVA');
        return $this->render->generateView('registroIva/index', $this->vars);
    }

    public function create(Request $request, Soggetto $soggetto): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $registro = new RegistroIva($this->dataMapper);
        $registro->studio = $soggetto->studio;
        $registro->soggetto = $soggetto;
        return $this->gestisci($request, $registro, 'Nuovo sezionale IVA');
    }

    public function update(Request $request, RegistroIva $registro): Response
    {
        $this->caricaAzienda($registro->soggetto);
        return $this->gestisci($request, $registro, $registro->getEtichetta());
    }

    public function delete(Request $request, RegistroIva $registro): Response
    {
        $soggetto = $registro->soggetto;
        $this->caricaAzienda($soggetto);
        $this->verificaCsrf($request);
        if (($registro->sezionale === '') || ((new RegistrazioneContabileModel($this->dataMapper))->countByRegistroIva($registro) > 0)
                || ((new StampaDefinitivaModel($this->dataMapper))->countByRegistroIva($registro) > 0)) {
            $this->aggiungiMessaggio('Il registro principale e i sezionali con documenti o stampe definitive non si eliminano: renderli non attivi.', 'warning');
        } else {
            $this->dataMapper->delete($registro);
            $this->aggiungiMessaggio('Sezionale eliminato.');
        }
        return $this->router->redirect('registro-iva/index/soggetto/' . $soggetto->id);
    }

    /**
     * Stampa del registro per il periodo (di default il mese corrente).
     */
    public function stampa(RegistroIva $registro, string $dal = '', string $al = ''): Response
    {
        $soggetto = $registro->soggetto;
        $this->caricaAzienda($soggetto);
        $inizio = self::data($dal, date('Y-m-01'));
        $fine = self::data($al, (new \DateTimeImmutable($inizio->format('Y-m-d')))->format('Y-m-t'));
        $this->vars['registro'] = $registro;
        $this->vars['dal'] = $inizio;
        $this->vars['al'] = $fine;
        $this->vars['dati'] = (new StampaRegistroIva($this->dataMapper))->dati($registro, $inizio, $fine);
        $this->vars['pageTitle'] = $registro->getEtichetta() . ' ' . $inizio->format('d/m/Y') . ' - ' . $fine->format('d/m/Y');
        return $this->render->generateView('registroIva/stampa', $this->vars);
    }

    private function gestisci(Request $request, RegistroIva $registro, string $titolo): Response
    {
        $soggetto = $registro->soggetto;
        $form = new RegistroIvaForm($registro);
        if ($this->isInvioValido($request, $form)) {
            $registro = $form->resolveEntity();
            $this->dataMapper->save($registro);
            $this->aggiungiMessaggio('Registro salvato.');
            return $this->router->redirect('registro-iva/index/soggetto/' . $soggetto->id);
        }
        $this->impostaVariabiliForm($form, $registro);
        $this->getEsercizio($soggetto, 0);
        $this->vars['registro'] = $registro;
        $this->vars['tipi'] = TipoRegistroIva::cases();
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBriciole($soggetto, null, $titolo, ['Registri IVA' => '/registro-iva/index/soggetto/' . $soggetto->id]);
        return $this->render->generateView('registroIva/form', $this->vars, $form->getResponseType());
    }
}
