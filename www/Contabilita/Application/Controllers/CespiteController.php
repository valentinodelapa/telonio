<?php

namespace Contabilita\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Entities\Cespite;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Forms\CespiteForm;
use Contabilita\Application\Models\CespiteModel;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\RegistrazioneContabileModel;
use Contabilita\Application\Models\RigaContabileModel;
use Contabilita\Application\Services\Ammortamenti;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Registro dei beni ammortizzabili dell'azienda (cespiti) con il piano di
 * ammortamento dell'esercizio e la scrittura degli ammortamenti.
 */
class CespiteController extends ContabilitaController
{

    public function index(Soggetto $soggetto, int $anno = 0): Response
    {
        return $this->registro($soggetto, $anno, false);
    }

    /**
     * Stampa del registro dei beni ammortizzabili dell'esercizio.
     */
    public function stampa(Soggetto $soggetto, int $anno): Response
    {
        return $this->registro($soggetto, $anno, true);
    }

    private function registro(Soggetto $soggetto, int $anno, bool $stampa): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $esercizio = $this->getEsercizio($soggetto, $anno);
        $ammortamenti = new Ammortamenti($this->dataMapper);
        $esercizi = $ammortamenti->getEsercizi($soggetto);
        $cespiti = [];
        foreach ((new CespiteModel($this->dataMapper))->getElenco($soggetto) as $cespite) {
            $cespiti[] = ['cespite' => $cespite, 'voce' => $esercizio === null ? null : (Ammortamenti::piano($cespite, $esercizi)[$esercizio->anno] ?? null)];
        }
        $this->vars['cespiti'] = $cespiti;
        $this->vars['totaleQuote'] = round(array_sum(array_map(fn(array $voce) => $voce['voce']['quota'] ?? 0.0, $cespiti)), 2);
        $this->vars['scrittura'] = $esercizio === null ? null : (new RegistrazioneContabileModel($this->dataMapper))->getGenerata($soggetto, Ammortamenti::ORIGINE, Ammortamenti::getRiferimento($esercizio->anno));
        $this->vars['acquistiSenzaCespite'] = $esercizio === null ? [] : $this->getAcquistiSenzaCespite($soggetto, $esercizio, $cespiti);
        $this->vars['sezioneAzienda'] = 'cespiti';
        $this->vars['pageTitle'] = $stampa ? 'Registro dei beni ammortizzabili ' . ($esercizio?->getEtichetta() ?? '') : $soggetto->nominativo . ' · Cespiti';
        $this->impostaBriciole($soggetto, null, 'Cespiti');
        return $this->render->generateView(($stampa && ($esercizio !== null)) ? 'cespite/stampa' : 'cespite/index', $this->vars);
    }

    public function create(Request $request, Soggetto $soggetto, int $registrazione = 0): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $cespite = new Cespite($this->dataMapper);
        $cespite->studio = $soggetto->studio;
        $cespite->soggetto = $soggetto;
        $cespite->codice = sprintf('%03d', count((new CespiteModel($this->dataMapper))->getElenco($soggetto)) + 1);
        $cespite->riduzionePrimoEsercizio = true;
        // proposta dall'acquisto: immobilizzazione, costo, data e conti del fondo e dell'ammortamento
        $documento = $registrazione > 0 ? (new RegistrazioneContabileModel($this->dataMapper))->getEntityById($registrazione) : null;
        if (($documento !== null) && ($documento->soggetto->id === $soggetto->id)) {
            $cespite->registrazioneAcquisto = $documento;
            $cespite->descrizione = mb_substr($documento->getDescrizioneCompleta(), 0, 255);
            $cespite->dataAcquisto = $documento->dataDocumento ?? $documento->dataRegistrazione;
            foreach ((new RigaContabileModel($this->dataMapper))->getRighe($documento) as $riga) {
                if (self::isImmobilizzazione($riga->conto) && ($riga->dare > 0)) {
                    $cespite->contoCespite ??= $riga->conto;
                    $cespite->costo += $riga->dare;
                }
            }
            if (isset($cespite->contoCespite)) {
                [$fondo, $ammortamento] = (new Ammortamenti($this->dataMapper))->proponiConti($this->piano, $cespite->contoCespite);
                if ($fondo !== null) {
                    $cespite->contoFondo = $fondo;
                }
                if ($ammortamento !== null) {
                    $cespite->contoAmmortamento = $ammortamento;
                }
                $cespite->deduzioneIntegrale = $cespite->costo <= Ammortamenti::SOGLIA_BENI_MINORI;
                // autovetture: 20% deducibile sul costo fino a 18.075,99 euro (art. 164 TUIR; dal 2027 art. 168 D.Lgs. 117/2026)
                if (str_contains(mb_strtolower($cespite->contoCespite->descrizione), 'autovettur')) {
                    $cespite->percentualeDeducibile = Ammortamenti::DEDUCIBILE_AUTOVETTURE;
                    $cespite->limiteCostoFiscale = Ammortamenti::LIMITE_AUTOVETTURE;
                }
            }
        }
        return $this->gestisci($request, $cespite, 'Nuovo cespite');
    }

    public function update(Request $request, Cespite $cespite): Response
    {
        $this->caricaAzienda($cespite->soggetto);
        return $this->gestisci($request, $cespite, $cespite->getEtichetta());
    }

    public function delete(Request $request, Cespite $cespite): Response
    {
        $soggetto = $cespite->soggetto;
        $this->caricaAzienda($soggetto);
        $this->verificaCsrf($request);
        $this->dataMapper->delete($cespite);
        $this->aggiungiMessaggio('Cespite eliminato: rielaborare gli ammortamenti degli esercizi aperti.');
        return $this->router->redirect('cespite/index/soggetto/' . $soggetto->id);
    }

    /**
     * Registra (o sostituisce) la scrittura degli ammortamenti dell'esercizio.
     */
    public function elabora(Request $request, Soggetto $soggetto, int $anno): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $this->verificaCsrf($request);
        $esercizio = $this->getEsercizio($soggetto, $anno);
        try {
            $avvisi = [];
            $registrazione = $esercizio === null ? null : (new Ammortamenti($this->dataMapper))->registra($soggetto, $esercizio, $avvisi);
            $this->aggiungiMessaggio($registrazione === null ? 'Nessuna quota di ammortamento nell\'esercizio.' : 'Ammortamenti registrati: registrazione n. ' . $registrazione->numero . ' del ' . $registrazione->dataRegistrazione->format('d/m/Y') . '.');
        } catch (\DomainException $errore) {
            $this->aggiungiMessaggio($errore->getMessage(), 'danger');
        }
        return $this->router->redirect('cespite/index/soggetto/' . $soggetto->id . '/anno/' . $anno);
    }

    private function gestisci(Request $request, Cespite $cespite, string $titolo): Response
    {
        $soggetto = $cespite->soggetto;
        $form = new CespiteForm($cespite);
        if ($this->isInvioValido($request, $form)) {
            $cespite = $form->resolveEntity();
            $doppio = array_filter((new CespiteModel($this->dataMapper))->getElenco($soggetto), fn(Cespite $altro) => ($altro->codice === $cespite->codice) && ($altro->id !== ($cespite->id ?? null)));
            if (count($doppio) > 0) {
                $this->aggiungiMessaggio('Esiste già il cespite ' . $cespite->codice . '.', 'danger');
            } else {
                $this->dataMapper->save($cespite);
                $this->aggiungiMessaggio('Cespite salvato: rielaborare gli ammortamenti degli esercizi aperti.');
                return $this->router->redirect('cespite/index/soggetto/' . $soggetto->id);
            }
        }
        $this->impostaVariabiliForm($form, $cespite);
        $this->getEsercizio($soggetto, 0);
        $conti = (new ContoModel($this->dataMapper))->getMovimentabili($this->piano);
        $scelte = ['immobilizzazioni' => [], 'fondi' => [], 'ammortamenti' => []];
        foreach ($conti as $conto) {
            $etichetta = $conto->codice . ' ' . $conto->descrizione;
            if (self::isImmobilizzazione($conto)) {
                $scelte['immobilizzazioni'][$conto->id] = $etichetta;
            } elseif (($conto->natura === NaturaConto::attivita) && str_starts_with(mb_strtolower($conto->descrizione), 'f.do')) {
                $scelte['fondi'][$conto->id] = $etichetta;
            } elseif ($conto->natura === NaturaConto::costo) {
                $scelte['ammortamenti'][$conto->id] = $etichetta;
            }
        }
        $this->vars['cespite'] = $cespite;
        $this->vars['scelteConti'] = $scelte;
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBriciole($soggetto, null, $titolo, ['Cespiti' => '/cespite/index/soggetto/' . $soggetto->id]);
        return $this->render->generateView('cespite/form', $this->vars, $form->getResponseType());
    }

    /**
     * Conto delle immobilizzazioni immateriali o materiali (voci B.I e B.II
     * dell'attivo), esclusi i fondi ammortamento.
     */
    private static function isImmobilizzazione(\Contabilita\Application\Entities\Conto $conto): bool
    {
        $voce = $conto->voceCee?->value ?? '';
        return ($conto->natura === NaturaConto::attivita) && (str_starts_with($voce, 'A.B.I.') || str_starts_with($voce, 'A.B.II.'))
                && (str_starts_with(mb_strtolower($conto->descrizione), 'f.do') === false);
    }

    /**
     * Fatture di acquisto dell'esercizio con righe sulle immobilizzazioni
     * non collegate a un cespite.
     *
     * @return array<int, array{registrazione: RegistrazioneContabile, importo: float}>
     */
    private function getAcquistiSenzaCespite(Soggetto $soggetto, \Contabilita\Application\Entities\EsercizioContabile $esercizio, array $cespiti): array
    {
        $collegate = [];
        foreach ($cespiti as $voce) {
            if ($voce['cespite']->registrazioneAcquisto !== null) {
                $collegate[$voce['cespite']->registrazioneAcquisto->id] = true;
            }
        }
        $acquisti = [];
        foreach ((new RigaContabileModel($this->dataMapper))->getMovimenti($soggetto, null, $esercizio->dataInizio, $esercizio->dataFine) as $riga) {
            $registrazione = $riga->registrazioneContabile;
            if (($registrazione->causale !== CausaleContabile::fatturaAcquisto) || isset($collegate[$registrazione->id]) || ($riga->dare <= 0) || (self::isImmobilizzazione($riga->conto) === false)) {
                continue;
            }
            $acquisti[$registrazione->id] ??= ['registrazione' => $registrazione, 'importo' => 0.0];
            $acquisti[$registrazione->id]['importo'] = round($acquisti[$registrazione->id]['importo'] + $riga->dare, 2);
        }
        return array_values($acquisti);
    }
}
