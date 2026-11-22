<?php

namespace Contabilita\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeDetrazioneIva;
use Anagrafica\Application\Models\CodiceAtecoModel;
use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Entities\Attivita;
use Contabilita\Application\Forms\AttivitaForm;
use Contabilita\Application\Models\AttivitaModel;
use Contabilita\Application\Models\LiquidazioneAttivitaModel;
use Contabilita\Application\Models\RegistroIvaModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Attività con contabilità separata dell'azienda (art. 36 D.P.R.
 * 633/1972), nella pagina dei registri IVA.
 */
class AttivitaController extends ContabilitaController
{

    public function create(Request $request, Soggetto $soggetto): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $attivita = new Attivita($this->dataMapper);
        $attivita->studio = $soggetto->studio;
        $attivita->soggetto = $soggetto;
        $attivita->numero = (new AttivitaModel($this->dataMapper))->getProssimoNumero($soggetto);
        $attivita->dalAnno = (int) date('Y');
        return $this->gestisci($request, $attivita, 'Nuova attività');
    }

    public function update(Request $request, Attivita $attivita): Response
    {
        $this->caricaAzienda($attivita->soggetto);
        return $this->gestisci($request, $attivita, $attivita->getEtichetta());
    }

    public function delete(Request $request, Attivita $attivita): Response
    {
        $soggetto = $attivita->soggetto;
        $this->caricaAzienda($soggetto);
        $this->verificaCsrf($request);
        $registri = array_filter(iterator_to_array((new RegistroIvaModel($this->dataMapper))->getElenco($soggetto), false), fn($registro) => $registro->attivita?->id === $attivita->id);
        if ((count($registri) > 0) || (count((new LiquidazioneAttivitaModel($this->dataMapper))->getPerAttivita($attivita)) > 0)) {
            $this->aggiungiMessaggio('L\'attività ha registri o liquidazioni: indicare l\'ultimo anno invece di eliminarla.', 'warning');
        } else {
            $this->dataMapper->delete($attivita);
            $this->aggiungiMessaggio('Attività eliminata.');
        }
        return $this->router->redirect('registro-iva/index/soggetto/' . $soggetto->id);
    }

    private function gestisci(Request $request, Attivita $attivita, string $titolo): Response
    {
        $soggetto = $attivita->soggetto;
        $form = new AttivitaForm($attivita);
        $codiceAteco = trim((string) ($request->input['ateco'] ?? ($attivita->codiceAteco?->codice ?? '')));
        if ($this->isInvioValido($request, $form)) {
            $attivita = $form->resolveEntity();
            $ateco = $codiceAteco === '' ? null : (new CodiceAtecoModel($this->dataMapper))->getByCodice(ClassificazioneAteco::ateco2025, $codiceAteco);
            $doppia = (new AttivitaModel($this->dataMapper))->getElenco($soggetto)[$attivita->numero] ?? null;
            $ente = (new \Anagrafica\Application\Models\DatiFiscaliModel($this->dataMapper))->getVigenteAl($soggetto, new \SismaFramework\Orm\CustomTypes\SismaDate($attivita->dalAnno . '-12-31'))?->enteNonCommerciale === true;
            if (($attivita->natura === \Contabilita\Application\Enumerations\NaturaAttivita::istituzionale) && ($ente === false)) {
                $this->aggiungiMessaggio('L\'attività istituzionale è propria degli enti non commerciali (casella dei dati fiscali dell\'Anagrafica).', 'danger');
            } elseif (($codiceAteco !== '') && ($ateco === null)) {
                $this->aggiungiMessaggio('Codice ATECO 2025 ' . $codiceAteco . ' non trovato.', 'danger');
            } elseif (($doppia !== null) && (($doppia->id ?? null) !== ($attivita->id ?? null))) {
                $this->aggiungiMessaggio('Esiste già l\'attività n. ' . $attivita->numero . '.', 'danger');
            } else {
                $attivita->codiceAteco = $ateco;
                $this->dataMapper->save($attivita);
                $this->aggiungiMessaggio($attivita->isCommerciale() ? 'Attività salvata: assegnarle i registri e i sezionali IVA e indicarla sui conti (attività predefinita).' : 'Attività istituzionale salvata: indicarla sui conti (attività predefinita) o sulle righe di prima nota.');
                return $this->router->redirect('registro-iva/index/soggetto/' . $soggetto->id);
            }
        }
        $this->impostaVariabiliForm($form, $attivita);
        $this->getEsercizio($soggetto, 0);
        $this->vars['attivita'] = $attivita;
        $this->vars['codiceAteco'] = $codiceAteco;
        $this->vars['periodicita'] = PeriodicitaIva::cases();
        $this->vars['nature'] = \Contabilita\Application\Enumerations\NaturaAttivita::cases();
        $this->vars['regimiOpzione'] = RegimeDetrazioneIva::getOpzionabili();
        $this->vars['regimiDetrazione'] = array_values(array_filter(RegimeDetrazioneIva::cases(), fn(RegimeDetrazioneIva $regime) => $regime !== RegimeDetrazioneIva::agricoloEsonerato));
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBriciole($soggetto, null, $titolo, ['Registri IVA' => '/registro-iva/index/soggetto/' . $soggetto->id]);
        return $this->render->generateView('attivita/form', $this->vars, $form->getResponseType());
    }
}
