<?php

namespace Imu\Application\Controllers;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Soggetto;
use Imu\Application\BaseClasses\ImuController;
use Imu\Application\Entities\CostoContabileImu;
use Imu\Application\Forms\CostoContabileImuForm;
use Imu\Application\Models\CostoContabileImuModel;
use Imu\Application\Services\ValoreImmobile;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Costi contabili dei fabbricati del gruppo D a valore contabile (comma
 * 746): elenco con il valore risultante per l'anno e inserimento.
 */
class CostoContabileImuController extends ImuController
{

    public function index(Request $request, Immobile $immobile, Soggetto $soggetto, int $anno = 0): Response
    {
        $this->autorizzaImmobile($immobile);
        $this->autorizzaSoggetto($soggetto);
        $anno = self::normalizzaAnno($anno);
        $costo = new CostoContabileImu($this->dataMapper);
        $costo->immobile = $immobile;
        $costo->anno = $anno - 1;
        $form = new CostoContabileImuForm($costo);
        if ($this->isInvioValido($request, $form)) {
            $this->dataMapper->save($form->resolveEntity());
            $this->aggiungiMessaggio('Costo registrato: ricalcola l\'imposta per aggiornare i versamenti.');
            return $this->router->redirect('costo-contabile-imu/index/immobile/' . $immobile->id . '/soggetto/' . $soggetto->id . '/anno/' . $anno);
        }
        $this->impostaVariabiliForm($form, $costo);
        $this->vars['immobile'] = $immobile;
        $this->vars['soggetto'] = $soggetto;
        $this->vars['anno'] = $anno;
        $this->vars['costi'] = iterator_to_array((new CostoContabileImuModel($this->dataMapper))->getElenco($immobile));
        $this->vars['valore'] = (new ValoreImmobile($this->dataMapper))->contabile($immobile, $anno, false);
        $this->vars['pageTitle'] = 'Costi contabili';
        $this->impostaBricioleContribuente($soggetto, $anno, $this->vars['pageTitle']);
        return $this->render->generateView('costoContabileImu/index', $this->vars, $form->getResponseType());
    }

    public function delete(Request $request, CostoContabileImu $costo, Soggetto $soggetto, int $anno = 0): Response
    {
        $this->autorizzaImmobile($costo->immobile);
        $this->autorizzaSoggetto($soggetto);
        $this->verificaCsrf($request);
        $immobile = $costo->immobile;
        $this->dataMapper->delete($costo);
        $this->aggiungiMessaggio('Costo eliminato.');
        return $this->router->redirect('costo-contabile-imu/index/immobile/' . $immobile->id . '/soggetto/' . $soggetto->id . '/anno/' . self::normalizzaAnno($anno));
    }
}
