<?php

namespace Dichiarativi\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\BaseClasses\DichiarativiController;
use Dichiarativi\Application\Entities\PerditaPregressa;
use Dichiarativi\Application\Enumerations\TipoPerdita;
use Dichiarativi\Application\Forms\PerditaPregressaForm;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Archivio delle perdite fiscali residue del contribuente alla fine di un
 * periodo d'imposta, per il riporto quando la dichiarazione dell'anno
 * prima non è nella suite.
 */
class PerditaPregressaController extends DichiarativiController
{

    public function create(Request $request, Soggetto $soggetto, int $anno = 0): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $perdita = new PerditaPregressa($this->dataMapper);
        $perdita->studio = $this->studio;
        $perdita->soggetto = $soggetto;
        $anno = self::normalizzaAnno($anno);
        // residui alla fine dell'anno prima della dichiarazione
        $perdita->anno = $anno - 1;
        return $this->gestisci($request, $perdita, $anno, 'Nuove perdite pregresse');
    }

    public function update(Request $request, PerditaPregressa $perdita, int $anno = 0): Response
    {
        $this->autorizzaSoggetto($perdita->soggetto);
        return $this->gestisci($request, $perdita, self::normalizzaAnno($anno), 'Modifica perdite pregresse');
    }

    public function delete(Request $request, PerditaPregressa $perdita, int $anno = 0): Response
    {
        $this->autorizzaSoggetto($perdita->soggetto);
        $this->verificaCsrf($request);
        $soggetto = $perdita->soggetto;
        $this->dataMapper->delete($perdita);
        $this->aggiungiMessaggio('Perdite pregresse eliminate.');
        return $this->vaiAlContribuente($soggetto, self::normalizzaAnno($anno));
    }

    private function gestisci(Request $request, PerditaPregressa $perdita, int $anno, string $titolo): Response
    {
        $form = new PerditaPregressaForm($perdita);
        if ($this->isInvioValido($request, $form)) {
            $perdita = $form->resolveEntity();
            $this->dataMapper->save($perdita);
            $this->aggiungiMessaggio('Perdite pregresse salvate: riproponi le dichiarazioni dell\'anno successivo per riportarle.');
            return $this->vaiAlContribuente($perdita->soggetto, $anno);
        }
        $this->vars['formEntity'] = $form->isSubmitted() ? $form->getEntityDataToStandardEntity() : $perdita;
        $this->vars['errors'] = $form->getFilterErrors();
        $this->vars['soggetto'] = $perdita->soggetto;
        $this->vars['anno'] = $anno;
        $this->vars['tipiPerdita'] = TipoPerdita::cases();
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBricioleContribuente($perdita->soggetto, $anno, $titolo);
        return $this->render->generateView('perditaPregressa/form', $this->vars, $form->getResponseType());
    }
}
