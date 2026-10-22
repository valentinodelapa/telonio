<?php

namespace Imu\Application\Controllers;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Imu\Application\BaseClasses\ImuController;
use Imu\Application\Entities\CaratteristicheImu;
use Imu\Application\Enumerations\EsenzioneImu;
use Imu\Application\Forms\CaratteristicheImuForm;
use Imu\Application\Models\CaratteristicheImuModel;
use Imu\Application\Models\ProspettoAliquoteModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Caratteristiche IMU dell'immobile (storicizzate, valide per tutti i
 * possessori). Si gestiscono dalla scheda del contribuente, a cui si
 * torna.
 */
class CaratteristicheImuController extends ImuController
{

    private const PROPRIETA_COPIATE = ['storicoArtistico', 'inagibile', 'ruraleStrumentale', 'beneMerce', 'areaFabbricabile', 'valoreVenale', 'valoreContabile', 'exComune', 'esenzione', 'note'];

    /**
     * Nuova versione: parte dalla più recente, con decorrenza al 1° gennaio
     * dell'anno (il valore delle aree è quello al 1° gennaio).
     */
    public function create(Request $request, Immobile $immobile, Soggetto $soggetto, int $anno = 0): Response
    {
        $this->autorizzaImmobile($immobile);
        $this->autorizzaSoggetto($soggetto);
        $anno = self::normalizzaAnno($anno);
        $caratteristiche = new CaratteristicheImu($this->dataMapper);
        $caratteristiche->immobile = $immobile;
        $storico = (new CaratteristicheImuModel($this->dataMapper))->getStorico($immobile);
        $precedente = $storico[0] ?? null;
        if ($precedente !== null) {
            foreach (self::PROPRIETA_COPIATE as $proprieta) {
                $caratteristiche->$proprieta = $precedente->$proprieta;
            }
        }
        $caratteristiche->validoDal = new SismaDate($anno . '-01-01');
        return $this->gestisci($request, $caratteristiche, $soggetto, $anno, $precedente === null ? 'Caratteristiche IMU' : 'Variazione delle caratteristiche IMU');
    }

    public function update(Request $request, CaratteristicheImu $caratteristiche, Soggetto $soggetto, int $anno = 0): Response
    {
        $this->autorizzaImmobile($caratteristiche->immobile);
        $this->autorizzaSoggetto($soggetto);
        return $this->gestisci($request, $caratteristiche, $soggetto, self::normalizzaAnno($anno), 'Rettifica delle caratteristiche IMU');
    }

    public function delete(Request $request, CaratteristicheImu $caratteristiche, Soggetto $soggetto, int $anno = 0): Response
    {
        $this->autorizzaImmobile($caratteristiche->immobile);
        $this->autorizzaSoggetto($soggetto);
        $this->annullaVersione($request, $caratteristiche, (new CaratteristicheImuModel($this->dataMapper))->getStorico($caratteristiche->immobile));
        $this->aggiungiMessaggio('Versione delle caratteristiche IMU eliminata: ricalcola l\'imposta per aggiornare i versamenti.');
        return $this->vaiAlContribuente($soggetto, self::normalizzaAnno($anno));
    }

    private function gestisci(Request $request, CaratteristicheImu $caratteristiche, Soggetto $soggetto, int $anno, string $titolo): Response
    {
        $form = new CaratteristicheImuForm($caratteristiche);
        $ambito = fn(CaratteristicheImu $versione) => (new CaratteristicheImuModel($this->dataMapper))->getStorico($versione->immobile);
        if ($this->registraVersione($request, $form, $caratteristiche, $ambito)) {
            $this->aggiungiMessaggio('Caratteristiche IMU salvate: ricalcola l\'imposta per aggiornare i versamenti.');
            return $this->vaiAlContribuente($soggetto, $anno);
        }
        $immobile = $caratteristiche->immobile;
        $this->impostaVariabiliForm($form, $caratteristiche);
        $this->vars['immobile'] = $immobile;
        $this->vars['terreno'] = $immobile->tipoCatasto === TipoCatasto::terreni;
        $this->vars['soggetto'] = $soggetto;
        $this->vars['anno'] = $anno;
        $this->vars['esenzioni'] = EsenzioneImu::cases();
        $this->vars['exComuni'] = array_values(array_unique(array_filter(array_map(fn($prospetto) => $prospetto->exComune,
                                (new ProspettoAliquoteModel($this->dataMapper))->getStoricoComune($this->studio, $immobile->comune->codiceCatastale)))));
        $this->vars['storico'] = (new CaratteristicheImuModel($this->dataMapper))->getStorico($immobile);
        $this->vars['nuovo'] = isset($caratteristiche->id) === false;
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBricioleContribuente($soggetto, $anno, $titolo);
        return $this->render->generateView('caratteristicheImu/form', $this->vars, $form->getResponseType());
    }
}
