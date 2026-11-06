<?php

namespace Contabilita\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeDetrazioneIva;
use Anagrafica\Application\Models\CodiceAtecoModel;
use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Entities\AttivitaIva;
use Contabilita\Application\Forms\AttivitaIvaForm;
use Contabilita\Application\Models\AttivitaIvaModel;
use Contabilita\Application\Models\LiquidazioneAttivitaModel;
use Contabilita\Application\Models\RegistroIvaModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Attività con contabilità separata dell'azienda (art. 36 D.P.R.
 * 633/1972), nella pagina dei registri IVA.
 */
class AttivitaIvaController extends ContabilitaController
{

    public function create(Request $request, Soggetto $soggetto): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $attivita = new AttivitaIva($this->dataMapper);
        $attivita->studio = $soggetto->studio;
        $attivita->soggetto = $soggetto;
        $attivita->numero = (new AttivitaIvaModel($this->dataMapper))->getProssimoNumero($soggetto);
        $attivita->dalAnno = (int) date('Y');
        return $this->gestisci($request, $attivita, 'Nuova attività separata');
    }

    public function update(Request $request, AttivitaIva $attivita): Response
    {
        $this->caricaAzienda($attivita->soggetto);
        return $this->gestisci($request, $attivita, $attivita->getEtichetta());
    }

    public function delete(Request $request, AttivitaIva $attivita): Response
    {
        $soggetto = $attivita->soggetto;
        $this->caricaAzienda($soggetto);
        $this->verificaCsrf($request);
        $registri = array_filter(iterator_to_array((new RegistroIvaModel($this->dataMapper))->getElenco($soggetto), false), fn($registro) => $registro->attivitaIva?->id === $attivita->id);
        if ((count($registri) > 0) || (count((new LiquidazioneAttivitaModel($this->dataMapper))->getPerAttivita($attivita)) > 0)) {
            $this->aggiungiMessaggio('L\'attività ha registri o liquidazioni: indicare l\'ultimo anno invece di eliminarla.', 'warning');
        } else {
            $this->dataMapper->delete($attivita);
            $this->aggiungiMessaggio('Attività eliminata.');
        }
        return $this->router->redirect('registro-iva/index/soggetto/' . $soggetto->id);
    }

    private function gestisci(Request $request, AttivitaIva $attivita, string $titolo): Response
    {
        $soggetto = $attivita->soggetto;
        $form = new AttivitaIvaForm($attivita);
        $codiceAteco = trim((string) ($request->input['ateco'] ?? ($attivita->codiceAteco?->codice ?? '')));
        if ($this->isInvioValido($request, $form)) {
            $attivita = $form->resolveEntity();
            $ateco = $codiceAteco === '' ? null : (new CodiceAtecoModel($this->dataMapper))->getByCodice(ClassificazioneAteco::ateco2025, $codiceAteco);
            $doppia = (new AttivitaIvaModel($this->dataMapper))->getElenco($soggetto)[$attivita->numero] ?? null;
            if (($codiceAteco !== '') && ($ateco === null)) {
                $this->aggiungiMessaggio('Codice ATECO 2025 ' . $codiceAteco . ' non trovato.', 'danger');
            } elseif (($doppia !== null) && (($doppia->id ?? null) !== ($attivita->id ?? null))) {
                $this->aggiungiMessaggio('Esiste già l\'attività n. ' . $attivita->numero . '.', 'danger');
            } else {
                $attivita->codiceAteco = $ateco;
                $this->dataMapper->save($attivita);
                $this->aggiungiMessaggio('Attività salvata: assegnarle i registri e i sezionali IVA.');
                return $this->router->redirect('registro-iva/index/soggetto/' . $soggetto->id);
            }
        }
        $this->impostaVariabiliForm($form, $attivita);
        $this->getEsercizio($soggetto, 0);
        $this->vars['attivita'] = $attivita;
        $this->vars['codiceAteco'] = $codiceAteco;
        $this->vars['periodicita'] = PeriodicitaIva::cases();
        $this->vars['regimiDetrazione'] = array_values(array_filter(RegimeDetrazioneIva::cases(), fn(RegimeDetrazioneIva $regime) => $regime !== RegimeDetrazioneIva::agricoloEsonerato));
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBriciole($soggetto, null, $titolo, ['Registri IVA' => '/registro-iva/index/soggetto/' . $soggetto->id]);
        return $this->render->generateView('attivitaIva/form', $this->vars, $form->getResponseType());
    }
}
