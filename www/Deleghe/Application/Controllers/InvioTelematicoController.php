<?php

namespace Deleghe\Application\Controllers;

use Deleghe\Application\BaseClasses\DelegheController;
use Deleghe\Application\Entities\InvioTelematico;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Models\DelegaModel;
use Deleghe\Application\Models\IntermediarioModel;
use Deleghe\Application\Models\InvioTelematicoModel;
use Deleghe\Application\Services\ControlloDelega;
use Deleghe\Application\Services\Telematico\GeneratoreFileF24;
use SismaFramework\Core\Exceptions\AccessDeniedException;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDateTime;

/**
 * Invii telematici delle deleghe confermate: generazione del file per
 * Entratel (da controllare, autenticare e trasmettere con il software
 * dell'Agenzia), storico dei file e annullamento di un invio scartato.
 */
class InvioTelematicoController extends DelegheController
{

    private const MASSIMO_PROPOSTE = 200;

    public function index(): Response
    {
        $this->vars['invii'] = (new InvioTelematicoModel($this->dataMapper))->getElenco($this->studio);
        $this->vars['briciole'] = ['Deleghe F24' => '/contribuente/index', 'Invii telematici' => null];
        return $this->render->generateView('invioTelematico/index', $this->vars);
    }

    /**
     * Elenco delle deleghe confermate con i problemi che impediscono
     * l'invio; l'invio del form genera il file con quelle selezionate.
     */
    public function create(Request $request, int $intermediario = 0, bool $addebito = false): Response
    {
        $intermediarioModel = new IntermediarioModel($this->dataMapper);
        $intermediari = $intermediarioModel->getElenco($this->studio);
        if (count($intermediari) === 0) {
            $this->aggiungiMessaggio('Inserisci prima l\'intermediario Entratel dello studio.', 'warning');
            return $this->router->redirect('intermediario/create');
        }
        $scelto = $intermediarioModel->getEntityById((int) ($request->input['intermediario'] ?? $intermediario)) ?? $intermediari[0];
        if ($scelto->studio->id !== $this->studio->id) {
            throw new AccessDeniedException();
        }
        $addebitoIntermediario = isset($request->input['addebito']) ? $request->input['addebito'] === '1' : $addebito;
        $generatore = new GeneratoreFileF24($this->dataMapper);
        $controllo = new ControlloDelega($this->dataMapper);
        $voci = [];
        foreach ((new DelegaModel($this->dataMapper))->cerca($this->studio, null, StatoDelega::confermata, null, null, 0, self::MASSIMO_PROPOSTE) as $delega) {
            $esito = $controllo->verifica($delega);
            $voci[$delega->id] = ['delega' => $delega, 'esito' => $esito, 'problemi' => $generatore->verificaDelega($delega, $esito, $addebitoIntermediario)];
        }
        $problemiIntermediario = $generatore->verificaIntermediario($scelto, $addebitoIntermediario);
        if ((($request->input['genera'] ?? null) === '1') && ($request->server['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->verificaCsrf($request);
            $selezionate = array_intersect_key($voci, array_flip(array_map('intval', (array) ($request->input['deleghe'] ?? []))));
            $selezionate = array_filter($selezionate, fn($voce) => count($voce['problemi']) === 0);
            if ((count($problemiIntermediario) === 0) && (count($selezionate) > 0)) {
                return $this->genera($scelto, $addebitoIntermediario, $selezionate, $generatore);
            }
            $this->vars['erroreInvio'] = count($selezionate) === 0 ? 'Seleziona almeno una delega senza problemi.' : 'Completa i dati dell\'intermediario.';
        }
        $this->vars['intermediari'] = $intermediari;
        $this->vars['intermediarioScelto'] = $scelto;
        $this->vars['addebitoIntermediario'] = $addebitoIntermediario;
        $this->vars['problemiIntermediario'] = $problemiIntermediario;
        $this->vars['voci'] = $voci;
        $this->vars['pageTitle'] = 'Nuovo invio telematico';
        $this->vars['briciole'] = ['Deleghe F24' => '/contribuente/index', 'Invii telematici' => '/invio-telematico/index', 'Nuovo invio' => null];
        return $this->render->generateView('invioTelematico/create', $this->vars);
    }

    private function genera($intermediario, bool $addebitoIntermediario, array $voci, GeneratoreFileF24 $generatore): Response
    {
        $contenuto = $generatore->genera($intermediario, $voci, $addebitoIntermediario);
        $primaTransazione = $this->dataMapper->startTransaction();
        $invio = new InvioTelematico($this->dataMapper);
        $invio->studio = $this->studio;
        $invio->intermediario = $intermediario;
        $invio->addebitoIntermediario = $addebitoIntermediario;
        $invio->numeroDeleghe = count($voci);
        $invio->totale = array_sum(array_map(fn($voce) => $voce['esito']->getSaldo(), $voci)) / 100;
        $invio->nomeFile = 'F24_' . date('Ymd_His') . '.txt';
        $invio->contenuto = $contenuto;
        $this->dataMapper->save($invio);
        foreach ($voci as ['delega' => $delega]) {
            $delega->stato = StatoDelega::trasmessa;
            $delega->invioTelematico = $invio;
            $delega->dataModifica = new SismaDateTime();
            $this->dataMapper->save($delega);
        }
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio(sprintf('File generato con %d deleghe: scaricalo, controllalo e autenticalo con il software dell\'Agenzia, poi trasmettilo con Entratel.', count($voci)));
        return $this->router->redirect('invio-telematico/show/invio/' . $invio->id);
    }

    public function show(InvioTelematico $invio): Response
    {
        $this->autorizzaInvio($invio);
        $this->vars['invio'] = $invio;
        $this->vars['deleghe'] = (new DelegaModel($this->dataMapper))->getByInvioTelematico($invio, null, ['id' => 'ASC']);
        $this->vars['pageTitle'] = 'Invio telematico del ' . $invio->dataCreazione->format('d/m/Y H:i');
        $this->vars['briciole'] = ['Deleghe F24' => '/contribuente/index', 'Invii telematici' => '/invio-telematico/index', $this->vars['pageTitle'] => null];
        return $this->render->generateView('invioTelematico/show', $this->vars);
    }

    public function scarica(InvioTelematico $invio): Response
    {
        $this->autorizzaInvio($invio);
        $this->vars['invio'] = $invio;
        return $this->render->generateData('invioTelematico/scarica', $this->vars);
    }

    /**
     * Annulla un invio (es. file scartato dai controlli dell'Agenzia): le
     * deleghe tornano confermate e possono essere corrette o reinviate.
     */
    public function delete(Request $request, InvioTelematico $invio): Response
    {
        $this->autorizzaInvio($invio);
        $this->verificaCsrf($request);
        $primaTransazione = $this->dataMapper->startTransaction();
        foreach ((new DelegaModel($this->dataMapper))->getByInvioTelematico($invio) as $delega) {
            if ($delega->stato === StatoDelega::trasmessa) {
                $delega->stato = StatoDelega::confermata;
            }
            $delega->invioTelematico = null;
            $this->dataMapper->save($delega);
        }
        $this->dataMapper->delete($invio);
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio('Invio annullato: le deleghe sono tornate confermate.');
        return $this->router->redirect('invio-telematico/index');
    }

    private function autorizzaInvio(InvioTelematico $invio): void
    {
        if ($invio->studio->id !== $this->studio->id) {
            throw new AccessDeniedException();
        }
    }
}
