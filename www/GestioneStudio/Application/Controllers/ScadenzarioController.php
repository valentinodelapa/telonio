<?php

namespace GestioneStudio\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Models\SoggettoModel;
use Telonio\Application\Enumerations\ModuloSuite;
use Telonio\Application\Services\RaccoltaScadenze;
use GestioneStudio\Application\BaseClasses\GestioneStudioController;
use GestioneStudio\Application\Entities\AssegnazioneAdempimento;
use GestioneStudio\Application\Enumerations\FiltroStato;
use GestioneStudio\Application\Enumerations\StatoLavorazione;
use GestioneStudio\Application\Forms\LavorazioneScadenzaForm;
use GestioneStudio\Application\Models\AssegnazioneAdempimentoModel;
use GestioneStudio\Application\Services\Scadenzario;
use GestioneStudio\Application\Services\VoceScadenzario;
use SismaFramework\Core\Exceptions\AccessDeniedException;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Scadenzario dello studio: scadenze di tutti i moduli per periodo, stato e
 * operatore, schede dei clienti con gli adempimenti a cui sono tenuti e
 * stato di lavorazione delle singole scadenze.
 */
class ScadenzarioController extends GestioneStudioController
{

    public const GIORNI_PRECEDENTI = 30;
    public const GIORNI_SUCCESSIVI = 60;
    private const RISULTATI_PER_PAGINA = 25;
    private const PERIODO_MASSIMO = 731;

    public function index(string $dal = '', string $al = '', string $stato = 'aperte', int $responsabile = Scadenzario::RESPONSABILE_TUTTI, string $modulo = ''): Response
    {
        $inizio = self::data($dal) ?? new SismaDate('-' . self::GIORNI_PRECEDENTI . ' days');
        $fine = self::data($al) ?? new SismaDate('+' . self::GIORNI_SUCCESSIVI . ' days');
        if (($fine < $inizio) || ((int) $inizio->diff($fine)->format('%a') > self::PERIODO_MASSIMO)) {
            $this->aggiungiMessaggio('Periodo non valido: al massimo due anni, con la fine dopo l\'inizio.', 'warning');
            $inizio = new SismaDate('-' . self::GIORNI_PRECEDENTI . ' days');
            $fine = new SismaDate('+' . self::GIORNI_SUCCESSIVI . ' days');
        }
        $filtro = FiltroStato::tryFrom($stato) ?? FiltroStato::aperte;
        $moduloScelto = ModuloSuite::tryFrom($modulo);
        $this->vars['voci'] = (new Scadenzario($this->dataMapper))->getVoci($this->studio, $inizio, $fine, null, $moduloScelto, $filtro, $responsabile);
        $this->vars['dal'] = $inizio;
        $this->vars['al'] = $fine;
        $this->vars['filtro'] = $filtro;
        $this->vars['filtri'] = FiltroStato::cases();
        $this->vars['responsabile'] = $responsabile;
        $this->vars['operatori'] = $this->getOperatori();
        $this->vars['moduloScelto'] = $moduloScelto;
        $this->vars['moduliFonte'] = array_map(fn(string $valore) => ModuloSuite::from($valore), array_keys((new RaccoltaScadenze($this->dataMapper))->getFonti()));
        $this->vars['oggi'] = new SismaDate();
        $this->vars['ritorno'] = VoceScadenzario::codifica('/scadenzario/index?' . http_build_query(['dal' => $inizio->format('Y-m-d'), 'al' => $fine->format('Y-m-d'), 'stato' => $filtro->value, 'responsabile' => $responsabile, 'modulo' => $modulo]));
        $this->vars['sezione'] = 'scadenzario';
        $this->impostaBriciole(['Scadenzario' => null]);
        return $this->render->generateView('scadenzario/index', $this->vars);
    }

    /**
     * Clienti dello studio (di default i soli clienti) con gli adempimenti
     * in vigore.
     */
    public function clienti(string $testo = '', bool $tutti = false, int $pagina = 1): Response
    {
        $soggettoModel = new SoggettoModel($this->dataMapper);
        $totale = $soggettoModel->contaRisultati($this->studio, $testo, null, null, $tutti === false);
        $pagine = max(1, (int) ceil($totale / self::RISULTATI_PER_PAGINA));
        $pagina = min(max(1, $pagina), $pagine);
        $assegnazioneModel = new AssegnazioneAdempimentoModel($this->dataMapper);
        $oggi = new SismaDate();
        $clienti = [];
        foreach ($soggettoModel->cerca($this->studio, $testo, null, null, $tutti === false, ($pagina - 1) * self::RISULTATI_PER_PAGINA, self::RISULTATI_PER_PAGINA) as $soggetto) {
            $vigenti = array_filter($assegnazioneModel->getPerSoggetto($soggetto), fn(AssegnazioneAdempimento $assegnazione) => $assegnazione->isVigenteAl($oggi));
            $clienti[] = ['soggetto' => $soggetto, 'adempimenti' => count($vigenti)];
        }
        $this->vars['clienti'] = $clienti;
        $this->vars['totale'] = $totale;
        $this->vars['pagina'] = $pagina;
        $this->vars['pagine'] = $pagine;
        $this->vars['testo'] = $testo;
        $this->vars['inclusiTutti'] = $tutti;
        $this->vars['sezione'] = 'clienti';
        $this->impostaBriciole(['Clienti' => null]);
        return $this->render->generateView('scadenzario/clienti', $this->vars);
    }

    /**
     * Scheda del cliente: adempimenti assegnati e scadenze dell'anno di
     * tutti i moduli.
     */
    public function soggetto(Soggetto $soggetto, int $anno = 0): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $anno = self::normalizzaAnno($anno);
        $this->vars['soggetto'] = $soggetto;
        $this->vars['anno'] = $anno;
        $this->vars['anni'] = self::getAnni();
        $this->vars['assegnazioni'] = (new AssegnazioneAdempimentoModel($this->dataMapper))->getPerSoggetto($soggetto);
        $this->vars['voci'] = (new Scadenzario($this->dataMapper))->getVoci($this->studio, new SismaDate($anno . '-01-01'), new SismaDate($anno . '-12-31'), $soggetto);
        $this->vars['oggi'] = new SismaDate();
        $this->vars['ritorno'] = VoceScadenzario::codifica('/scadenzario/soggetto/soggetto/' . $soggetto->id . '/anno/' . $anno);
        $this->vars['pageTitle'] = $soggetto->nominativo . ' · scadenze ' . $anno;
        $this->vars['sezione'] = 'clienti';
        $this->impostaBriciole(['Clienti' => '/scadenzario/clienti', $soggetto->nominativo => null]);
        return $this->render->generateView('scadenzario/soggetto', $this->vars);
    }

    /**
     * Stato di lavorazione, responsabile e note della scadenza.
     */
    public function lavorazione(Request $request, string $modulo, string $rif, string $data, int $soggetto = 0, string $ritorno = ''): Response
    {
        $voce = $this->trovaVoce($modulo, $rif, $data, $soggetto);
        if ($voce === null) {
            return $this->scadenzaNonTrovata($ritorno);
        }
        $scadenzario = new Scadenzario($this->dataMapper);
        $lavorazione = $scadenzario->preparaLavorazione($this->studio, $voce);
        $statoPrecedente = $voce->lavorazione?->stato;
        if ($voce->isCompletataDalModulo() === false) {
            $form = new LavorazioneScadenzaForm($lavorazione);
            if ($this->isInvioValido($request, $form)) {
                $scadenzario->registra($voce->scadenza, $form->resolveEntity(), $statoPrecedente, $this->utente);
                $this->aggiungiMessaggio('Lavorazione della scadenza salvata.');
                return $this->torna($ritorno);
            }
            $this->impostaVariabiliForm($form, $lavorazione);
        }
        $this->vars['voce'] = $voce;
        $this->vars['stati'] = StatoLavorazione::cases();
        $this->vars['operatori'] = $this->getOperatori();
        $this->vars['urlAnnulla'] = $this->getUrlRitorno($ritorno);
        $this->vars['pageTitle'] = $voce->scadenza->descrizione;
        $this->impostaBriciole(['Scadenzario' => '/scadenzario/index', 'Lavorazione della scadenza' => null]);
        return $this->render->generateView('scadenzario/lavorazione', $this->vars);
    }

    /**
     * Cambio rapido dello stato dall'elenco (es. "completata").
     */
    public function stato(Request $request, string $modulo, string $rif, string $data, string $nuovo, int $soggetto = 0, string $ritorno = ''): Response
    {
        $this->verificaCsrf($request);
        $voce = $this->trovaVoce($modulo, $rif, $data, $soggetto);
        $stato = StatoLavorazione::tryFrom($nuovo);
        if (($voce === null) || ($stato === null)) {
            return $this->scadenzaNonTrovata($ritorno);
        }
        if ($voce->isCompletataDalModulo()) {
            $this->aggiungiMessaggio('Scadenza già completata nel modulo ' . $voce->scadenza->modulo->getLabel() . '.', 'warning');
            return $this->torna($ritorno);
        }
        $scadenzario = new Scadenzario($this->dataMapper);
        $lavorazione = $scadenzario->preparaLavorazione($this->studio, $voce);
        $statoPrecedente = $voce->lavorazione?->stato;
        $lavorazione->stato = $stato;
        $scadenzario->registra($voce->scadenza, $lavorazione, $statoPrecedente, $this->utente);
        $this->aggiungiMessaggio('Scadenza "' . $voce->scadenza->descrizione . '": ' . mb_strtolower($stato->getLabel()) . '.');
        return $this->torna($ritorno);
    }

    private function trovaVoce(string $modulo, string $rif, string $data, int $soggettoId): ?VoceScadenzario
    {
        $moduloScadenza = ModuloSuite::tryFrom($modulo);
        $riferimento = VoceScadenzario::decodifica($rif);
        $giorno = self::data($data);
        if (($moduloScadenza === null) || ($riferimento === null) || ($giorno === null)) {
            return null;
        }
        $soggetto = null;
        if ($soggettoId > 0) {
            $soggetto = (new SoggettoModel($this->dataMapper))->getEntityById($soggettoId);
            if ($soggetto === null) {
                return null;
            }
            $this->autorizzaSoggetto($soggetto);
        }
        return (new Scadenzario($this->dataMapper))->trova($this->studio, $moduloScadenza, $riferimento, $giorno, $giorno, $soggetto);
    }

    private function scadenzaNonTrovata(string $ritorno): Response
    {
        $this->aggiungiMessaggio('Scadenza non trovata: potrebbe essere cambiata nel modulo di origine. Aggiorna l\'elenco.', 'warning');
        return $this->torna($ritorno);
    }

    private function torna(string $ritorno): Response
    {
        return $this->router->redirect(ltrim($this->getUrlRitorno($ritorno), '/'));
    }

    /**
     * Pagina di ritorno codificata nell'URL, solo dentro lo scadenzario.
     */
    private function getUrlRitorno(string $ritorno): string
    {
        $url = VoceScadenzario::decodifica($ritorno);
        if (($url === null) || ($url === '')) {
            return '/scadenzario/index';
        }
        if ((str_starts_with($url, '/scadenzario/') === false) || str_contains($url, '//')) {
            throw new AccessDeniedException('Pagina di ritorno non valida');
        }
        return $url;
    }

    private static function data(string $valore): ?SismaDate
    {
        $data = \DateTimeImmutable::createFromFormat('!Y-m-d', $valore);
        return ($data === false) || ($data->format('Y-m-d') !== $valore) ? null : new SismaDate($valore);
    }
}
