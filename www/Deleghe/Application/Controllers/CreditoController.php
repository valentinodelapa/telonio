<?php

namespace Deleghe\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Deleghe\Application\BaseClasses\DelegheController;
use Deleghe\Application\Entities\Credito;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\TabellaF24;
use Deleghe\Application\Exceptions\DelegaNonModificabileException;
use Deleghe\Application\Forms\CreditoForm;
use Deleghe\Application\HelperClasses\Importo;
use Deleghe\Application\Models\CodiceTabellaF24Model;
use Deleghe\Application\Models\CreditoModel;
use Deleghe\Application\Models\VersamentoModel;
use Deleghe\Application\Services\Compensazioni;
use Deleghe\Application\Services\EsitoControllo;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Crediti del contribuente e loro utilizzo in compensazione nelle deleghe.
 */
class CreditoController extends DelegheController
{

    public function create(Request $request, Soggetto $soggetto, SezioneF24 $sezione = SezioneF24::erario): Response
    {
        $this->autorizzaSoggetto($soggetto);
        if (in_array($sezione, [SezioneF24::elide, SezioneF24::accise], true)) {
            $this->aggiungiMessaggio('La sezione ' . $sezione->getLabel() . ' non ammette crediti.', 'warning');
            return $this->vaiAlContribuente($soggetto, 'crediti');
        }
        $credito = new Credito($this->dataMapper);
        $credito->studio = $this->studio;
        $credito->soggetto = $soggetto;
        $credito->sezione = $sezione;
        if ($sezione->getSezioneCodici() !== null) {
            $credito->annoRiferimento = (int) date('Y') - 1;
        }
        return $this->gestisci($request, $credito, 'Nuovo credito: ' . $sezione->getLabel());
    }

    public function update(Request $request, Credito $credito): Response
    {
        $this->autorizzaSoggetto($credito->soggetto);
        return $this->gestisci($request, $credito, 'Modifica credito');
    }

    private function gestisci(Request $request, Credito $credito, string $titolo): Response
    {
        $form = new CreditoForm($credito);
        $creditoModel = new CreditoModel($this->dataMapper);
        $utilizzato = isset($credito->id) ? $creditoModel->getUtilizzato($credito) : 0;
        if ($this->isInvioValido($request, $form)) {
            $credito = $form->resolveEntity();
            if (EsitoControllo::centesimi($credito->importo) < $utilizzato) {
                $this->aggiungiMessaggio('L\'importo non può essere inferiore a quanto già utilizzato (€ ' . EsitoControllo::euro($utilizzato) . ').', 'danger');
                return $this->router->redirect('credito/update/credito/' . $credito->id);
            }
            $this->dataMapper->save($credito);
            $this->aggiungiMessaggio('Credito salvato.');
            return $this->vaiAlContribuente($credito->soggetto, 'crediti');
        }
        $this->impostaVariabiliForm($form, $credito);
        $this->vars['credito'] = $credito;
        $this->vars['campiRigo'] = CreditoForm::getCampiSezione($credito);
        $this->vars['utilizzato'] = $utilizzato;
        $this->vars['codiceTributoCorrente'] = $credito->codiceTributo?->getEtichetta();
        $tabelle = new CodiceTabellaF24Model($this->dataMapper);
        $this->vars['regioni'] = $credito->sezione === SezioneF24::regioni ? $tabelle->getVoci(TabellaF24::regioni) : [];
        $this->vars['altriEnti'] = $credito->sezione === SezioneF24::altriEnti ? $tabelle->getVoci(TabellaF24::altriEnti) : [];
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBricioleContribuente($credito->soggetto, $titolo);
        return $this->render->generateView('credito/form', $this->vars, $form->getResponseType());
    }

    public function delete(Request $request, Credito $credito): Response
    {
        $this->autorizzaSoggetto($credito->soggetto);
        $this->verificaCsrf($request);
        if ((new VersamentoModel($this->dataMapper))->countByCredito($credito) > 0) {
            $this->aggiungiMessaggio('Il credito è utilizzato in una o più deleghe: elimina prima gli utilizzi.', 'warning');
            return $this->vaiAlContribuente($credito->soggetto, 'crediti');
        }
        $this->dataMapper->delete($credito);
        $this->aggiungiMessaggio('Credito eliminato.');
        return $this->vaiAlContribuente($credito->soggetto, 'crediti');
    }

    /**
     * Utilizzo del credito nella delega per l'importo indicato (di default
     * il massimo tra residuo e capienza della delega).
     */
    public function compensa(Request $request, Delega $delega, Credito $credito): Response
    {
        $this->autorizza($delega);
        $this->verificaCsrf($request);
        $importo = Importo::normalizza(is_string($request->input['importo'] ?? null) ? $request->input['importo'] : '');
        try {
            $compensazioni = new Compensazioni($this->dataMapper);
            $centesimi = $importo === '' ? min((new CreditoModel($this->dataMapper))->getResiduo($credito), $compensazioni->getCapienza($delega, $credito)) : EsitoControllo::centesimi((float) $importo);
            $versamento = $compensazioni->compensa($delega, $credito, $centesimi / 100);
            $this->aggiungiMessaggio('Compensati € ' . Importo::euro($versamento->importoCredito) . ' del credito ' . $credito->getEtichetta() . '.');
        } catch (DelegaNonModificabileException | \InvalidArgumentException $errore) {
            $this->aggiungiMessaggio($errore->getMessage(), 'danger');
        }
        return $this->vaiAllaDelega($delega);
    }
}
