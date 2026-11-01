<?php

namespace Contabilita\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Entities\PianoConti;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Enumerations\LivelloConto;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Enumerations\VoceCee;
use Contabilita\Application\Forms\ContoForm;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\ControparteModel;
use Contabilita\Application\Models\RigaContabileModel;
use Contabilita\Application\Models\RigaIvaModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Conti dei piani dello studio e delle aziende (i piani della suite non si
 * modificano). Il nuovo conto riceve di default natura e voce CEE del
 * conto superiore; si elimina solo senza sottoconti né movimenti.
 */
class ContoController extends ContabilitaController
{

    public function create(Request $request, PianoConti $piano, int $padre = 0): Response
    {
        $this->autorizzaPiano($piano);
        $conto = new Conto($this->dataMapper);
        $conto->pianoConti = $piano;
        $superiore = $padre === 0 ? null : (new ContoModel($this->dataMapper))->getEntityById($padre);
        if (($superiore !== null) && ($superiore->pianoConti->id !== $piano->id)) {
            $superiore = null;
        }
        if ($superiore !== null) {
            $conto->codice = $superiore->livello === LivelloConto::conto ? (new ContoModel($this->dataMapper))->getProssimoCodice($superiore) : $superiore->codice . '.';
            $conto->natura = $superiore->natura;
            $conto->voceCee = $superiore->voceCee;
        }
        return $this->gestisci($request, $conto, 'Nuovo conto');
    }

    public function update(Request $request, Conto $conto): Response
    {
        $this->autorizzaPiano($conto->pianoConti);
        return $this->gestisci($request, $conto, 'Conto ' . $conto->codice);
    }

    public function delete(Request $request, Conto $conto): Response
    {
        $piano = $conto->pianoConti;
        $this->autorizzaPiano($piano);
        $this->verificaCsrf($request);
        $model = new ContoModel($this->dataMapper);
        $usato = ((new RigaContabileModel($this->dataMapper))->contaPerConto($conto) > 0) || ((new RigaIvaModel($this->dataMapper))->countByConto($conto) > 0)
                || ((new ControparteModel($this->dataMapper))->countByContoCliente($conto) + (new ControparteModel($this->dataMapper))->countByContoFornitore($conto) > 0);
        if ($model->contaFigli($conto) > 0) {
            $this->aggiungiMessaggio('Il conto ha conti o sottoconti: eliminarli prima.', 'warning');
        } elseif ($usato) {
            $this->aggiungiMessaggio('Il conto è usato nelle registrazioni o nel partitario: renderlo non attivo invece di eliminarlo.', 'warning');
        } else {
            $this->dataMapper->delete($conto);
            $this->aggiungiMessaggio('Conto ' . $conto->codice . ' eliminato.');
        }
        return $this->router->redirect('piano-conti/show/piano/' . $piano->id);
    }

    /**
     * Ricerca dei sottoconti attivi del piano dell'azienda, per
     * l'autocompletamento.
     */
    public function cerca(Soggetto $soggetto, string $testo = ''): Response
    {
        $risultati = [];
        $piano = $this->caricaAzienda($soggetto);
        if (($piano !== null) && (mb_strlen(trim($testo)) >= 1)) {
            foreach ((new ContoModel($this->dataMapper))->cercaMovimentabili($piano, $testo) as $conto) {
                $risultati[] = ['id' => $conto->id, 'etichetta' => $conto->getEtichetta()];
            }
        }
        return $this->render->generateJson($risultati);
    }

    private function gestisci(Request $request, Conto $conto, string $titolo): Response
    {
        $form = new ContoForm($conto);
        $piano = $conto->pianoConti;
        if ($this->isInvioValido($request, $form)) {
            $conto = $form->resolveEntity();
            if (isset($conto->id) === false) {
                $conto->livello = LivelloConto::daCodice($conto->codice);
                $codicePadre = LivelloConto::codicePadre($conto->codice);
                $conto->parentConto = $codicePadre === null ? null : (new ContoModel($this->dataMapper))->getPerCodice($piano, $codicePadre);
            }
            $this->dataMapper->save($conto);
            $this->aggiungiMessaggio('Conto ' . $conto->getEtichetta() . ' salvato.');
            return $this->router->redirect('piano-conti/show/piano/' . $piano->id);
        }
        $this->impostaVariabiliForm($form, $conto);
        $this->vars['conto'] = $conto;
        $this->vars['pianoConti'] = $piano;
        $this->vars['nature'] = NaturaConto::cases();
        $this->vars['funzioni'] = FunzioneConto::cases();
        $this->vars['vociCee'] = VoceCee::perSezione();
        $this->vars['pageTitle'] = $titolo;
        if ($piano->soggetto !== null) {
            $this->impostaBriciole($piano->soggetto, null, $titolo, ['Piano dei conti' => '/piano-conti/show/piano/' . $piano->id]);
        } else {
            $this->vars['briciole'] = ['Contabilità' => '/azienda/index', 'Piani dei conti' => '/piano-conti/index', $piano->denominazione => '/piano-conti/show/piano/' . $piano->id, $titolo => null];
        }
        return $this->render->generateView('conto/form', $this->vars, $form->getResponseType());
    }

    private function autorizzaPiano(PianoConti $piano): void
    {
        if ($piano->soggetto !== null) {
            $this->autorizzaSoggetto($piano->soggetto);
            return;
        }
        $this->autorizzaStudio($piano->studio, true);
    }
}
