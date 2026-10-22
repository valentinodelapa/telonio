<?php

namespace Imu\Application\Controllers;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Imu\Application\BaseClasses\ImuController;
use Imu\Application\Entities\AgevolazioneImu;
use Imu\Application\Enumerations\AssimilazioneAbitazione;
use Imu\Application\Forms\AgevolazioneImuForm;
use Imu\Application\Models\AgevolazioneImuModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Agevolazioni IMU del contribuente su un immobile (storicizzate).
 */
class AgevolazioneImuController extends ImuController
{

    private const PROPRIETA_COPIATE = ['comodatoParenti', 'conduzioneAgricola', 'assimilazione', 'pensionatoEstero', 'note'];

    public function create(Request $request, Soggetto $soggetto, Immobile $immobile, int $anno = 0): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $this->autorizzaImmobile($immobile);
        $anno = self::normalizzaAnno($anno);
        $agevolazione = new AgevolazioneImu($this->dataMapper);
        $agevolazione->soggetto = $soggetto;
        $agevolazione->immobile = $immobile;
        $precedente = (new AgevolazioneImuModel($this->dataMapper))->getStorico($soggetto, $immobile)[0] ?? null;
        if ($precedente !== null) {
            foreach (self::PROPRIETA_COPIATE as $proprieta) {
                $agevolazione->$proprieta = $precedente->$proprieta;
            }
        }
        $agevolazione->validoDal = new SismaDate($anno . '-01-01');
        return $this->gestisci($request, $agevolazione, $anno, $precedente === null ? 'Agevolazioni IMU' : 'Variazione delle agevolazioni IMU');
    }

    public function update(Request $request, AgevolazioneImu $agevolazione, int $anno = 0): Response
    {
        $this->autorizzaSoggetto($agevolazione->soggetto);
        return $this->gestisci($request, $agevolazione, self::normalizzaAnno($anno), 'Rettifica delle agevolazioni IMU');
    }

    public function delete(Request $request, AgevolazioneImu $agevolazione, int $anno = 0): Response
    {
        $this->autorizzaSoggetto($agevolazione->soggetto);
        $soggetto = $agevolazione->soggetto;
        $this->annullaVersione($request, $agevolazione, (new AgevolazioneImuModel($this->dataMapper))->getStorico($soggetto, $agevolazione->immobile));
        $this->aggiungiMessaggio('Versione delle agevolazioni IMU eliminata: ricalcola l\'imposta per aggiornare i versamenti.');
        return $this->vaiAlContribuente($soggetto, self::normalizzaAnno($anno));
    }

    private function gestisci(Request $request, AgevolazioneImu $agevolazione, int $anno, string $titolo): Response
    {
        $form = new AgevolazioneImuForm($agevolazione);
        $ambito = fn(AgevolazioneImu $versione) => (new AgevolazioneImuModel($this->dataMapper))->getStorico($versione->soggetto, $versione->immobile);
        if ($this->registraVersione($request, $form, $agevolazione, $ambito)) {
            $this->aggiungiMessaggio('Agevolazioni IMU salvate: ricalcola l\'imposta per aggiornare i versamenti.');
            return $this->vaiAlContribuente($agevolazione->soggetto, $anno);
        }
        $this->impostaVariabiliForm($form, $agevolazione);
        $this->vars['agevolazione'] = $agevolazione;
        $this->vars['immobile'] = $agevolazione->immobile;
        $this->vars['terreno'] = $agevolazione->immobile->tipoCatasto === TipoCatasto::terreni;
        $this->vars['soggetto'] = $agevolazione->soggetto;
        $this->vars['anno'] = $anno;
        $this->vars['assimilazioni'] = AssimilazioneAbitazione::cases();
        $this->vars['storico'] = (new AgevolazioneImuModel($this->dataMapper))->getStorico($agevolazione->soggetto, $agevolazione->immobile);
        $this->vars['nuovo'] = isset($agevolazione->id) === false;
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBricioleContribuente($agevolazione->soggetto, $anno, $titolo);
        return $this->render->generateView('agevolazioneImu/form', $this->vars, $form->getResponseType());
    }
}
