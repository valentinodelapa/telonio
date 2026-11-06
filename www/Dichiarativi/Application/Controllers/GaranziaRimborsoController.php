<?php

namespace Dichiarativi\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\BaseClasses\DichiarativiController;
use Dichiarativi\Application\Entities\GaranziaRimborso;
use Dichiarativi\Application\Forms\GaranziaRimborsoForm;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Archivio delle garanzie dei rimborsi IVA del contribuente, con la scadenza
 * nello scadenzario dello studio finché non sono svincolate.
 */
class GaranziaRimborsoController extends DichiarativiController
{

    public function create(Request $request, Soggetto $soggetto, int $anno = 0): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $anno = self::normalizzaAnno($anno);
        $garanzia = new GaranziaRimborso($this->dataMapper);
        $garanzia->studio = $this->studio;
        $garanzia->soggetto = $soggetto;
        $garanzia->annoImposta = $anno;
        $garanzia->dataRilascio = new SismaDate();
        return $this->gestisci($request, $garanzia, $anno, 'Nuova garanzia del rimborso');
    }

    public function update(Request $request, GaranziaRimborso $garanzia, int $anno = 0): Response
    {
        $this->autorizzaSoggetto($garanzia->soggetto);
        return $this->gestisci($request, $garanzia, self::normalizzaAnno($anno), 'Modifica garanzia del rimborso');
    }

    public function delete(Request $request, GaranziaRimborso $garanzia, int $anno = 0): Response
    {
        $this->autorizzaSoggetto($garanzia->soggetto);
        $this->verificaCsrf($request);
        $soggetto = $garanzia->soggetto;
        $this->dataMapper->delete($garanzia);
        $this->aggiungiMessaggio('Garanzia eliminata.');
        return $this->vaiAlContribuente($soggetto, self::normalizzaAnno($anno));
    }

    private function gestisci(Request $request, GaranziaRimborso $garanzia, int $anno, string $titolo): Response
    {
        $form = new GaranziaRimborsoForm($garanzia);
        if ($this->isInvioValido($request, $form)) {
            $garanzia = $form->resolveEntity();
            $garanzia->dataScadenza ??= $garanzia->getScadenzaProposta();
            $this->dataMapper->save($garanzia);
            $this->aggiungiMessaggio('Garanzia salvata.');
            return $this->vaiAlContribuente($garanzia->soggetto, $anno);
        }
        $this->vars['formEntity'] = $form->isSubmitted() ? $form->getEntityDataToStandardEntity() : $garanzia;
        $this->vars['errors'] = $form->getFilterErrors();
        $this->vars['soggetto'] = $garanzia->soggetto;
        $this->vars['anno'] = $anno;
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBricioleContribuente($garanzia->soggetto, $anno, $titolo);
        return $this->render->generateView('garanziaRimborso/form', $this->vars, $form->getResponseType());
    }
}
