<?php

namespace GestioneStudio\Application\Controllers;

use Anagrafica\Application\Models\DatiFiscaliModel;
use Anagrafica\Application\Models\SoggettoModel;
use Anagrafica\Application\Services\StoricoService;
use Telonio\Application\Models\UtenteModel;
use GestioneStudio\Application\BaseClasses\GestioneStudioController;
use GestioneStudio\Application\Entities\Adempimento;
use GestioneStudio\Application\Entities\AssegnazioneAdempimento;
use GestioneStudio\Application\Enumerations\AmbitoAdempimento;
use GestioneStudio\Application\Enumerations\CategoriaAdempimento;
use GestioneStudio\Application\Forms\AdempimentoForm;
use GestioneStudio\Application\Models\AdempimentoModel;
use GestioneStudio\Application\Models\AssegnazioneAdempimentoModel;
use GestioneStudio\Application\Models\ProrogaAdempimentoModel;
use GestioneStudio\Application\Services\CalendarioAdempimenti;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Calendario degli adempimenti: quelli del calendario fiscale comune (in
 * sola lettura, con le proroghe dello studio) e quelli propri dello studio,
 * con l'assegnazione ai clienti.
 */
class AdempimentoController extends GestioneStudioController
{

    private const LIMITE_CLIENTI_ASSEGNAZIONE = 1000;

    public function index(int $anno = 0, bool $inattivi = false): Response
    {
        $anno = self::normalizzaAnno($anno);
        $calendario = new CalendarioAdempimenti($this->studio, $this->dataMapper);
        $assegnazioneModel = new AssegnazioneAdempimentoModel($this->dataMapper);
        $oggi = new SismaDate();
        $adempimenti = [];
        foreach ((new AdempimentoModel($this->dataMapper))->getElenco($this->studio, $inattivi === false) as $adempimento) {
            $adempimenti[] = [
                'adempimento' => $adempimento,
                'termini' => $calendario->getTermini($adempimento, new SismaDate($anno . '-01-01'), new SismaDate($anno . '-12-31')),
                'clienti' => count($assegnazioneModel->getAssegnatiDal($this->studio, $adempimento, $oggi)),
            ];
        }
        $this->vars['adempimenti'] = $adempimenti;
        $this->vars['anno'] = $anno;
        $this->vars['anni'] = self::getAnni();
        $this->vars['inattivi'] = $inattivi;
        $this->vars['sezione'] = 'adempimenti';
        $this->impostaBriciole(['Calendario degli adempimenti ' . $anno => null]);
        return $this->render->generateView('adempimento/index', $this->vars);
    }

    /**
     * Termini dell'adempimento per l'anno di riferimento, proroghe e
     * clienti a cui è assegnato.
     */
    public function show(Adempimento $adempimento, int $anno = 0): Response
    {
        $this->autorizzaAdempimento($adempimento, false);
        $anno = self::normalizzaAnno($anno);
        $calendario = new CalendarioAdempimenti($this->studio, $this->dataMapper);
        $prorogaModel = new ProrogaAdempimentoModel($this->dataMapper);
        $termini = [];
        foreach ($calendario->getTerminiAnno($adempimento, $anno) as $effettivo) {
            $termini[] = ['effettivo' => $effettivo, 'proroghe' => $prorogaModel->getPerTermine($effettivo->termine, $this->studio)];
        }
        $this->vars['adempimento'] = $adempimento;
        $this->vars['anno'] = $anno;
        $this->vars['anni'] = self::getAnni();
        $this->vars['termini'] = $termini;
        $this->vars['assegnati'] = (new AssegnazioneAdempimentoModel($this->dataMapper))->getAssegnatiDal($this->studio, $adempimento, new SismaDate());
        $this->vars['modificabile'] = $adempimento->studio !== null;
        $this->vars['pageTitle'] = $adempimento->denominazione;
        $this->vars['sezione'] = 'adempimenti';
        $this->impostaBriciole(['Calendario degli adempimenti' => '/adempimento/index/anno/' . $anno, $adempimento->denominazione => null]);
        return $this->render->generateView('adempimento/show', $this->vars);
    }

    public function create(Request $request): Response
    {
        $adempimento = new Adempimento($this->dataMapper);
        $adempimento->studio = $this->studio;
        return $this->gestisci($request, $adempimento, 'Nuovo adempimento dello studio');
    }

    public function update(Request $request, Adempimento $adempimento): Response
    {
        $this->autorizzaAdempimento($adempimento, true);
        return $this->gestisci($request, $adempimento, 'Modifica di ' . $adempimento->denominazione);
    }

    /**
     * Elimina l'adempimento dello studio non ancora assegnato; altrimenti
     * lo si disattiva.
     */
    public function delete(Request $request, Adempimento $adempimento): Response
    {
        $this->autorizzaAdempimento($adempimento, true);
        $this->verificaCsrf($request);
        if ((new AssegnazioneAdempimentoModel($this->dataMapper))->countByAdempimento($adempimento) > 0) {
            $this->aggiungiMessaggio('L\'adempimento è assegnato ad almeno un cliente: disattivalo invece di eliminarlo.', 'warning');
            return $this->router->redirect('adempimento/show/adempimento/' . $adempimento->id);
        }
        $this->dataMapper->delete($adempimento);
        $this->aggiungiMessaggio('Adempimento eliminato.');
        return $this->router->redirect('adempimento/index');
    }

    /**
     * Assegna l'adempimento a più clienti: proposti quelli dell'ambito con i
     * dati fiscali in vigore alla decorrenza, esclusi quelli a cui è già assegnato dalla decorrenza in poi.
     */
    public function assegna(Request $request, Adempimento $adempimento): Response
    {
        $this->autorizzaAdempimento($adempimento, false);
        $decorrenza = new SismaDate(date('Y') . '-01-01');
        $responsabile = null;
        $errore = null;
        if (isset($request->input['submitted'])) {
            $this->verificaCsrf($request);
            $data = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($request->input['validoDal'] ?? ''));
            $responsabileId = (int) ($request->input['responsabile'] ?? 0);
            $responsabile = $responsabileId > 0 ? (new UtenteModel($this->dataMapper))->getEntityById($responsabileId) : null;
            $scelti = array_map('intval', array_keys(array_filter((array) ($request->input['soggetti'] ?? []))));
            if ($data === false) {
                $errore = 'Indica la data da cui l\'adempimento è dovuto.';
            } elseif (($responsabileId > 0) && (($responsabile === null) || ($this->isOperatoreDelloStudio($responsabile) === false))) {
                $errore = 'Il responsabile deve essere un operatore dello studio.';
            } elseif (count($scelti) === 0) {
                $errore = 'Seleziona almeno un cliente.';
            } else {
                $decorrenza = new SismaDate($data->format('Y-m-d'));
                $esito = $this->registraAssegnazioni($adempimento, $scelti, $decorrenza, $responsabile);
                $this->aggiungiMessaggio(sprintf('Adempimento assegnato a %d clienti.', $esito['assegnati']));
                foreach ($esito['errori'] as $messaggio) {
                    $this->aggiungiMessaggio($messaggio, 'warning');
                }
                return $this->router->redirect('adempimento/show/adempimento/' . $adempimento->id);
            }
        }
        $gia = (new AssegnazioneAdempimentoModel($this->dataMapper))->getAssegnatiDal($this->studio, $adempimento, $decorrenza);
        $candidati = [];
        $datiFiscaliModel = new DatiFiscaliModel($this->dataMapper);
        foreach ((new SoggettoModel($this->dataMapper))->cerca($this->studio, null, null, null, true, 0, self::LIMITE_CLIENTI_ASSEGNAZIONE) as $soggetto) {
            if (isset($gia[$soggetto->id]) === false) {
                $candidati[] = ['soggetto' => $soggetto, 'proposto' => $adempimento->ambito->isProponibile($soggetto, $datiFiscaliModel->getVigenteAl($soggetto, $decorrenza))];
            }
        }
        usort($candidati, fn(array $a, array $b) => [$b['proposto'], $a['soggetto']->nominativo] <=> [$a['proposto'], $b['soggetto']->nominativo]);
        $this->vars['adempimento'] = $adempimento;
        $this->vars['candidati'] = $candidati;
        $this->vars['numeroGiaAssegnati'] = count($gia);
        $this->vars['urlAnnulla'] = '/adempimento/show/adempimento/' . $adempimento->id;
        $this->vars['decorrenza'] = $decorrenza;
        $this->vars['responsabileScelto'] = $responsabile?->id;
        $this->vars['operatori'] = $this->getOperatori();
        $this->vars['errore'] = $errore;
        $this->vars['pageTitle'] = 'Assegna ai clienti: ' . $adempimento->denominazione;
        $this->vars['sezione'] = 'adempimenti';
        $this->impostaBriciole(['Calendario degli adempimenti' => '/adempimento/index', $adempimento->denominazione => '/adempimento/show/adempimento/' . $adempimento->id, 'Assegna ai clienti' => null]);
        return $this->render->generateView('adempimento/assegna', $this->vars);
    }

    /**
     * @param int[] $soggettiId
     * @return array{assegnati: int, errori: string[]}
     */
    private function registraAssegnazioni(Adempimento $adempimento, array $soggettiId, SismaDate $decorrenza, $responsabile): array
    {
        $soggettoModel = new SoggettoModel($this->dataMapper);
        $assegnazioneModel = new AssegnazioneAdempimentoModel($this->dataMapper);
        $storico = new StoricoService($this->dataMapper);
        $esito = ['assegnati' => 0, 'errori' => []];
        foreach ($soggettiId as $id) {
            $soggetto = $soggettoModel->getEntityById($id);
            if (($soggetto === null) || ($soggetto->studio->id !== $this->studio->id)) {
                continue;
            }
            $assegnazione = new AssegnazioneAdempimento($this->dataMapper);
            $assegnazione->studio = $this->studio;
            $assegnazione->soggetto = $soggetto;
            $assegnazione->adempimento = $adempimento;
            $assegnazione->responsabile = $responsabile;
            $assegnazione->validoDal = $decorrenza;
            $ambito = $assegnazioneModel->getAmbito($assegnazione);
            $errore = $storico->verifica($assegnazione, $ambito);
            if ($errore !== null) {
                $esito['errori'][] = $soggetto->nominativo . ': ' . $errore;
                continue;
            }
            $storico->registra($assegnazione, $ambito);
            $esito['assegnati']++;
        }
        return $esito;
    }

    private function gestisci(Request $request, Adempimento $adempimento, string $titolo): Response
    {
        $form = new AdempimentoForm($adempimento);
        if ($this->isInvioValido($request, $form)) {
            $adempimento = $form->resolveEntity();
            $nuovo = isset($adempimento->id) === false;
            $this->dataMapper->save($adempimento);
            $this->aggiungiMessaggio($nuovo ? 'Adempimento creato: aggiungi i suoi termini.' : 'Adempimento salvato.');
            return $this->router->redirect('adempimento/show/adempimento/' . $adempimento->id);
        }
        $this->impostaVariabiliForm($form, $adempimento);
        $this->vars['adempimento'] = $adempimento;
        $this->vars['categorie'] = CategoriaAdempimento::cases();
        $this->vars['ambiti'] = AmbitoAdempimento::cases();
        $this->vars['urlAnnulla'] = isset($adempimento->id) ? '/adempimento/show/adempimento/' . $adempimento->id : '/adempimento/index';
        $this->vars['pageTitle'] = $titolo;
        $this->vars['sezione'] = 'adempimenti';
        $this->impostaBriciole(['Calendario degli adempimenti' => '/adempimento/index', $titolo => null]);
        return $this->render->generateView('adempimento/form', $this->vars, $form->getResponseType());
    }
}
