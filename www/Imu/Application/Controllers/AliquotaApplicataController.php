<?php

namespace Imu\Application\Controllers;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Soggetto;
use Imu\Application\BaseClasses\ImuController;
use Imu\Application\Entities\AliquotaApplicata;
use Imu\Application\Forms\AliquotaApplicataForm;
use Imu\Application\Models\AliquotaApplicataModel;
use Imu\Application\Models\AliquotaDifferenziataModel;
use Imu\Application\Services\AliquoteComune;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Scelta dell'aliquota dell'immobile del contribuente per l'anno, tra le
 * differenziate del prospetto del comune o indicata dallo studio.
 */
class AliquotaApplicataController extends ImuController
{

    public function create(Request $request, Soggetto $soggetto, Immobile $immobile, int $anno): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $this->autorizzaImmobile($immobile);
        $anno = self::normalizzaAnno($anno);
        $esistente = (new AliquotaApplicataModel($this->dataMapper))->getPerAnno($soggetto, $immobile, $anno);
        if ($esistente !== null) {
            return $this->router->redirect('aliquota-applicata/update/aliquota-applicata/' . $esistente->id);
        }
        $aliquotaApplicata = new AliquotaApplicata($this->dataMapper);
        $aliquotaApplicata->soggetto = $soggetto;
        $aliquotaApplicata->immobile = $immobile;
        $aliquotaApplicata->anno = $anno;
        return $this->gestisci($request, $aliquotaApplicata);
    }

    public function update(Request $request, AliquotaApplicata $aliquotaApplicata): Response
    {
        $this->autorizzaSoggetto($aliquotaApplicata->soggetto);
        return $this->gestisci($request, $aliquotaApplicata);
    }

    public function delete(Request $request, AliquotaApplicata $aliquotaApplicata): Response
    {
        $this->autorizzaSoggetto($aliquotaApplicata->soggetto);
        $this->verificaCsrf($request);
        $this->dataMapper->delete($aliquotaApplicata);
        $this->aggiungiMessaggio('Si applica l\'aliquota della fattispecie di base: ricalcola l\'imposta per aggiornare i versamenti.');
        return $this->vaiAlContribuente($aliquotaApplicata->soggetto, $aliquotaApplicata->anno);
    }

    private function gestisci(Request $request, AliquotaApplicata $aliquotaApplicata): Response
    {
        $immobile = $aliquotaApplicata->immobile;
        $anno = $aliquotaApplicata->anno;
        $prospetto = (new AliquoteComune($this->studio, $this->dataMapper))->getProspetto($immobile->comune->codiceCatastale, $anno);
        $differenziate = $prospetto['prospetto'] === null ? [] : iterator_to_array((new AliquotaDifferenziataModel($this->dataMapper))->getElenco($prospetto['prospetto']));
        $form = new AliquotaApplicataForm($aliquotaApplicata);
        if ($this->isInvioValido($request, $form)) {
            $aliquotaApplicata = $form->resolveEntity();
            $scelta = $aliquotaApplicata->aliquotaDifferenziata;
            if (($scelta === null) || in_array($scelta->id, array_map(fn($differenziata) => $differenziata->id, $differenziate), true)) {
                $this->dataMapper->save($aliquotaApplicata);
                $this->aggiungiMessaggio('Aliquota dell\'immobile salvata: ricalcola l\'imposta per aggiornare i versamenti.');
                return $this->vaiAlContribuente($aliquotaApplicata->soggetto, $anno);
            }
            $form->getFilterErrors()->aliquotaDifferenziataCustomMessage = 'L\'aliquota scelta non è del prospetto del comune per il ' . $anno . '.';
        }
        $this->impostaVariabiliForm($form, $aliquotaApplicata);
        $this->vars['aliquotaApplicata'] = $aliquotaApplicata;
        $this->vars['immobile'] = $immobile;
        $this->vars['soggetto'] = $aliquotaApplicata->soggetto;
        $this->vars['anno'] = $anno;
        $this->vars['prospetto'] = $prospetto;
        $this->vars['differenziate'] = $differenziate;
        $this->vars['pageTitle'] = 'Aliquota ' . $anno . ' dell\'immobile';
        $this->impostaBricioleContribuente($aliquotaApplicata->soggetto, $anno, $this->vars['pageTitle']);
        return $this->render->generateView('aliquotaApplicata/form', $this->vars, $form->getResponseType());
    }
}
