<?php

namespace Anagrafica\Application\Controllers;

use Anagrafica\Application\BaseClasses\AnagraficaController;
use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\StatoCivile;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Forms\DatiFiscaliForm;
use Anagrafica\Application\Forms\SoggettoForm;
use Anagrafica\Application\Models\ContoCorrenteModel;
use Anagrafica\Application\Models\DatiCatastaliModel;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Anagrafica\Application\Models\PossessoModel;
use Anagrafica\Application\Models\DocumentoIdentitaModel;
use Anagrafica\Application\Models\IndirizzoModel;
use Anagrafica\Application\Models\LegameModel;
use Anagrafica\Application\Models\RecapitoModel;
use Anagrafica\Application\Models\SoggettoModel;
use Anagrafica\Application\Services\SincronizzazioneSoggetto;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\ResponseType;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDate;

class SoggettoController extends AnagraficaController
{

    private const RISULTATI_PER_PAGINA = 25;

    public function index(string $testo = '', string $tipo = '', string $attivita = '', bool $clienti = false, int $pagina = 1): Response
    {
        $tipoSoggetto = TipoSoggetto::tryFrom($tipo);
        $tipoAttivita = TipoAttivita::tryFrom($attivita);
        $soggettoModel = new SoggettoModel($this->dataMapper);
        $totale = $soggettoModel->contaRisultati($this->studio, $testo, $tipoSoggetto, $tipoAttivita, $clienti);
        $pagine = max(1, (int) ceil($totale / self::RISULTATI_PER_PAGINA));
        $pagina = min(max(1, $pagina), $pagine);
        $this->vars['soggetti'] = $soggettoModel->cerca($this->studio, $testo, $tipoSoggetto, $tipoAttivita, $clienti, ($pagina - 1) * self::RISULTATI_PER_PAGINA, self::RISULTATI_PER_PAGINA);
        $this->vars['totale'] = $totale;
        $this->vars['pagina'] = $pagina;
        $this->vars['pagine'] = $pagine;
        $this->vars['testo'] = $testo;
        $this->vars['tipoSoggetto'] = $tipoSoggetto;
        $this->vars['tipoAttivita'] = $tipoAttivita;
        $this->vars['soloClienti'] = $clienti;
        $this->vars['tipiSoggetto'] = TipoSoggetto::cases();
        $this->vars['tipiAttivita'] = TipoAttivita::cases();
        $this->vars['briciole'] = ['Anagrafica' => null];
        return $this->render->generateView('soggetto/index', $this->vars);
    }

    /**
     * Per le persone fisiche con attività (ditta individuale, professionista)
     * il parametro attivita fa raccogliere anche i dati fiscali iniziali.
     */
    public function create(Request $request, TipoSoggetto $tipo, string $attivita = ''): Response
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->studio = $this->studio;
        $soggetto->tipoSoggetto = $tipo;
        $soggettoForm = new SoggettoForm($soggetto);
        $forms = [$soggettoForm];
        $datiFiscali = null;
        $tipoAttivita = $tipo->isPersonaFisica() ? TipoAttivita::tryFrom($attivita) : null;
        if (($tipo->isPersonaFisica() === false) || ($tipoAttivita !== null)) {
            $datiFiscali = new DatiFiscali($this->dataMapper);
            $datiFiscali->soggetto = $soggetto;
            $datiFiscali->tipoAttivita = $tipoAttivita;
            $datiFiscaliForm = new DatiFiscaliForm($datiFiscali);
            $forms[] = $datiFiscaliForm;
        }
        if ($this->isInvioValido($request, ...$forms)) {
            $primaTransazione = $this->dataMapper->startTransaction();
            $soggetto = $soggettoForm->resolveEntity();
            if ($datiFiscali !== null) {
                $datiFiscali = $datiFiscaliForm->resolveEntity();
                if ($tipo->isPersonaFisica() === false) {
                    $soggetto->nominativo = $datiFiscali->denominazione;
                }
            }
            $this->dataMapper->save($soggetto);
            if ($datiFiscali !== null) {
                $this->dataMapper->save($datiFiscali);
            }
            (new SincronizzazioneSoggetto($this->dataMapper))->aggiorna($soggetto, false);
            $this->dataMapper->commitTransaction($primaTransazione);
            $this->aggiungiMessaggio('Soggetto inserito in anagrafica.');
            if ($soggettoForm->isCodiceFiscaleCalcolato()) {
                $this->aggiungiMessaggio('Il codice fiscale è stato calcolato dai dati anagrafici: verificalo su un documento (possibili omocodie).', 'warning');
            }
            return $this->vaiAllaScheda($soggetto);
        }
        $this->impostaVariabiliForm($soggettoForm, $soggetto);
        if ($datiFiscali !== null) {
            $this->impostaVariabiliForm($datiFiscaliForm, $datiFiscali, 'datiFiscaliEntity', 'datiFiscaliErrors');
        }
        $this->vars['soggetto'] = $soggetto;
        $this->vars['nuovo'] = true;
        $this->vars['pageTitle'] = 'Nuovo soggetto: ' . mb_strtolower(($tipoAttivita ?? $tipo)->getLabel());
        $this->vars['briciole'] = ['Anagrafica' => '/soggetto/index', 'Nuovo soggetto' => null];
        return $this->generaVistaForm(...$forms);
    }

    public function update(Request $request, Soggetto $soggetto): Response
    {
        $this->autorizza($soggetto);
        $soggettoForm = new SoggettoForm($soggetto);
        if ($this->isInvioValido($request, $soggettoForm)) {
            $soggetto = $soggettoForm->resolveEntity();
            $this->dataMapper->save($soggetto);
            (new SincronizzazioneSoggetto($this->dataMapper))->aggiorna($soggetto);
            $this->aggiungiMessaggio('Dati anagrafici aggiornati.');
            return $this->vaiAllaScheda($soggetto);
        }
        $this->impostaVariabiliForm($soggettoForm, $soggetto);
        $this->vars['soggetto'] = $soggetto;
        $this->vars['nuovo'] = false;
        $this->vars['pageTitle'] = 'Modifica dati anagrafici';
        $this->impostaBriciole($soggetto, 'Modifica');
        return $this->generaVistaForm($soggettoForm);
    }

    private function generaVistaForm(BaseForm ...$forms): Response
    {
        $this->vars['sessi'] = Sesso::cases();
        $this->vars['statiCivili'] = StatoCivile::cases();
        $this->vars['tipiAttivita'] = TipoAttivita::cases();
        $responseType = ResponseType::httpOk;
        foreach ($forms as $form) {
            if ($form->getResponseType() !== ResponseType::httpOk) {
                $responseType = $form->getResponseType();
            }
        }
        return $this->render->generateView('soggetto/form', $this->vars, $responseType);
    }

    public function show(Soggetto $soggetto, string $scheda = 'generale', string $al = ''): Response
    {
        $this->autorizza($soggetto);
        $dataRiferimento = $this->leggiData($al);
        $datiFiscaliModel = new DatiFiscaliModel($this->dataMapper);
        $indirizzoModel = new IndirizzoModel($this->dataMapper);
        $legameModel = new LegameModel($this->dataMapper);
        $this->vars['soggetto'] = $soggetto;
        $this->vars['scheda'] = $scheda;
        $this->vars['dataRiferimento'] = $dataRiferimento;
        $this->vars['isOggi'] = $dataRiferimento->format('Y-m-d') === date('Y-m-d');
        $this->vars['datiFiscaliStorico'] = $datiFiscaliModel->getStorico($soggetto);
        $this->vars['datiFiscaliVigenti'] = $datiFiscaliModel->getVigenteAl($soggetto, $dataRiferimento);
        $this->vars['indirizziStorico'] = $indirizzoModel->getStorico($soggetto);
        $this->vars['indirizziVigenti'] = $indirizzoModel->getVigentiAl($soggetto, $dataRiferimento);
        $this->vars['recapiti'] = (new RecapitoModel($this->dataMapper))->getElenco($soggetto);
        $this->vars['conti'] = (new ContoCorrenteModel($this->dataMapper))->getElenco($soggetto);
        $this->vars['collegati'] = $legameModel->getCollegati($soggetto);
        $this->vars['incarichi'] = $legameModel->getIncarichi($soggetto);
        $this->vars['documenti'] = $soggetto->isPersonaFisica() ? (new DocumentoIdentitaModel($this->dataMapper))->getElenco($soggetto) : [];
        $this->vars['possessi'] = (new PossessoModel($this->dataMapper))->getStoricoSoggetto($soggetto);
        $datiCatastaliModel = new DatiCatastaliModel($this->dataMapper);
        $datiCatastaliVigenti = [];
        foreach ($this->vars['possessi'] as $possesso) {
            $datiCatastaliVigenti[$possesso->immobile->id] ??= $datiCatastaliModel->getVigenteAl($possesso->immobile, $dataRiferimento);
        }
        $this->vars['datiCatastaliVigenti'] = $datiCatastaliVigenti;
        $this->vars['pageTitle'] = $soggetto->nominativo;
        $this->vars['briciole'] = ['Anagrafica' => '/soggetto/index', $soggetto->nominativo => null];
        return $this->render->generateView('soggetto/show', $this->vars);
    }

    private function leggiData(string $data): SismaDate
    {
        $dataLetta = \DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        return new SismaDate($dataLetta === false ? 'today' : $dataLetta->format('Y-m-d'));
    }

    public function delete(Request $request, Soggetto $soggetto): Response
    {
        $this->autorizza($soggetto);
        $this->verificaCsrf($request);
        $legameModel = new LegameModel($this->dataMapper);
        if ($legameModel->countBySoggettoCollegato($soggetto) > 0) {
            $this->aggiungiMessaggio('Il soggetto ricopre ruoli presso altri soggetti: rimuovi prima quei collegamenti.', 'danger');
            return $this->vaiAllaScheda($soggetto, 'legami');
        }
        $primaTransazione = $this->dataMapper->startTransaction();
        $legameModel->deleteBySoggetto($soggetto);
        (new PossessoModel($this->dataMapper))->deleteBySoggetto($soggetto);
        (new IndirizzoModel($this->dataMapper))->deleteBySoggetto($soggetto);
        (new RecapitoModel($this->dataMapper))->deleteBySoggetto($soggetto);
        (new ContoCorrenteModel($this->dataMapper))->deleteBySoggetto($soggetto);
        (new DocumentoIdentitaModel($this->dataMapper))->deleteBySoggetto($soggetto);
        (new DatiFiscaliModel($this->dataMapper))->deleteBySoggetto($soggetto);
        $this->dataMapper->delete($soggetto);
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio('Soggetto eliminato: ' . $soggetto->nominativo . '.');
        return $this->router->redirect('soggetto/index');
    }

    /**
     * Ricerca per l'autocompletamento dei soggetti dello studio.
     */
    public function cerca(string $testo = ''): Response
    {
        $risultati = [];
        if (mb_strlen(trim($testo)) >= 2) {
            foreach ((new SoggettoModel($this->dataMapper))->cerca($this->studio, $testo, null, null, null, 0, 15) as $soggetto) {
                $risultati[] = ['id' => $soggetto->id, 'etichetta' => $soggetto->nominativo . ' - ' . $soggetto->codiceFiscale];
            }
        }
        return $this->render->generateJson($risultati);
    }
}
