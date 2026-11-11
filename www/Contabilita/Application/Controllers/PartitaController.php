<?php

namespace Contabilita\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Models\ControparteModel;
use Contabilita\Application\Services\PartiteAperte;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Scadenzario dell'azienda: partite aperte (o tutte) per cliente e
 * fornitore con residuo, abbinamenti e quadratura con il saldo del
 * sottoconto; conferma degli abbinamenti creati dalla migrazione.
 */
class PartitaController extends ContabilitaController
{

    public function index(Soggetto $soggetto, int $controparte = 0, int $tutte = 0): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $filtro = $controparte > 0 ? (new ControparteModel($this->dataMapper))->getEntityById($controparte) : null;
        if (($filtro !== null) && ($filtro->soggetto->id !== $soggetto->id)) {
            $filtro = null;
        }
        $this->vars['scadenzario'] = (new PartiteAperte($this->dataMapper))->getScadenzario($soggetto, $tutte === 1, $filtro);
        $this->vars['filtroControparte'] = $filtro;
        $this->vars['tutte'] = $tutte === 1;
        $this->getEsercizio($soggetto, 0);
        $this->vars['sezioneAzienda'] = 'scadenzario';
        $this->vars['pageTitle'] = $soggetto->nominativo . ' · Scadenzario';
        $this->impostaBriciole($soggetto, null, 'Scadenzario');
        return $this->render->generateView('partita/index', $this->vars);
    }

    public function conferma(Request $request, Controparte $controparte): Response
    {
        $soggetto = $controparte->soggetto;
        $this->caricaAzienda($soggetto);
        $this->verificaCsrf($request);
        $confermati = (new PartiteAperte($this->dataMapper))->conferma($controparte);
        $this->aggiungiMessaggio($confermati . ' abbinamenti di ' . $controparte->denominazione . ' confermati.');
        return $this->router->redirect('partita/index/soggetto/' . $soggetto->id . '/controparte/' . $controparte->id);
    }
}
