<?php

namespace Imu\Application\Controllers;

use Anagrafica\Application\Models\PossessoModel;
use Imu\Application\BaseClasses\ImuController;
use Imu\Application\Entities\DichiarazioneImu;
use Imu\Application\Entities\RigoDichiarazioneImu;
use Imu\Application\Enumerations\CaratteristicaDichiarata;
use Imu\Application\Forms\RigoDichiarazioneImuForm;
use Imu\Application\Models\RigoDichiarazioneImuModel;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Quadri descrittivi degli immobili della dichiarazione in bozza:
 * inserimento manuale (vicende non ricavabili dai dati, es. usufrutto
 * legale, riunione di usufrutto), modifica ed eliminazione. La numerazione
 * (numero d'ordine, progressivo immobile) è ricalcolata a ogni modifica.
 */
class RigoDichiarazioneImuController extends ImuController
{

    public function create(Request $request, DichiarazioneImu $dichiarazione): Response
    {
        $this->autorizzaSoggetto($dichiarazione->soggetto);
        $rigo = new RigoDichiarazioneImu($this->dataMapper);
        $rigo->dichiarazioneImu = $dichiarazione;
        return $this->gestisci($request, $rigo, 'Nuovo immobile');
    }

    public function update(Request $request, RigoDichiarazioneImu $rigo): Response
    {
        $this->autorizzaSoggetto($rigo->dichiarazioneImu->soggetto);
        return $this->gestisci($request, $rigo, 'Immobile n. ' . $rigo->numeroOrdine);
    }

    public function delete(Request $request, RigoDichiarazioneImu $rigo): Response
    {
        $dichiarazione = $rigo->dichiarazioneImu;
        $this->autorizzaSoggetto($dichiarazione->soggetto);
        $this->verificaCsrf($request);
        if ($dichiarazione->isModificabile()) {
            $this->dataMapper->delete($rigo);
            $this->rinumera($dichiarazione);
            $this->aggiungiMessaggio('Immobile tolto dalla dichiarazione.');
        }
        return $this->router->redirect('dichiarazione-imu/show/dichiarazione/' . $dichiarazione->id);
    }

    private function gestisci(Request $request, RigoDichiarazioneImu $rigo, string $titolo): Response
    {
        $dichiarazione = $rigo->dichiarazioneImu;
        if ($dichiarazione->isModificabile() === false) {
            $this->aggiungiMessaggio('La dichiarazione è presentata: non è modificabile.', 'warning');
            return $this->router->redirect('dichiarazione-imu/show/dichiarazione/' . $dichiarazione->id);
        }
        $immobili = [];
        foreach ((new PossessoModel($this->dataMapper))->getStoricoSoggetto($dichiarazione->soggetto) as $possesso) {
            if ($possesso->immobile->comune->codiceCatastale === $dichiarazione->codiceComune) {
                $immobili[$possesso->immobile->id] = $possesso->immobile->getIdentificativo() . ' - ' . $possesso->immobile->getUbicazione();
            }
        }
        $form = new RigoDichiarazioneImuForm($rigo);
        if ($this->isInvioValido($request, $form)) {
            $rigo = $form->resolveEntity();
            if (isset($immobili[$rigo->immobile->id])) {
                if (isset($rigo->id) === false) {
                    $rigo->numeroOrdine = 9999;
                }
                $this->dataMapper->save($rigo);
                $this->rinumera($dichiarazione);
                $this->aggiungiMessaggio('Immobile salvato.');
                return $this->router->redirect('dichiarazione-imu/show/dichiarazione/' . $dichiarazione->id);
            }
            $form->getFilterErrors()->immobileCustomMessage = 'Immobile non posseduto nel comune della dichiarazione.';
        }
        $this->impostaVariabiliForm($form, $rigo);
        $this->vars['rigo'] = $rigo;
        $this->vars['dichiarazione'] = $dichiarazione;
        $this->vars['immobili'] = $immobili;
        $this->vars['caratteristiche'] = array_values(array_filter(CaratteristicaDichiarata::cases(), fn(CaratteristicaDichiarata $caratteristica) => ($dichiarazione->isEnc() === false) || $caratteristica->isAmmessaEnc()));
        $this->vars['riduzioni'] = $dichiarazione->isEnc() ? RigoDichiarazioneImu::RIDUZIONI_ENC : RigoDichiarazioneImu::RIDUZIONI;
        $this->vars['soggetto'] = $dichiarazione->soggetto;
        $this->vars['anno'] = $dichiarazione->anno;
        $this->vars['pageTitle'] = $titolo . ' · ' . $dichiarazione->denominazioneComune . ' ' . $dichiarazione->anno;
        $this->impostaBricioleContribuente($dichiarazione->soggetto, $dichiarazione->anno, $this->vars['pageTitle']);
        return $this->render->generateView('rigoDichiarazioneImu/form', $this->vars, $form->getResponseType());
    }

    /**
     * Numero d'ordine progressivo per quadro, progressivo immobile per
     * immobile nell'ordine di comparsa.
     */
    private function rinumera(DichiarazioneImu $dichiarazione): void
    {
        $numeri = $progressivi = $assegnati = [];
        foreach ((new RigoDichiarazioneImuModel($this->dataMapper))->getRighi($dichiarazione) as $rigo) {
            $numeri[$rigo->quadro] = ($numeri[$rigo->quadro] ?? 0) + 1;
            $chiave = $rigo->quadro . '|' . $rigo->immobile->id;
            if (isset($assegnati[$chiave]) === false) {
                $progressivi[$rigo->quadro] = ($progressivi[$rigo->quadro] ?? 0) + 1;
                $assegnati[$chiave] = $progressivi[$rigo->quadro];
            }
            $rigo->numeroOrdine = $numeri[$rigo->quadro];
            $rigo->progressivoImmobile = $assegnati[$chiave];
            $this->dataMapper->save($rigo);
        }
    }
}
