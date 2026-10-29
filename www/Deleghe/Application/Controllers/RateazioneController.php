<?php

namespace Deleghe\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Deleghe\Application\BaseClasses\DelegheController;
use Deleghe\Application\Entities\PianoRateazione;
use Deleghe\Application\Entities\RateazioneSecondoAcconto;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Models\VersamentoModel;
use Deleghe\Application\Services\CalcoloRateazione;
use Deleghe\Application\Services\EsitoControllo;
use Deleghe\Application\Services\RateazioneService;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Pagamento rateale del saldo e del primo acconto dei versamenti da versare
 * (CalcoloRateazione), e del secondo acconto differito da una norma
 * straordinaria: scelta dei versamenti e delle date, anteprima di quote e
 * interessi, registrazione delle rate nell'archivio.
 */
class RateazioneController extends DelegheController
{

    /**
     * Nuovo piano per i versamenti rateizzabili da versare alla data.
     */
    public function create(Request $request, Soggetto $soggetto, string $scadenza): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $data = self::leggiData($scadenza);
        $service = new RateazioneService($this->dataMapper);
        $rateizzabili = $data === null ? [] : array_values(array_filter((new VersamentoModel($this->dataMapper))->getDaVersare($soggetto, $data),
                                fn(Versamento $versamento) => $service->isRateizzabile($versamento)));
        if (count($rateizzabili) === 0) {
            $this->aggiungiMessaggio('Nessun versamento rateizzabile da versare a questa data (saldo e primo acconto: codici con rateazione NNRR o contributi INPS; secondo acconto solo con una norma straordinaria).', 'warning');
            return $this->vaiAlContribuente($soggetto);
        }
        // di default il secondo acconto differito, se c'è, che non si rateizza con gli altri versamenti
        $differiti = array_values(array_filter($rateizzabili, fn(Versamento $versamento) => $service->getNormaPiano([$versamento]) !== null));
        $scelti = $this->isPost($request) ? array_values(array_filter($rateizzabili, fn(Versamento $versamento) => in_array((string) $versamento->id, (array) ($request->input['versamenti'] ?? []), true)))
                : (count($differiti) > 0 ? $differiti : $rateizzabili);
        try {
            $norma = $service->getNormaPiano($scelti);
        } catch (\InvalidArgumentException $errore) {
            $norma = null;
            $this->vars['erroreRateazione'] = $errore->getMessage();
        }
        $date = $this->leggiDate($request, $data, $norma);
        $descrizione = $this->leggiDescrizione($request);
        if ($this->isPost($request) && (($request->input['azione'] ?? '') === 'salva')) {
            $this->verificaCsrf($request);
            try {
                $piano = $service->crea($soggetto, $scelti, $date, $descrizione);
                $this->aggiungiMessaggio($piano->numeroRate === 1 ? sprintf('Versamento differito al %s.', $date[0]->format('d/m/Y'))
                                : sprintf('Piano di rateazione creato: %d rate dal %s al %s.', $piano->numeroRate, $date[0]->format('d/m/Y'), end($date)->format('d/m/Y')));
                return $this->vaiAlContribuente($soggetto);
            } catch (\InvalidArgumentException $errore) {
                $this->vars['erroreRateazione'] = $errore->getMessage();
            }
        }
        $this->vars['piano'] = null;
        $this->vars['rateizzabili'] = $rateizzabili;
        $this->vars['idScelti'] = array_map(fn(Versamento $versamento) => $versamento->id, $scelti);
        $this->vars['urlForm'] = '/rateazione/create/soggetto/' . $soggetto->id . '/scadenza/' . $data->format('Y-m-d');
        return $this->vistaForm($soggetto, $data, $scelti, $date, $descrizione, 'Pagamento rateale', $norma);
    }

    public function update(Request $request, PianoRateazione $piano): Response
    {
        $this->autorizzaSoggetto($piano->soggetto);
        $service = new RateazioneService($this->dataMapper);
        $originali = $service->getOriginali($piano);
        $norma = $service->getNormaPiano($originali);
        $date = $this->isPost($request) ? $this->leggiDate($request, $piano->scadenza, $norma) : $piano->getDate();
        $descrizione = $this->isPost($request) ? $this->leggiDescrizione($request) : $piano->descrizione;
        if ($this->isPost($request) && (($request->input['azione'] ?? '') === 'salva')) {
            $this->verificaCsrf($request);
            try {
                $service->aggiorna($piano, $date, $descrizione);
                $this->aggiungiMessaggio('Piano di rateazione aggiornato: rate ricalcolate.');
                return $this->vaiAlContribuente($piano->soggetto);
            } catch (\InvalidArgumentException $errore) {
                $this->vars['erroreRateazione'] = $errore->getMessage();
            }
        }
        $this->vars['piano'] = $piano;
        $this->vars['motivoBlocco'] = $service->getMotivoBlocco($piano);
        $this->vars['rateizzabili'] = $originali;
        $this->vars['idScelti'] = array_map(fn(Versamento $versamento) => $versamento->id, $originali);
        $this->vars['urlForm'] = '/rateazione/update/piano/' . $piano->id;
        return $this->vistaForm($piano->soggetto, $piano->scadenza, $originali, $date, $descrizione, 'Piano di rateazione', $norma);
    }

    public function delete(Request $request, PianoRateazione $piano): Response
    {
        $this->autorizzaSoggetto($piano->soggetto);
        $this->verificaCsrf($request);
        try {
            (new RateazioneService($this->dataMapper))->elimina($piano);
            $this->aggiungiMessaggio('Piano di rateazione eliminato: i versamenti originari sono di nuovo da versare.');
        } catch (\InvalidArgumentException $errore) {
            $this->aggiungiMessaggio($errore->getMessage(), 'danger');
        }
        return $this->vaiAlContribuente($piano->soggetto);
    }

    /**
     * @param Versamento[] $scelti
     * @param SismaDate[] $date
     */
    private function vistaForm(Soggetto $soggetto, \DateTimeInterface $scadenza, array $scelti, array $date, ?string $descrizione, string $titolo, ?RateazioneSecondoAcconto $norma): Response
    {
        $anteprima = [];
        $erroriDate = CalcoloRateazione::verificaDate($scadenza, $date, $norma);
        if ((count($scelti) > 0) && (count($erroriDate) === 0) && (isset($this->vars['erroreRateazione']) === false)) {
            try {
                $anteprima = $this->getAnteprima((new CalcoloRateazione($this->dataMapper))->calcola($scelti, $date), $date);
            } catch (\InvalidArgumentException $errore) {
                $this->vars['erroreRateazione'] ??= $errore->getMessage();
            }
        }
        $this->vars['soggetto'] = $soggetto;
        $this->vars['scadenzaPiano'] = $scadenza;
        $this->vars['date'] = $date;
        $this->vars['descrizione'] = $descrizione;
        $this->vars['anteprima'] = $anteprima;
        $this->vars['erroriDate'] = $erroriDate;
        $this->vars['norma'] = $norma;
        $this->vars['minimoRate'] = CalcoloRateazione::getMinimoRate($norma);
        $this->vars['massimoRate'] = CalcoloRateazione::getMassimoRate(CalcoloRateazione::getPrimaRata($scadenza, $norma), $norma);
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBricioleContribuente($soggetto, $titolo);
        return $this->render->generateView('rateazione/form', $this->vars);
    }

    /**
     * Totali per rata di quote e interessi.
     *
     * @param Versamento[] $rate
     * @param SismaDate[] $date
     * @return array<int, array{data: SismaDate, versamento: string, percentuale: float, quote: int, interessi: int}>
     */
    private function getAnteprima(array $rate, array $date): array
    {
        $anteprima = $indici = [];
        foreach ($date as $indice => $data) {
            $versamento = CalcoloRateazione::getDataVersamento($data);
            $indici[$versamento->format('Y-m-d')] = $indice;
            $anteprima[$indice] = ['data' => $data, 'versamento' => $versamento, 'percentuale' => $indice === 0 ? 0.0 : CalcoloRateazione::getPercentualeInteressi($date[0], $date[1], $data),
                'quote' => 0, 'interessi' => 0];
        }
        foreach ($rate as $rata) {
            $anteprima[$indici[$rata->scadenza->format('Y-m-d')]][CalcoloRateazione::isInteresse($rata) ? 'interessi' : 'quote'] += EsitoControllo::centesimi($rata->importoDebito);
        }
        return $anteprima;
    }

    /**
     * Date delle rate dal form; se il numero di rate cambia si propongono
     * di nuovo le date.
     *
     * @return SismaDate[]
     */
    private function leggiDate(Request $request, \DateTimeInterface $scadenza, ?RateazioneSecondoAcconto $norma): array
    {
        $primaRata = CalcoloRateazione::getPrimaRata($scadenza, $norma);
        $minimo = CalcoloRateazione::getMinimoRate($norma);
        $massimo = CalcoloRateazione::getMassimoRate($primaRata, $norma);
        $numero = (int) ($request->input['numeroRate'] ?? $massimo);
        $numero = min(max($minimo, $numero), max($minimo, $massimo));
        $inviate = array_values(array_filter(array_map(fn($data) => is_string($data) ? self::leggiData($data) : null, (array) ($request->input['date'] ?? []))));
        if ((count($inviate) !== $numero) || ($inviate[0]->format('Y-m-d') !== $primaRata->format('Y-m-d'))) {
            // numero di rate o tipo di piano (versamenti scelti) cambiati: date proposte
            return CalcoloRateazione::getDateProposte($primaRata, $numero, $norma);
        }
        return $inviate;
    }

    private function leggiDescrizione(Request $request): ?string
    {
        $descrizione = trim((string) ($request->input['descrizione'] ?? ''));
        return $descrizione === '' ? null : mb_substr($descrizione, 0, 100);
    }

    private function isPost(Request $request): bool
    {
        return ($request->server['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }
}
