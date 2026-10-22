<?php

namespace Imu\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Deleghe\Application\Models\VersamentoModel;
use Imu\Application\BaseClasses\ImuController;
use Imu\Application\Entities\CalcoloImu;
use Imu\Application\Models\RigoCalcoloImuModel;
use Imu\Application\Services\ElaborazioneImu;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Elaborazione del calcolo IMU del contribuente: calcolo, registrazione
 * dei versamenti nell'archivio di Deleghe, eliminazione e stampa del
 * prospetto.
 */
class CalcoloImuController extends ImuController
{

    public function elabora(Request $request, Soggetto $soggetto, int $anno): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $this->verificaCsrf($request);
        $anno = self::normalizzaAnno($anno);
        try {
            $calcolo = (new ElaborazioneImu($this->dataMapper))->elabora($soggetto, $anno, ($request->input['unicaSoluzione'] ?? '') === '1');
            $this->aggiungiMessaggio(sprintf('IMU %d calcolata: imposta € %s, acconto € %s, saldo € %s. I versamenti sono nell\'archivio Deleghe F24.', $anno,
                            number_format($calcolo->impostaAnnua, 2, ',', '.'), number_format($calcolo->acconto, 2, ',', '.'), number_format($calcolo->saldo, 2, ',', '.')));
            foreach ($calcolo->getDifferenze() as $differenza) {
                $this->aggiungiMessaggio($differenza, 'warning');
            }
        } catch (\InvalidArgumentException $errore) {
            $this->aggiungiMessaggio('Versamenti non registrati: ' . $errore->getMessage(), 'danger');
        }
        return $this->vaiAlContribuente($soggetto, $anno);
    }

    public function delete(Request $request, CalcoloImu $calcolo): Response
    {
        $this->autorizzaSoggetto($calcolo->soggetto);
        $this->verificaCsrf($request);
        $soggetto = $calcolo->soggetto;
        $anno = $calcolo->anno;
        $differenze = (new ElaborazioneImu($this->dataMapper))->elimina($calcolo);
        $this->aggiungiMessaggio('Calcolo eliminato con i versamenti ancora da versare o in bozza.');
        foreach ($differenze as $differenza) {
            $this->aggiungiMessaggio($differenza, 'warning');
        }
        return $this->vaiAlContribuente($soggetto, $anno);
    }

    /**
     * Prospetto di calcolo da consegnare al contribuente.
     */
    public function stampa(CalcoloImu $calcolo): Response
    {
        $this->autorizzaSoggetto($calcolo->soggetto);
        $this->vars['calcolo'] = $calcolo;
        $this->vars['righi'] = iterator_to_array((new RigoCalcoloImuModel($this->dataMapper))->getRighi($calcolo));
        $this->vars['versamenti'] = iterator_to_array((new VersamentoModel($this->dataMapper))->getGenerati($calcolo->soggetto, CalcoloImu::ORIGINE, CalcoloImu::getRiferimento($calcolo->anno)));
        $this->vars['pageTitle'] = 'IMU ' . $calcolo->anno . ' · ' . $calcolo->soggetto->nominativo;
        return $this->render->generateView('calcoloImu/stampa', $this->vars);
    }
}
