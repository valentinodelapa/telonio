<?php

namespace Deleghe\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Models\ContoCorrenteModel;
use Deleghe\Application\BaseClasses\DelegheController;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Ravvedimento;
use Deleghe\Application\Entities\SanzioneCivile;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\CodiceIdentificativo;
use Deleghe\Application\Enumerations\ModelloF24;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Enumerations\TabellaF24;
use Deleghe\Application\Exceptions\DelegaNonModificabileException;
use Deleghe\Application\Forms\DelegaForm;
use Deleghe\Application\Models\CodiceTabellaF24Model;
use Deleghe\Application\Models\CreditoModel;
use Deleghe\Application\Models\RavvedimentoModel;
use Deleghe\Application\Models\SanzioneCivileModel;
use Deleghe\Application\Models\VersamentoModel;
use Deleghe\Application\Services\CalcoloRavvedimento;
use Deleghe\Application\Services\CalcoloSanzioniCivili;
use Deleghe\Application\Services\Compensazioni;
use Deleghe\Application\Services\ControlloDelega;
use Deleghe\Application\Services\CopiaVersamenti;
use Deleghe\Application\Services\DatiContribuente;
use Deleghe\Application\Services\EsitoControllo;
use Deleghe\Application\Services\GenerazioneDeleghe;
use Deleghe\Application\Services\ModelloCompilato;
use Deleghe\Application\Services\RavvedimentoService;
use Deleghe\Application\Services\SanzioniCiviliService;
use Deleghe\Application\Services\Stampa\ModuloUfficialeF24;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;

/**
 * Deleghe F24 del contribuente: generazione dai versamenti da versare,
 * testata, compensazioni, cambi di stato e stampa del modello. Le deleghe
 * si raggiungono dalla scheda del contribuente (ContribuenteController).
 */
class DelegaController extends DelegheController
{

    /**
     * Origini dei versamenti calcolati nella delega: eliminando la delega si
     * eliminano con il loro calcolo, gli altri versamenti tornano da versare.
     */
    private const ORIGINI_DELEGA = [Ravvedimento::ORIGINE, SanzioneCivile::ORIGINE];

    public function index(): Response
    {
        return $this->router->redirect('contribuente/index');
    }

    /**
     * Nuova delega del contribuente per la data scelta: vi entrano subito i
     * versamenti da versare con quella data che il modello ammette e, a
     * richiesta, le compensazioni con i crediti disponibili.
     */
    public function create(Request $request, Soggetto $soggetto, string $scadenza = ''): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $delega = new Delega($this->dataMapper);
        $delega->studio = $this->studio;
        $delega->soggetto = $soggetto;
        $delega->scadenza = self::leggiData($scadenza) ?? self::prossimaScadenza();
        $form = new DelegaForm($delega);
        if ($this->isInvioValido($request, $form)) {
            $delega = $form->resolveEntity();
            $delega->contoAddebito ??= (new ContoCorrenteModel($this->dataMapper))->getPredefinito($delega->soggetto);
            $primaTransazione = $this->dataMapper->startTransaction();
            $this->dataMapper->save($delega);
            $daVersare = (new VersamentoModel($this->dataMapper))->getDaVersare($soggetto, $delega->scadenza);
            $ammessi = array_filter($daVersare, fn(Versamento $versamento) => $delega->modello->ammetteSezione($versamento->sezione));
            (new GenerazioneDeleghe($this->dataMapper))->aggiungi($delega, $ammessi);
            $compensazioni = isset($request->input['compensa']) ? (new Compensazioni($this->dataMapper))->compensaAutomaticamente($delega) : [];
            $this->dataMapper->commitTransaction($primaTransazione);
            $messaggio = count($ammessi) === 0 ? 'Delega creata: nessun versamento da versare il ' . $delega->scadenza->format('d/m/Y') . ', aggiungili nelle sezioni del modello.'
                    : 'Delega creata con ' . count($ammessi) . ' versamenti da versare del ' . $delega->scadenza->format('d/m/Y') . (count($compensazioni) > 0 ? ' e ' . count($compensazioni) . ' compensazioni' : '') . '.';
            if (count($daVersare) > count($ammessi)) {
                $messaggio .= ' ' . (count($daVersare) - count($ammessi)) . ' versamenti della stessa data non sono previsti nel ' . $delega->modello->getLabel() . ': restano da versare.';
            }
            $this->aggiungiMessaggio($messaggio);
            return $this->vaiAllaDelega($delega);
        }
        $dateDaVersare = [];
        foreach ((new VersamentoModel($this->dataMapper))->getDaVersare($soggetto) as $versamento) {
            $data = $versamento->scadenza->format('Y-m-d');
            $dateDaVersare[$data] = ($dateDaVersare[$data] ?? 0) + 1;
        }
        $this->vars['dateDaVersare'] = $dateDaVersare;
        $creditoModel = new CreditoModel($this->dataMapper);
        $this->vars['conCrediti'] = count(array_filter(iterator_to_array($creditoModel->getElenco($soggetto), false), fn($credito) => $creditoModel->getResiduo($credito) > 0)) > 0;
        $this->vars['pageTitle'] = 'Nuova delega F24';
        $this->impostaBricioleContribuente($soggetto, 'Nuova delega');
        return $this->generaVistaForm($form, $delega, true);
    }

    /**
     * Genera (o completa) le deleghe del contribuente per una data con i
     * versamenti da versare di quella data.
     */
    public function genera(Request $request, Soggetto $soggetto): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $this->verificaCsrf($request);
        $scadenza = self::leggiData((string) ($request->input['scadenza'] ?? ''));
        if ($scadenza === null) {
            $this->aggiungiMessaggio('Indicare la data di versamento.', 'danger');
            return $this->vaiAlContribuente($soggetto);
        }
        try {
            $deleghe = (new GenerazioneDeleghe($this->dataMapper))->genera($soggetto, $scadenza, isset($request->input['compensa']));
        } catch (DelegaNonModificabileException | \InvalidArgumentException $errore) {
            $this->aggiungiMessaggio($errore->getMessage(), 'danger');
            return $this->vaiAlContribuente($soggetto);
        }
        if (count($deleghe) === 0) {
            $this->aggiungiMessaggio('Nessun versamento da versare il ' . $scadenza->format('d/m/Y') . '.', 'warning');
            return $this->vaiAlContribuente($soggetto);
        }
        $this->aggiungiMessaggio(count($deleghe) === 1 ? 'Delega del ' . $scadenza->format('d/m/Y') . ' generata con i versamenti da versare.'
                        : count($deleghe) . ' deleghe generate (modelli diversi per ELIDE e accise).');
        return count($deleghe) === 1 ? $this->vaiAllaDelega($deleghe[0]) : $this->vaiAlContribuente($soggetto, 'deleghe');
    }

    /**
     * Inserisce nella delega i versamenti da versare con la sua data.
     */
    public function aggiungiDaVersare(Request $request, Delega $delega): Response
    {
        $this->autorizza($delega);
        $this->verificaCsrf($request);
        if (($bloccata = $this->verificaModificabile($delega)) !== null) {
            return $bloccata;
        }
        $versamenti = array_filter((new VersamentoModel($this->dataMapper))->getDaVersare($delega->soggetto, $delega->scadenza),
                fn(Versamento $versamento) => $delega->modello->ammetteSezione($versamento->sezione));
        (new GenerazioneDeleghe($this->dataMapper))->aggiungi($delega, $versamenti);
        $this->aggiungiMessaggio(count($versamenti) . ' versamenti aggiunti alla delega.');
        return $this->vaiAllaDelega($delega);
    }

    /**
     * Compensa con i crediti disponibili fino a capienza.
     */
    public function compensa(Request $request, Delega $delega): Response
    {
        $this->autorizza($delega);
        $this->verificaCsrf($request);
        if (($bloccata = $this->verificaModificabile($delega)) !== null) {
            return $bloccata;
        }
        try {
            $righi = (new Compensazioni($this->dataMapper))->compensaAutomaticamente($delega);
            $this->aggiungiMessaggio(count($righi) === 0 ? 'Nessun credito da compensare.' : 'Compensati € ' . EsitoControllo::euro(array_sum(array_map(fn(Versamento $rigo) => EsitoControllo::centesimi($rigo->importoCredito), $righi))) . ' di crediti.');
        } catch (\InvalidArgumentException $errore) {
            $this->aggiungiMessaggio($errore->getMessage(), 'danger');
        }
        return $this->vaiAllaDelega($delega);
    }

    public function update(Request $request, Delega $delega): Response
    {
        $this->autorizza($delega);
        if (($bloccata = $this->verificaModificabile($delega)) !== null) {
            return $bloccata;
        }
        $dataPrecedente = $delega->scadenza->format('Y-m-d');
        $form = new DelegaForm($delega);
        if ($this->isInvioValido($request, $form)) {
            $delega = $form->resolveEntity();
            $dataCambiata = $delega->scadenza->format('Y-m-d') !== $dataPrecedente;
            $ravvedimenti = iterator_to_array((new RavvedimentoModel($this->dataMapper))->getElenco($delega), false);
            $sanzioniCivili = iterator_to_array((new SanzioneCivileModel($this->dataMapper))->getElenco($delega), false);
            $errore = $dataCambiata ? $this->verificaRicalcoli($delega, $ravvedimenti, $sanzioniCivili) : null;
            if ($errore !== null) {
                $this->aggiungiMessaggio('Data non modificata: ' . $errore, 'danger');
                return $this->vaiAllaDelega($delega);
            }
            $delega->dataModifica = new SismaDateTime();
            $primaTransazione = $this->dataMapper->startTransaction();
            $this->dataMapper->save($delega);
            foreach ((new VersamentoModel($this->dataMapper))->getElenco($delega) as $versamento) {
                if ($versamento->scadenza->format('Y-m-d') !== $delega->scadenza->format('Y-m-d')) {
                    $versamento->scadenza = $delega->scadenza;
                    $this->dataMapper->save($versamento);
                }
            }
            if ($dataCambiata) {
                // sanzioni e interessi dipendono dalla data di versamento
                foreach ($ravvedimenti as $ravvedimento) {
                    (new RavvedimentoService($this->dataMapper))->registra($ravvedimento);
                }
                foreach ($sanzioniCivili as $sanzioneCivile) {
                    (new SanzioniCiviliService($this->dataMapper))->registra($sanzioneCivile);
                }
            }
            $this->dataMapper->commitTransaction($primaTransazione);
            $ricalcolati = count($ravvedimenti) + count($sanzioniCivili);
            $this->aggiungiMessaggio('Dati della delega aggiornati' . (($dataCambiata && ($ricalcolati > 0)) ? ': ravvedimenti e sanzioni civili ricalcolati alla nuova data.' : '.'));
            return $this->vaiAllaDelega($delega);
        }
        $this->vars['pageTitle'] = 'Modifica delega';
        $this->impostaBriciole($delega, 'Modifica');
        return $this->generaVistaForm($form, $delega, false);
    }

    /**
     * Prova i calcoli alla nuova data prima di salvare: un ravvedimento o
     * una sanzione civile non ricalcolabile (es. data non successiva alla
     * scadenza originale) impedisce il cambio di data.
     *
     * @param Ravvedimento[] $ravvedimenti
     * @param SanzioneCivile[] $sanzioniCivili
     */
    private function verificaRicalcoli(Delega $delega, array $ravvedimenti, array $sanzioniCivili): ?string
    {
        try {
            foreach ($ravvedimenti as $ravvedimento) {
                (new CalcoloRavvedimento($this->dataMapper))->calcola($ravvedimento->importoTributo, $ravvedimento->scadenzaOriginale, $delega->scadenza,
                        $ravvedimento->termineDichiarazione, $ravvedimento->termineDichiarazioneSuccessiva, $ravvedimento->circostanza);
            }
            foreach ($sanzioniCivili as $sanzioneCivile) {
                (new CalcoloSanzioniCivili($this->dataMapper))->calcola($sanzioneCivile->importoContributi, $sanzioneCivile->scadenzaOriginale, $delega->scadenza,
                        $sanzioneCivile->regime, $sanzioneCivile->spontaneo);
            }
        } catch (\InvalidArgumentException | \UnexpectedValueException $errore) {
            return $errore->getMessage();
        }
        return null;
    }

    private function generaVistaForm(DelegaForm $form, Delega $delega, bool $nuova): Response
    {
        $this->impostaVariabiliForm($form, $delega);
        $this->vars['delega'] = $delega;
        $this->vars['nuova'] = $nuova;
        $this->vars['codiciIdentificativi'] = CodiceIdentificativo::cases();
        $this->vars['modelli'] = ModelloF24::cases();
        $this->vars['conti'] = $this->getContiAddebito($delega);
        return $this->render->generateView('delega/form', $this->vars, $form->getResponseType());
    }

    /**
     * Conti attivi del contribuente e, se in anagrafica, del secondo
     * soggetto (per i codici identificativi con addebito sul suo conto).
     *
     * @return array<int, string> id => etichetta
     */
    private function getContiAddebito(Delega $delega): array
    {
        $contoCorrenteModel = new ContoCorrenteModel($this->dataMapper);
        $soggetti = [$delega->soggetto];
        if ($delega->codiceFiscaleCoobbligato !== null) {
            $soggetti[] = (new ControlloDelega($this->dataMapper))->trovaSoggetto($delega, $delega->codiceFiscaleCoobbligato);
        }
        $conti = [];
        foreach (array_filter($soggetti) as $soggetto) {
            foreach ($contoCorrenteModel->getAttivi($soggetto) as $conto) {
                $conti[$conto->id] = $conto->getEtichetta() . ' (' . $soggetto->nominativo . ')';
            }
        }
        if (($delega->contoAddebito !== null) && (isset($conti[$delega->contoAddebito->id]) === false)) {
            $conti[$delega->contoAddebito->id] = $delega->contoAddebito->getEtichetta();
        }
        return $conti;
    }

    public function show(Delega $delega): Response
    {
        $this->autorizza($delega);
        $versamentoModel = new VersamentoModel($this->dataMapper);
        $this->vars['delega'] = $delega;
        $this->vars['righe'] = $versamentoModel->getPerSezione($delega);
        $this->vars['esito'] = (new ControlloDelega($this->dataMapper))->verifica($delega);
        $this->vars['sezioni'] = SezioneF24::cases();
        $this->vars['regioni'] = $this->getDenominazioniRegioni();
        $this->vars['ravvedimenti'] = (new RavvedimentoModel($this->dataMapper))->getElenco($delega);
        $this->vars['sanzioniCivili'] = (new SanzioneCivileModel($this->dataMapper))->getElenco($delega);
        $this->vars['daVersare'] = $delega->isModificabile() ? $versamentoModel->getDaVersare($delega->soggetto, $delega->scadenza) : [];
        $creditiDisponibili = [];
        if ($delega->isModificabile() && $delega->modello->ammetteCrediti()) {
            $compensazioni = new Compensazioni($this->dataMapper);
            foreach ((new CreditoModel($this->dataMapper))->getDisponibili($delega->soggetto, $delega->scadenza) as $disponibile) {
                if ($delega->modello->ammetteSezione($disponibile['credito']->sezione)) {
                    $creditiDisponibili[] = $disponibile + ['capienza' => $compensazioni->getCapienza($delega, $disponibile['credito'])];
                }
            }
        }
        $this->vars['creditiDisponibili'] = $creditiDisponibili;
        $this->vars['pageTitle'] = 'Delega F24 del ' . $delega->scadenza->format('d/m/Y');
        $this->impostaBriciole($delega);
        return $this->render->generateView('delega/show', $this->vars);
    }

    /**
     * @return array<string, string> codice => denominazione
     */
    private function getDenominazioniRegioni(): array
    {
        $regioni = [];
        foreach ((new CodiceTabellaF24Model($this->dataMapper))->getVoci(TabellaF24::regioni) as $regione) {
            $regioni[$regione->codice] = $regione->descrizione;
        }
        return $regioni;
    }

    /**
     * Passaggio di stato. La conferma richiede una delega senza errori; la
     * quietanza registra data e protocollo; tornando indietro i dati della
     * quietanza vengono rimossi.
     */
    public function stato(Request $request, Delega $delega, StatoDelega $stato): Response
    {
        $this->autorizza($delega);
        $this->verificaCsrf($request);
        if (in_array($stato, $delega->stato->getPassaggi(), true) === false) {
            $this->aggiungiMessaggio('Passaggio di stato non consentito.', 'danger');
            return $this->vaiAllaDelega($delega);
        }
        if (($stato === StatoDelega::confermata) && ($delega->stato === StatoDelega::bozza)) {
            $esito = (new ControlloDelega($this->dataMapper))->verifica($delega);
            if ($esito->isConfermabile() === false) {
                $this->aggiungiMessaggio('La delega contiene errori: correggili prima di confermarla.', 'danger');
                return $this->vaiAllaDelega($delega);
            }
        }
        if ($stato === StatoDelega::quietanzata) {
            $dataQuietanza = self::leggiData((string) ($request->input['dataQuietanza'] ?? ''));
            if ($dataQuietanza === null) {
                $this->aggiungiMessaggio('Indicare la data della quietanza.', 'danger');
                return $this->vaiAllaDelega($delega);
            }
            $delega->dataQuietanza = $dataQuietanza;
            $protocollo = trim((string) ($request->input['protocollo'] ?? ''));
            $delega->protocollo = $protocollo === '' ? null : mb_substr($protocollo, 0, 50);
        } elseif ($delega->stato === StatoDelega::quietanzata) {
            $delega->dataQuietanza = null;
            $delega->protocollo = null;
        }
        $azione = $stato->getAzione($delega->stato);
        $delega->stato = $stato;
        $delega->dataModifica = new SismaDateTime();
        $this->dataMapper->save($delega);
        $this->aggiungiMessaggio($azione . ': la delega è ora ' . mb_strtolower($stato->getLabel()) . '.');
        return $this->vaiAllaDelega($delega);
    }

    /**
     * Riporta in una nuova data i versamenti inseriti a mano nella delega
     * (non le compensazioni né i versamenti generati dagli altri moduli),
     * con i riferimenti aggiornati (CopiaVersamenti): vanno tra i versamenti
     * da versare e, a richiesta, nella delega di quella data.
     */
    public function duplica(Request $request, Delega $delega, string $scadenza = ''): Response
    {
        $this->autorizza($delega);
        $data = self::leggiData((string) ($request->input['scadenza'] ?? $scadenza)) ?? CopiaVersamenti::spostaData($delega->scadenza, 1);
        $copiaVersamenti = new CopiaVersamenti($this->dataMapper);
        $copie = $copiaVersamenti->copiaDelega($delega, $data);
        $errori = $copiaVersamenti->verifica($copie);
        if (($request->server['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->verificaCsrf($request);
            if ((count($copie) > 0) && (count($errori) === 0)) {
                $primaTransazione = $this->dataMapper->startTransaction();
                $copiaVersamenti->salva($copie);
                $deleghe = isset($request->input['genera']) ? (new GenerazioneDeleghe($this->dataMapper))->genera($delega->soggetto, $data, isset($request->input['compensa'])) : [];
                $this->dataMapper->commitTransaction($primaTransazione);
                $this->aggiungiMessaggio(count($copie) . ' versamenti riportati al ' . $data->format('d/m/Y') . (count($deleghe) > 0 ? ' e delega generata.' : ': sono tra quelli da versare.'));
                return count($deleghe) === 1 ? $this->vaiAllaDelega($deleghe[0]) : $this->vaiAlContribuente($delega->soggetto);
            }
            $this->aggiungiMessaggio('Nessun versamento da riportare o copie non valide.', 'danger');
        }
        $this->vars['delega'] = $delega;
        $this->vars['dataCopia'] = $data;
        $this->vars['copie'] = $copie;
        $this->vars['erroriCopie'] = $errori;
        $this->vars['pageTitle'] = 'Duplica delega';
        $this->impostaBriciole($delega, 'Duplica');
        return $this->render->generateView('delega/duplica', $this->vars);
    }

    /**
     * Elimina la delega in bozza: i versamenti tornano da versare, tranne gli
     * utilizzi dei crediti e i versamenti calcolati nella delega
     * (ravvedimenti e sanzioni civili, eliminati con il calcolo).
     */
    public function delete(Request $request, Delega $delega): Response
    {
        $this->autorizza($delega);
        $this->verificaCsrf($request);
        if (($bloccata = $this->verificaModificabile($delega)) !== null) {
            return $bloccata;
        }
        $soggetto = $delega->soggetto;
        $tornati = 0;
        $primaTransazione = $this->dataMapper->startTransaction();
        // i calcoli puntano alla delega (il framework ne impedisce l'eliminazione finché esistono):
        // si eliminano con i loro servizi, che tolgono i righi generati e riportano gli interessi delle rate rateizzate
        foreach ((new RavvedimentoModel($this->dataMapper))->getElenco($delega) as $ravvedimento) {
            (new RavvedimentoService($this->dataMapper))->elimina($ravvedimento);
        }
        foreach ((new SanzioneCivileModel($this->dataMapper))->getElenco($delega) as $sanzioneCivile) {
            (new SanzioniCiviliService($this->dataMapper))->elimina($sanzioneCivile);
        }
        foreach ((new VersamentoModel($this->dataMapper))->getElenco($delega) as $versamento) {
            if (($versamento->credito !== null) || in_array($versamento->origine, self::ORIGINI_DELEGA, true)) {
                $this->dataMapper->delete($versamento);
            } else {
                $versamento->delega = null;
                $this->dataMapper->save($versamento);
                $tornati++;
            }
        }
        $this->dataMapper->delete($delega);
        $this->dataMapper->commitTransaction($primaTransazione);
        $this->aggiungiMessaggio('Delega eliminata' . ($tornati > 0 ? ': ' . $tornati . ' versamenti sono tornati tra quelli da versare.' : '.'));
        return $this->vaiAlContribuente($soggetto);
    }

    /**
     * Modelli F24 ufficiali compilati, uno per modello della ripartizione:
     * la copia per il soggetto che effettua il versamento o, con $copie,
     * anche le due copie per la banca.
     */
    public function stampa(Delega $delega, bool $copie = false): Response
    {
        $this->autorizza($delega);
        $soggetto = $delega->soggetto;
        $esito = (new ControlloDelega($this->dataMapper))->verifica($delega);
        $modelli = $esito->modelli;
        if (count($modelli) === 0) {
            // delega non ripartibile (es. saldo negativo): anteprima su un solo modello
            $anteprima = new ModelloCompilato();
            foreach ((new VersamentoModel($this->dataMapper))->getPerSezione($delega) as $righeSezione) {
                foreach ($righeSezione as $riga) {
                    $anteprima->aggiungi($riga, EsitoControllo::centesimi($riga->importoDebito), EsitoControllo::centesimi($riga->importoCredito));
                }
            }
            $modelli = [$anteprima];
        }
        $modulo = new ModuloUfficialeF24($delega, $soggetto, (new DatiContribuente($this->dataMapper))->getDomicilio($soggetto, $delega->scadenza));
        $this->vars['pagine'] = array_merge(...array_map(fn(ModelloCompilato $modello) => $modulo->getPagine($modello, $copie), $modelli));
        $this->vars['delega'] = $delega;
        $this->vars['soggetto'] = $soggetto;
        $this->vars['esito'] = $esito;
        $this->vars['copie'] = $copie;
        $this->vars['numeroModelli'] = count($modelli);
        return $this->render->generateView('delega/stampa', $this->vars);
    }
}
