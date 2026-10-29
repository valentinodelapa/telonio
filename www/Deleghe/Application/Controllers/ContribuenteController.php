<?php

namespace Deleghe\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Models\SoggettoModel;
use Deleghe\Application\BaseClasses\DelegheController;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Models\CreditoModel;
use Deleghe\Application\Models\DelegaModel;
use Deleghe\Application\Models\PianoRateazioneModel;
use Deleghe\Application\Models\RavvedimentoModel;
use Deleghe\Application\Models\VersamentoModel;
use Deleghe\Application\Services\RateazioneService;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Ingresso del modulo: si sceglie il contribuente e si lavora nella sua
 * scheda (versamenti da versare, deleghe, crediti), come nella gestione
 * deleghe per ditta.
 */
class ContribuenteController extends DelegheController
{

    private const RISULTATI_PER_PAGINA = 25;
    private const VISTE = ['versamenti', 'deleghe', 'crediti'];

    /**
     * Contribuenti dello studio (di default i soli clienti) con i
     * versamenti da versare e la prossima scadenza.
     */
    public function index(string $testo = '', bool $tutti = false, int $pagina = 1): Response
    {
        $soggettoModel = new SoggettoModel($this->dataMapper);
        $totale = $soggettoModel->contaRisultati($this->studio, $testo, null, null, $tutti === false);
        $pagine = max(1, (int) ceil($totale / self::RISULTATI_PER_PAGINA));
        $pagina = min(max(1, $pagina), $pagine);
        $versamentoModel = new VersamentoModel($this->dataMapper);
        $delegaModel = new DelegaModel($this->dataMapper);
        $contribuenti = [];
        foreach ($soggettoModel->cerca($this->studio, $testo, null, null, $tutti === false, ($pagina - 1) * self::RISULTATI_PER_PAGINA, self::RISULTATI_PER_PAGINA) as $soggetto) {
            $daVersare = $versamentoModel->getDaVersare($soggetto);
            $contribuenti[] = [
                'soggetto' => $soggetto,
                'daVersare' => count($daVersare),
                'prossimaScadenza' => count($daVersare) > 0 ? $daVersare[0]->scadenza : null,
                'bozze' => $delegaModel->contaRisultati($this->studio, $soggetto, StatoDelega::bozza, null, null),
                'confermate' => $delegaModel->contaRisultati($this->studio, $soggetto, StatoDelega::confermata, null, null),
            ];
        }
        $this->vars['contribuenti'] = $contribuenti;
        $this->vars['totale'] = $totale;
        $this->vars['pagina'] = $pagina;
        $this->vars['pagine'] = $pagine;
        $this->vars['testo'] = $testo;
        $this->vars['inclusiTutti'] = $tutti;
        $this->vars['oggi'] = (new SismaDate())->format('Y-m-d');
        $this->vars['briciole'] = ['Deleghe F24' => null];
        return $this->render->generateView('contribuente/index', $this->vars);
    }

    /**
     * Scheda del contribuente: versamenti da versare per data, deleghe
     * (filtrabili per stato e anno), crediti con il residuo.
     */
    public function show(Soggetto $soggetto, string $vista = 'versamenti', string $stato = '', int $anno = 0): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $vista = in_array($vista, self::VISTE, true) ? $vista : 'versamenti';
        $versamentoModel = new VersamentoModel($this->dataMapper);
        $creditoModel = new CreditoModel($this->dataMapper);
        $daVersare = $ravveduti = [];
        $ravvedimentoModel = new RavvedimentoModel($this->dataMapper);
        foreach ($versamentoModel->getDaVersare($soggetto) as $versamento) {
            $daVersare[$versamento->scadenza->format('Y-m-d')][] = $versamento;
            if ($versamento->scadenza->format('Y-m-d') < (new SismaDate())->format('Y-m-d')) {
                // scaduti: parte già regolarizzata con il ravvedimento frazionato
                $ravveduti[$versamento->id] = $ravvedimentoModel->getImportoRavveduto($versamento);
            }
        }
        $this->vars['ravveduti'] = $ravveduti;
        $crediti = [];
        foreach ($creditoModel->getElenco($soggetto) as $credito) {
            $crediti[] = ['credito' => $credito, 'residuo' => $creditoModel->getResiduo($credito), 'utilizzi' => $versamentoModel->getUtilizzi($credito)];
        }
        $statoDelega = StatoDelega::tryFrom($stato);
        $dal = $anno > 0 ? new SismaDate($anno . '-01-01') : null;
        $al = $anno > 0 ? new SismaDate($anno . '-12-31') : null;
        $delegaModel = new DelegaModel($this->dataMapper);
        $deleghe = [];
        foreach ($delegaModel->cerca($this->studio, $soggetto, $statoDelega, $dal, $al, 0, 500) as $delega) {
            $deleghe[] = ['delega' => $delega, 'totali' => $versamentoModel->getTotali($delega)];
        }
        $this->vars['soggetto'] = $soggetto;
        $this->vars['vista'] = $vista;
        $this->vars['daVersare'] = $daVersare;
        $this->vars['crediti'] = $crediti;
        $this->vars['piani'] = iterator_to_array((new PianoRateazioneModel($this->dataMapper))->getElenco($soggetto), false);
        $this->vars['dateRateizzabili'] = $this->getDateRateizzabili($daVersare);
        $this->vars['deleghe'] = $deleghe;
        $this->vars['statoFiltro'] = $statoDelega;
        $this->vars['annoFiltro'] = $anno;
        $this->vars['stati'] = StatoDelega::cases();
        $this->vars['sezioni'] = SezioneF24::cases();
        $this->vars['sezioniCredito'] = array_values(array_filter(SezioneF24::cases(), fn(SezioneF24 $sezione) => in_array($sezione, [SezioneF24::elide, SezioneF24::accise], true) === false));
        $this->vars['oggi'] = (new SismaDate())->format('Y-m-d');
        $this->vars['prossimaScadenza'] = self::prossimaScadenza();
        $this->vars['pageTitle'] = $soggetto->nominativo;
        $this->vars['briciole'] = ['Deleghe F24' => '/contribuente/index', $soggetto->nominativo => null];
        return $this->render->generateView('contribuente/show', $this->vars);
    }

    /**
     * Date dei versamenti da versare per cui proporre il piano di
     * rateazione: non ancora scadute con versamenti rateizzabili, o con un
     * secondo acconto differito da una norma straordinaria (rateizzabile
     * anche dopo la scadenza ordinaria, o per registrare i versamenti fatti).
     *
     * @param array<string, Versamento[]> $daVersare
     * @return array<string, true>
     */
    private function getDateRateizzabili(array $daVersare): array
    {
        $service = new RateazioneService($this->dataMapper);
        $oggi = (new SismaDate())->format('Y-m-d');
        $date = [];
        foreach ($daVersare as $data => $versamenti) {
            foreach ($versamenti as $versamento) {
                if ((($data >= $oggi) && $service->isRateizzabile($versamento)) || ($service->getNormaSecondoAcconto($versamento) !== null)) {
                    $date[$data] = true;
                    break;
                }
            }
        }
        return $date;
    }
}
