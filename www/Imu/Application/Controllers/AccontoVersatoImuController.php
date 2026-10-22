<?php

namespace Imu\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Imu\Application\BaseClasses\ImuController;
use Imu\Application\Entities\AccontoVersatoImu;
use Imu\Application\Forms\AccontoVersatoImuForm;
use Imu\Application\Models\CalcoloImuModel;
use Imu\Application\Models\RigoCalcoloImuModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Acconti IMU versati senza la procedura (anche, per il 2020, la metà di
 * IMU e TASI 2019): il calcolo non registra l'acconto e determina il saldo
 * su questo importo.
 */
class AccontoVersatoImuController extends ImuController
{

    public function create(Request $request, Soggetto $soggetto, int $anno): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $acconto = new AccontoVersatoImu($this->dataMapper);
        $acconto->soggetto = $soggetto;
        $acconto->anno = self::normalizzaAnno($anno);
        return $this->gestisci($request, $acconto, 'Acconto versato');
    }

    public function update(Request $request, AccontoVersatoImu $acconto): Response
    {
        $this->autorizzaSoggetto($acconto->soggetto);
        return $this->gestisci($request, $acconto, 'Acconto versato');
    }

    public function delete(Request $request, AccontoVersatoImu $acconto): Response
    {
        $this->autorizzaSoggetto($acconto->soggetto);
        $this->verificaCsrf($request);
        $this->dataMapper->delete($acconto);
        $this->aggiungiMessaggio('Acconto eliminato: ricalcola l\'imposta per aggiornare i versamenti.');
        return $this->vaiAlContribuente($acconto->soggetto, $acconto->anno);
    }

    private function gestisci(Request $request, AccontoVersatoImu $acconto, string $titolo): Response
    {
        $form = new AccontoVersatoImuForm($acconto);
        if ($this->isInvioValido($request, $form)) {
            $acconto = $form->resolveEntity();
            $this->dataMapper->save($acconto);
            $this->aggiungiMessaggio('Acconto versato salvato: ricalcola l\'imposta per aggiornare il saldo.');
            return $this->vaiAlContribuente($acconto->soggetto, $acconto->anno);
        }
        $this->impostaVariabiliForm($form, $acconto);
        $comuni = [];
        $calcolo = (new CalcoloImuModel($this->dataMapper))->getPerAnno($acconto->soggetto, $acconto->anno);
        if ($calcolo !== null) {
            foreach ((new RigoCalcoloImuModel($this->dataMapper))->getRighi($calcolo) as $rigo) {
                $comuni[$rigo->codiceComune] = $rigo->codiceComune . ' - ' . $rigo->immobile->comune->denominazione;
            }
        }
        $this->vars['acconto'] = $acconto;
        $this->vars['comuni'] = $comuni;
        $this->vars['codiciTributo'] = array_combine(AccontoVersatoImuForm::getCodiciTributo(), AccontoVersatoImuForm::getCodiciTributo());
        $this->vars['soggetto'] = $acconto->soggetto;
        $this->vars['anno'] = $acconto->anno;
        $this->vars['pageTitle'] = $titolo . ' ' . $acconto->anno;
        $this->impostaBricioleContribuente($acconto->soggetto, $acconto->anno, $titolo);
        return $this->render->generateView('accontoVersatoImu/form', $this->vars, $form->getResponseType());
    }
}
