<?php

namespace Imu\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Models\SoggettoModel;
use Deleghe\Application\Models\VersamentoModel;
use Imu\Application\BaseClasses\ImuController;
use Imu\Application\Entities\CalcoloImu;
use Imu\Application\Models\AccontoVersatoImuModel;
use Imu\Application\Models\CalcoloImuModel;
use Imu\Application\Models\DichiarazioneImuModel;
use Imu\Application\Models\UtilizzoMistoEncModel;
use Imu\Application\Services\CalcoloImposta;
use Imu\Application\Models\RigoCalcoloImuModel;
use Imu\Application\Services\ElaborazioneImu;
use Imu\Application\Services\QuadroImmobili;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Ingresso del modulo: si sceglie il contribuente e si lavora nella sua
 * scheda dell'anno (immobili dell'Anagrafica con i dati IMU, prospetto di
 * calcolo, versamenti registrati per le deleghe F24).
 */
class ContribuenteImuController extends ImuController
{

    private const RISULTATI_PER_PAGINA = 25;

    /**
     * Contribuenti dello studio (di default i soli clienti) con il calcolo
     * dell'anno.
     */
    public function index(string $testo = '', bool $tutti = false, int $pagina = 1, int $anno = 0): Response
    {
        $anno = self::normalizzaAnno($anno);
        $soggettoModel = new SoggettoModel($this->dataMapper);
        $totale = $soggettoModel->contaRisultati($this->studio, $testo, null, null, $tutti === false);
        $pagine = max(1, (int) ceil($totale / self::RISULTATI_PER_PAGINA));
        $pagina = min(max(1, $pagina), $pagine);
        $calcoloModel = new CalcoloImuModel($this->dataMapper);
        $contribuenti = [];
        foreach ($soggettoModel->cerca($this->studio, $testo, null, null, $tutti === false, ($pagina - 1) * self::RISULTATI_PER_PAGINA, self::RISULTATI_PER_PAGINA) as $soggetto) {
            $contribuenti[] = ['soggetto' => $soggetto, 'calcolo' => $calcoloModel->getPerAnno($soggetto, $anno)];
        }
        $this->vars['contribuenti'] = $contribuenti;
        $this->vars['totale'] = $totale;
        $this->vars['pagina'] = $pagina;
        $this->vars['pagine'] = $pagine;
        $this->vars['testo'] = $testo;
        $this->vars['inclusiTutti'] = $tutti;
        $this->vars['anno'] = $anno;
        $this->vars['anni'] = self::getAnni();
        $this->vars['briciole'] = ['IMU' => null];
        return $this->render->generateView('contribuenteImu/index', $this->vars);
    }

    /**
     * Scheda del contribuente per l'anno.
     */
    public function show(Soggetto $soggetto, int $anno = 0): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $anno = self::normalizzaAnno($anno);
        $calcolo = (new CalcoloImuModel($this->dataMapper))->getPerAnno($soggetto, $anno);
        $this->vars['soggetto'] = $soggetto;
        $this->vars['anno'] = $anno;
        $this->vars['anni'] = self::getAnni();
        $this->vars['immobili'] = (new QuadroImmobili($this->dataMapper))->getImmobili($soggetto, $anno);
        $this->vars['calcolo'] = $calcolo;
        $this->vars['righi'] = $calcolo === null ? [] : iterator_to_array((new RigoCalcoloImuModel($this->dataMapper))->getRighi($calcolo));
        $this->vars['versamenti'] = iterator_to_array((new VersamentoModel($this->dataMapper))->getGenerati($soggetto, CalcoloImu::ORIGINE, CalcoloImu::getRiferimento($anno)));
        $this->vars['enteNonCommerciale'] = CalcoloImposta::isEnteNonCommerciale($soggetto, $anno, $this->dataMapper);
        $this->vars['utilizziEnc'] = (new UtilizzoMistoEncModel($this->dataMapper))->getPerImmobile($soggetto, $anno);
        $this->vars['accontiVersati'] = (new AccontoVersatoImuModel($this->dataMapper))->getPerAnno($soggetto, $anno);
        $this->vars['dichiarazioni'] = iterator_to_array((new DichiarazioneImuModel($this->dataMapper))->getElenco($soggetto, $anno));
        $this->vars['scadenzaAcconto'] = ElaborazioneImu::getScadenzaAcconto($anno);
        $this->vars['scadenzaSaldo'] = ElaborazioneImu::getScadenzaSaldo($anno);
        $this->vars['pageTitle'] = $soggetto->nominativo . ' · IMU ' . $anno;
        $this->impostaBricioleContribuente($soggetto, $anno);
        return $this->render->generateView('contribuenteImu/show', $this->vars);
    }
}
