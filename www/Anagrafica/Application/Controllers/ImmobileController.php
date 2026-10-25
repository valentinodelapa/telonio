<?php

namespace Anagrafica\Application\Controllers;

use Anagrafica\Application\BaseClasses\AnagraficaController;
use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\CausaleClassamento;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Anagrafica\Application\Forms\DatiCatastaliForm;
use Anagrafica\Application\Forms\ImmobileForm;
use Anagrafica\Application\Forms\PossessoForm;
use Anagrafica\Application\Models\DatiCatastaliModel;
use Anagrafica\Application\Models\IdentificativoGraffatoModel;
use Anagrafica\Application\Models\ImmobileModel;
use Anagrafica\Application\Models\PossessoModel;
use Anagrafica\Application\Services\ControlloQuote;
use Anagrafica\Application\Services\SincronizzazioneSoggetto;
use Anagrafica\Application\Services\StoricoService;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\ResponseType;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Archivio immobili dello studio: fabbricati e terreni, con dati catastali
 * e possessori storicizzati.
 */
class ImmobileController extends AnagraficaController
{

    private const RISULTATI_PER_PAGINA = 25;

    public function index(string $testo = '', string $tipo = '', int $pagina = 1): Response
    {
        $tipoCatasto = TipoCatasto::tryFrom($tipo);
        $immobileModel = new ImmobileModel($this->dataMapper);
        $totale = $immobileModel->contaRisultati($this->studio, $testo, $tipoCatasto);
        $pagine = max(1, (int) ceil($totale / self::RISULTATI_PER_PAGINA));
        $pagina = min(max(1, $pagina), $pagine);
        $immobili = $immobileModel->cerca($this->studio, $testo, $tipoCatasto, ($pagina - 1) * self::RISULTATI_PER_PAGINA, self::RISULTATI_PER_PAGINA);
        $oggi = new SismaDate();
        $datiCatastaliModel = new DatiCatastaliModel($this->dataMapper);
        $possessoModel = new PossessoModel($this->dataMapper);
        $righe = [];
        foreach ($immobili as $immobile) {
            $righe[] = [
                'immobile' => $immobile,
                'datiCatastali' => $datiCatastaliModel->getVigenteAl($immobile, $oggi),
                'possessi' => $possessoModel->getVigentiAl($immobile, $oggi),
            ];
        }
        $this->vars['righe'] = $righe;
        $this->vars['totale'] = $totale;
        $this->vars['pagina'] = $pagina;
        $this->vars['pagine'] = $pagine;
        $this->vars['testo'] = $testo;
        $this->vars['tipoCatasto'] = $tipoCatasto;
        $this->vars['tipiCatasto'] = TipoCatasto::cases();
        $this->vars['briciole'] = ['Anagrafica' => '/soggetto/index', 'Immobili' => null];
        return $this->render->generateView('immobile/index', $this->vars);
    }

    /**
     * Inserimento di un immobile con i primi dati catastali e, se richiesto
     * da un soggetto, il suo possesso. Se l'immobile è già nell'archivio
     * dello studio (es. inserito per il comproprietario) viene riusato e si
     * registra solo il possesso.
     */
    public function create(Request $request, TipoCatasto $tipo = TipoCatasto::fabbricati, ?Soggetto $soggetto = null): Response
    {
        if ($soggetto !== null) {
            $this->autorizza($soggetto);
        }
        $immobile = new Immobile($this->dataMapper);
        $immobile->studio = $this->studio;
        $immobile->tipoCatasto = $tipo;
        $immobileForm = new ImmobileForm($immobile);
        $datiCatastali = new DatiCatastali($this->dataMapper);
        $datiCatastali->immobile = $immobile;
        $datiCatastali->causale = CausaleClassamento::costituzione;
        $datiCatastaliForm = new DatiCatastaliForm($datiCatastali);
        $forms = [$immobileForm, $datiCatastaliForm];
        $possesso = null;
        if ($soggetto !== null) {
            $immobileForm->consentiImmobileEsistente();
            $possesso = new Possesso($this->dataMapper);
            $possesso->soggetto = $soggetto;
            $possesso->immobile = $immobile;
            $possessoForm = (new PossessoForm($possesso))->conPrefisso('possesso');
            $datiCatastaliForm->conDecorrenzaDelPossesso('possesso');
            $forms[] = $possessoForm;
        }
        if ($this->isInvioValido($request, ...$forms)) {
            $immobile = $immobileForm->resolveEntity();
            $esistente = $soggetto === null ? null : (new ImmobileModel($this->dataMapper))->trovaPerChiaveCatastale($this->studio, $immobile);
            $risposta = $possesso === null ? $this->salvaNuovo($immobile, $datiCatastaliForm) : $this->salvaConPossesso($immobile, $esistente, $datiCatastaliForm, $possessoForm);
            if ($risposta !== null) {
                return $risposta;
            }
        }
        $this->impostaVariabiliForm($immobileForm, $immobile);
        $this->impostaVariabiliForm($datiCatastaliForm, $datiCatastali, 'datiCatastaliEntity', 'datiCatastaliErrors');
        if ($possesso !== null) {
            $this->impostaVariabiliForm($possessoForm, $possesso, 'possessoEntity', 'possessoErrors');
        }
        $this->vars['immobile'] = $immobile;
        $this->vars['soggetto'] = $soggetto;
        $this->vars['nuovo'] = true;
        $this->vars['causali'] = [CausaleClassamento::costituzione];
        // con il possesso la decorrenza dei dati catastali è facoltativa e parte vuota (= inizio del possesso)
        $this->vars['decorrenzaDalPossesso'] = $soggetto !== null;
        // anche dopo un invio non valido, se lasciata vuota (segue il possesso, che può essere corretto)
        $this->vars['decorrenzaVuota'] = ($soggetto !== null) && (trim((string) ($request->input['validoDal'] ?? '')) === '');
        $this->vars['pageTitle'] = 'Nuovo ' . mb_strtolower($tipo->getLabel());
        if ($soggetto !== null) {
            $this->impostaBriciole($soggetto, $this->vars['pageTitle']);
        } else {
            $this->vars['briciole'] = ['Anagrafica' => '/soggetto/index', 'Immobili' => '/immobile/index', $this->vars['pageTitle'] => null];
        }
        return $this->generaVistaForm(...$forms);
    }

    private function salvaNuovo(Immobile $immobile, DatiCatastaliForm $datiCatastaliForm): Response
    {
        $primaTransazione = $this->dataMapper->startTransaction();
        $this->dataMapper->save($immobile);
        $this->dataMapper->save($datiCatastaliForm->resolveEntity());
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio('Immobile inserito in archivio.');
        return $this->vaiAllImmobile($immobile);
    }

    private function salvaConPossesso(Immobile $immobile, ?Immobile $esistente, DatiCatastaliForm $datiCatastaliForm, PossessoForm $possessoForm): ?Response
    {
        $possesso = $possessoForm->resolveEntity();
        $possesso->immobile = $esistente ?? $immobile;
        $possessoModel = new PossessoModel($this->dataMapper);
        $storicoService = new StoricoService($this->dataMapper);
        $ambito = $esistente === null ? [] : $possessoModel->getAmbito($possesso);
        $errori = array_filter([
            'validoDal' => $storicoService->verifica($possesso, $ambito),
            'quota' => (new ControlloQuote($this->dataMapper))->verifica($possesso),
        ]);
        if (count($errori) > 0) {
            foreach ($errori as $campo => $errore) {
                $possessoForm->getFilterErrors()->{$campo . 'CustomMessage'} = $errore;
            }
            return null;
        }
        $primaTransazione = $this->dataMapper->startTransaction();
        if ($esistente === null) {
            $this->dataMapper->save($immobile);
            $this->dataMapper->save($datiCatastaliForm->resolveEntity());
        }
        $storicoService->registra($possesso, $ambito);
        (new SincronizzazioneSoggetto($this->dataMapper))->aggiorna($possesso->soggetto);
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio($esistente === null ? 'Immobile inserito e collegato al soggetto.' : 'Immobile già presente nell\'archivio dello studio: è stato collegato al soggetto senza modificarne i dati catastali.');
        return $this->vaiAllaScheda($possesso->soggetto, 'immobili');
    }

    public function update(Request $request, Immobile $immobile): Response
    {
        $this->autorizzaImmobile($immobile);
        $form = new ImmobileForm($immobile);
        if ($this->isInvioValido($request, $form)) {
            $this->dataMapper->save($form->resolveEntity());
            $this->aggiungiMessaggio('Dati dell\'immobile aggiornati.');
            return $this->vaiAllImmobile($immobile);
        }
        $this->impostaVariabiliForm($form, $immobile);
        $this->vars['immobile'] = $immobile;
        $this->vars['soggetto'] = null;
        $this->vars['nuovo'] = false;
        $this->vars['pageTitle'] = 'Modifica immobile';
        $this->vars['briciole'] = ['Anagrafica' => '/soggetto/index', 'Immobili' => '/immobile/index', $immobile->getIdentificativo() => '/immobile/show/immobile/' . $immobile->id, 'Modifica' => null];
        return $this->generaVistaForm($form);
    }

    private function generaVistaForm(BaseForm ...$forms): Response
    {
        $this->vars['categorie'] = CategoriaCatastale::cases();
        $this->vars['titoli'] = TitoloPossesso::cases();
        $this->vars['utilizzi'] = UtilizzoImmobile::cases();
        $responseType = ResponseType::httpOk;
        foreach ($forms as $form) {
            if ($form->getResponseType() !== ResponseType::httpOk) {
                $responseType = $form->getResponseType();
            }
        }
        return $this->render->generateView('immobile/form', $this->vars, $responseType);
    }

    public function show(Immobile $immobile, string $al = ''): Response
    {
        $this->autorizzaImmobile($immobile);
        $dataRiferimento = \DateTimeImmutable::createFromFormat('!Y-m-d', $al) ?: new \DateTimeImmutable('today');
        $dataRiferimento = new SismaDate($dataRiferimento->format('Y-m-d'));
        $datiCatastaliModel = new DatiCatastaliModel($this->dataMapper);
        $this->vars['immobile'] = $immobile;
        $this->vars['dataRiferimento'] = $dataRiferimento;
        $this->vars['isOggi'] = $dataRiferimento->format('Y-m-d') === date('Y-m-d');
        $this->vars['datiCatastaliStorico'] = $datiCatastaliModel->getStorico($immobile);
        $this->vars['datiCatastaliVigenti'] = $datiCatastaliModel->getVigenteAl($immobile, $dataRiferimento);
        $this->vars['possessi'] = (new PossessoModel($this->dataMapper))->getStoricoImmobile($immobile);
        $this->vars['graffati'] = (new IdentificativoGraffatoModel($this->dataMapper))->getElenco($immobile);
        $this->vars['pageTitle'] = $immobile->tipoCatasto->getLabel() . ' ' . $immobile->getIdentificativo();
        $this->vars['briciole'] = ['Anagrafica' => '/soggetto/index', 'Immobili' => '/immobile/index', $immobile->getIdentificativo() => null];
        return $this->render->generateView('immobile/show', $this->vars);
    }

    public function delete(Request $request, Immobile $immobile): Response
    {
        $this->autorizzaImmobile($immobile);
        $this->verificaCsrf($request);
        $possessoModel = new PossessoModel($this->dataMapper);
        $soggetti = [];
        foreach ($possessoModel->getStoricoImmobile($immobile) as $possesso) {
            $soggetti[$possesso->soggetto->id] = $possesso->soggetto;
        }
        $primaTransazione = $this->dataMapper->startTransaction();
        $possessoModel->deleteByImmobile($immobile);
        (new DatiCatastaliModel($this->dataMapper))->deleteByImmobile($immobile);
        (new IdentificativoGraffatoModel($this->dataMapper))->deleteByImmobile($immobile);
        $this->dataMapper->delete($immobile);
        foreach ($soggetti as $soggetto) {
            (new SincronizzazioneSoggetto($this->dataMapper))->aggiorna($soggetto);
        }
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio('Immobile eliminato dall\'archivio.');
        return $this->router->redirect('immobile/index');
    }

    /**
     * Ricerca per l'autocompletamento degli immobili dello studio.
     */
    public function cerca(string $testo = ''): Response
    {
        $risultati = [];
        if (mb_strlen(trim($testo)) >= 1) {
            foreach ((new ImmobileModel($this->dataMapper))->cerca($this->studio, $testo, null, 0, 15) as $immobile) {
                $risultati[] = ['id' => $immobile->id, 'etichetta' => $immobile->tipoCatasto->getLabel() . ' ' . $immobile->getIdentificativo() . ' - ' . $immobile->getUbicazione()];
            }
        }
        return $this->render->generateJson($risultati);
    }
}
