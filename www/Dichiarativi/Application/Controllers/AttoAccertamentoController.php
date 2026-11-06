<?php

namespace Dichiarativi\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\BaseClasses\DichiarativiController;
use Dichiarativi\Application\Entities\AttoAccertamento;
use Dichiarativi\Application\Forms\AttoAccertamentoForm;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Archivio degli atti di accertamento del contribuente, per le situazioni
 * di rischio dei rimborsi IVA oltre 30.000 euro.
 */
class AttoAccertamentoController extends DichiarativiController
{

    public function create(Request $request, Soggetto $soggetto, int $anno = 0): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $atto = new AttoAccertamento($this->dataMapper);
        $atto->studio = $this->studio;
        $atto->soggetto = $soggetto;
        return $this->gestisci($request, $atto, self::normalizzaAnno($anno), 'Nuovo atto di accertamento');
    }

    public function update(Request $request, AttoAccertamento $atto, int $anno = 0): Response
    {
        $this->autorizzaSoggetto($atto->soggetto);
        return $this->gestisci($request, $atto, self::normalizzaAnno($anno), 'Modifica atto di accertamento');
    }

    public function delete(Request $request, AttoAccertamento $atto, int $anno = 0): Response
    {
        $this->autorizzaSoggetto($atto->soggetto);
        $this->verificaCsrf($request);
        $soggetto = $atto->soggetto;
        $this->dataMapper->delete($atto);
        $this->aggiungiMessaggio('Atto di accertamento eliminato.');
        return $this->vaiAlContribuente($soggetto, self::normalizzaAnno($anno));
    }

    private function gestisci(Request $request, AttoAccertamento $atto, int $anno, string $titolo): Response
    {
        $form = new AttoAccertamentoForm($atto);
        if ($this->isInvioValido($request, $form)) {
            $atto = $form->resolveEntity();
            $this->dataMapper->save($atto);
            $this->aggiungiMessaggio('Atto di accertamento salvato.');
            return $this->vaiAlContribuente($atto->soggetto, $anno);
        }
        $this->vars['formEntity'] = $form->isSubmitted() ? $form->getEntityDataToStandardEntity() : $atto;
        $this->vars['errors'] = $form->getFilterErrors();
        $this->vars['soggetto'] = $atto->soggetto;
        $this->vars['anno'] = $anno;
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBricioleContribuente($atto->soggetto, $anno, $titolo);
        return $this->render->generateView('attoAccertamento/form', $this->vars, $form->getResponseType());
    }
}
