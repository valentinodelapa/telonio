<?php

namespace Dichiarativi\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\BaseClasses\DichiarativiController;
use Dichiarativi\Application\Entities\CessioneFinanziaria;
use Dichiarativi\Application\Forms\CessioneFinanziariaForm;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Archivio delle cessioni di titoli, partecipazioni non qualificate e
 * cripto-attività del contribuente in regime dichiarativo (quadro RT).
 */
class CessioneFinanziariaController extends DichiarativiController
{

    public function create(Request $request, Soggetto $soggetto, int $anno = 0): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $cessione = new CessioneFinanziaria($this->dataMapper);
        $cessione->studio = $this->studio;
        $cessione->soggetto = $soggetto;
        $cessione->annoImposta = self::normalizzaAnno($anno);
        $cessione->dataCessione = new SismaDate($cessione->annoImposta . '-12-31');
        return $this->gestisci($request, $cessione, 'Nuova cessione');
    }

    public function update(Request $request, CessioneFinanziaria $cessioneFinanziaria): Response
    {
        $this->autorizzaSoggetto($cessioneFinanziaria->soggetto);
        return $this->gestisci($request, $cessioneFinanziaria, 'Modifica cessione');
    }

    public function delete(Request $request, CessioneFinanziaria $cessioneFinanziaria): Response
    {
        $this->autorizzaSoggetto($cessioneFinanziaria->soggetto);
        $this->verificaCsrf($request);
        $this->dataMapper->delete($cessioneFinanziaria);
        $this->aggiungiMessaggio('Cessione eliminata.');
        return $this->vaiAlContribuente($cessioneFinanziaria->soggetto, $cessioneFinanziaria->annoImposta);
    }

    private function gestisci(Request $request, CessioneFinanziaria $cessione, string $titolo): Response
    {
        $form = new CessioneFinanziariaForm($cessione);
        if ($this->isInvioValido($request, $form)) {
            $cessione = $form->resolveEntity();
            $this->dataMapper->save($cessione);
            $this->aggiungiMessaggio('Cessione salvata.');
            return $this->vaiAlContribuente($cessione->soggetto, $cessione->annoImposta);
        }
        $this->vars['formEntity'] = $form->isSubmitted() ? $form->getEntityDataToStandardEntity() : $cessione;
        $this->vars['errors'] = $form->getFilterErrors();
        $this->vars['tipiCessione'] = CessioneFinanziaria::TIPI;
        $this->vars['soggetto'] = $cessione->soggetto;
        $this->vars['anno'] = $cessione->annoImposta;
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBricioleContribuente($cessione->soggetto, $cessione->annoImposta, $titolo);
        return $this->render->generateView('cessioneFinanziaria/form', $this->vars, $form->getResponseType());
    }
}
