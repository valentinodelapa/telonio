<?php

namespace Successioni\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Models\SoggettoModel;
use SismaFramework\Core\HttpClasses\Response;
use Successioni\Application\BaseClasses\SuccessioniController;
use Successioni\Application\Models\DichiarazioneSuccessioneModel;

/**
 * Ingresso del modulo: elenco dei defunti dello studio (persone fisiche con
 * la data di decesso nell'Anagrafica) e scheda del defunto con le sue
 * dichiarazioni di successione.
 */
class DefuntoController extends SuccessioniController
{

    private const RISULTATI_PER_PAGINA = 25;

    /**
     * Defunti dello studio, dal decesso più recente; con "tutti" si cercano
     * tutte le persone fisiche (per aprire la scheda e indicare il decesso
     * in Anagrafica).
     */
    public function index(string $testo = '', bool $tutti = false, int $pagina = 1): Response
    {
        $soggettoModel = new SoggettoModel($this->dataMapper);
        $totale = $tutti ? $soggettoModel->contaRisultati($this->studio, $testo, TipoSoggetto::personaFisica, null, null) : $soggettoModel->contaDeceduti($this->studio, $testo);
        $pagine = max(1, (int) ceil($totale / self::RISULTATI_PER_PAGINA));
        $pagina = min(max(1, $pagina), $pagine);
        $inizio = ($pagina - 1) * self::RISULTATI_PER_PAGINA;
        $soggetti = $tutti ? $soggettoModel->cerca($this->studio, $testo, TipoSoggetto::personaFisica, null, null, $inizio, self::RISULTATI_PER_PAGINA)
                : $soggettoModel->cercaDeceduti($this->studio, $testo, $inizio, self::RISULTATI_PER_PAGINA);
        $dichiarazioneModel = new DichiarazioneSuccessioneModel($this->dataMapper);
        $defunti = [];
        foreach ($soggetti as $soggetto) {
            $defunti[] = ['soggetto' => $soggetto, 'dichiarazioni' => iterator_to_array($dichiarazioneModel->getElenco($soggetto), false)];
        }
        $this->vars['defunti'] = $defunti;
        $this->vars['totale'] = $totale;
        $this->vars['pagina'] = $pagina;
        $this->vars['pagine'] = $pagine;
        $this->vars['testo'] = $testo;
        $this->vars['inclusiTutti'] = $tutti;
        $this->vars['briciole'] = ['Successioni' => null];
        return $this->render->generateView('defunto/index', $this->vars);
    }

    public function show(Soggetto $soggetto): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $this->vars['soggetto'] = $soggetto;
        $this->vars['dichiarazioni'] = iterator_to_array((new DichiarazioneSuccessioneModel($this->dataMapper))->getElenco($soggetto), false);
        $this->vars['pageTitle'] = $soggetto->nominativo;
        $this->vars['briciole'] = ['Successioni' => '/defunto/index', $soggetto->nominativo => null];
        return $this->render->generateView('defunto/show', $this->vars);
    }
}
