<?php

namespace Deleghe\Application\Controllers;

use Deleghe\Application\BaseClasses\DelegheController;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Models\CodiceTributoModel;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Consultazione della tabella dei codici tributo e ricerca per
 * l'autocompletamento nelle righe della delega.
 */
class CodiceTributoController extends DelegheController
{

    private const RISULTATI_PER_PAGINA = 50;

    public function index(string $testo = '', string $sezione = '', bool $soppressi = false, int $pagina = 1): Response
    {
        $sezioneCodici = SezioneCodiceTributo::tryFrom($sezione);
        $codiceTributoModel = new CodiceTributoModel($this->dataMapper);
        $totale = $codiceTributoModel->contaRisultati($sezioneCodici, $soppressi, $testo);
        $pagine = max(1, (int) ceil($totale / self::RISULTATI_PER_PAGINA));
        $pagina = min(max(1, $pagina), $pagine);
        $this->vars['codici'] = $codiceTributoModel->consulta($sezioneCodici, $soppressi, $testo, ($pagina - 1) * self::RISULTATI_PER_PAGINA, self::RISULTATI_PER_PAGINA);
        $this->vars['totale'] = $totale;
        $this->vars['pagina'] = $pagina;
        $this->vars['pagine'] = $pagine;
        $this->vars['testo'] = $testo;
        $this->vars['sezioneFiltro'] = $sezioneCodici;
        $this->vars['soppressi'] = $soppressi;
        $this->vars['sezioni'] = SezioneCodiceTributo::cases();
        $this->vars['briciole'] = ['Deleghe F24' => '/contribuente/index', 'Codici tributo' => null];
        return $this->render->generateView('codiceTributo/index', $this->vars);
    }

    /**
     * Codici in uso della sezione per l'autocompletamento: [{id, etichetta}].
     */
    public function cerca(SezioneCodiceTributo $sezione, string $testo = ''): Response
    {
        $risultati = [];
        if (mb_strlen(trim($testo)) >= 2) {
            foreach ((new CodiceTributoModel($this->dataMapper))->cerca($sezione, trim($testo), 20) as $codice) {
                $risultati[] = ['id' => $codice->id, 'etichetta' => $codice->getEtichetta()];
            }
        }
        return $this->render->generateJson($risultati);
    }
}
