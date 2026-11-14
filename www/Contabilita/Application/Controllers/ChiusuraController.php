<?php

namespace Contabilita\Application\Controllers;

use Contabilita\Application\Services\DestinazioneRisultato;
use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Entities\RateoRisconto;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Forms\RateoRiscontoForm;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\RegistrazioneContabileModel;
use Contabilita\Application\Services\Ammortamenti;
use Contabilita\Application\Services\ChiusuraEsercizio;
use Contabilita\Application\Services\RateiRisconti;
use Deleghe\Application\HelperClasses\Importo;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Fine dell'esercizio: scritture di assestamento (ammortamenti dai
 * cespiti, ratei e risconti) e chiusura e riapertura dei conti.
 */
class ChiusuraController extends ContabilitaController
{

    public function index(Soggetto $soggetto, int $anno = 0): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $esercizio = $this->getEsercizio($soggetto, $anno);
        if ($esercizio === null) {
            $this->aggiungiMessaggio('Aprire prima un esercizio.', 'warning');
            return $this->vaiAllAzienda($soggetto);
        }
        $registrazioni = new RegistrazioneContabileModel($this->dataMapper);
        $ammortamenti = (new Ammortamenti($this->dataMapper))->calcola($soggetto, $esercizio);
        $chiusura = new ChiusuraEsercizio($this->dataMapper);
        $saldi = $chiusura->getSaldi($soggetto, $esercizio);
        $this->vars['totaleAmmortamenti'] = round(array_sum(array_column($ammortamenti, 'quota')), 2);
        $this->vars['scritturaAmmortamenti'] = $registrazioni->getGenerata($soggetto, Ammortamenti::ORIGINE, Ammortamenti::getRiferimento($esercizio->anno));
        $this->vars['ratei'] = (new RateiRisconti($this->dataMapper))->calcola($soggetto, $esercizio);
        $this->vars['scritturaRatei'] = $registrazioni->getGenerata($soggetto, RateiRisconti::ORIGINE, RateiRisconti::getRiferimento($esercizio->anno));
        $this->vars['scritturaStorno'] = $registrazioni->getGenerata($soggetto, RateiRisconti::ORIGINE, RateiRisconti::getRiferimento($esercizio->anno, true));
        $this->vars['risultato'] = ChiusuraEsercizio::getRisultato($saldi);
        $this->vars['scrittureChiusura'] = $chiusura->getScritture($soggetto, $esercizio->anno);
        $this->vars['successivo'] = (new RateiRisconti($this->dataMapper))->getSuccessivo($soggetto, $esercizio);
        $this->vars['scritturaDestinazione'] = (new DestinazioneRisultato($this->dataMapper))->getRegistrata($soggetto, $esercizio->anno);
        $this->vars['sezioneAzienda'] = 'chiusura';
        $this->vars['pageTitle'] = $soggetto->nominativo . ' · Chiusura ' . $esercizio->getEtichetta();
        $this->impostaBriciole($soggetto, $esercizio->anno, 'Chiusura dell\'esercizio');
        return $this->render->generateView('chiusura/index', $this->vars);
    }

    public function ratei(Request $request, Soggetto $soggetto, int $anno): Response
    {
        return $this->elabora($request, $soggetto, $anno, function ($esercizio, array &$avvisi) use ($soggetto): string {
            (new RateiRisconti($this->dataMapper))->registra($soggetto, $esercizio, $avvisi);
            return 'Ratei e risconti registrati (assestamento e storno nell\'esercizio successivo).';
        });
    }

    public function chiudi(Request $request, Soggetto $soggetto, int $anno): Response
    {
        return $this->elabora($request, $soggetto, $anno, function ($esercizio, array &$avvisi) use ($soggetto): string {
            $risultato = (new ChiusuraEsercizio($this->dataMapper))->genera($soggetto, $esercizio, $avvisi);
            return 'Epilogo, chiusura e riapertura dei conti registrati: ' . ($risultato >= 0 ? 'utile' : 'perdita') . ' di € ' . Importo::euro(abs($risultato)) . '.';
        });
    }

    private function elabora(Request $request, Soggetto $soggetto, int $anno, callable $azione): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $this->verificaCsrf($request);
        $esercizio = $this->getEsercizio($soggetto, $anno);
        try {
            $avvisi = [];
            $this->aggiungiMessaggio($azione($esercizio, $avvisi));
            foreach ($avvisi as $avviso) {
                $this->aggiungiMessaggio($avviso, 'warning');
            }
        } catch (\DomainException $errore) {
            $this->aggiungiMessaggio($errore->getMessage(), 'danger');
        }
        return $this->router->redirect('chiusura/index/soggetto/' . $soggetto->id . '/anno/' . $anno);
    }

    /**
     * Destinazione del risultato dell'esercizio con la scrittura
     * nell'esercizio successivo.
     */
    public function destinazione(Request $request, Soggetto $soggetto, int $anno): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $esercizio = $this->getEsercizio($soggetto, $anno);
        $servizio = new DestinazioneRisultato($this->dataMapper);
        $proposta = $servizio->proponi($soggetto, $esercizio);
        $successivo = (new RateiRisconti($this->dataMapper))->getSuccessivo($soggetto, $esercizio);
        // delibera entro 120 giorni dalla chiusura (art. 2364, c. 2, c.c.)
        $data = (new \DateTimeImmutable($esercizio->dataFine->format('Y-m-d')))->modify('+1 day')->modify('+4 months')->modify('-1 day')->format('Y-m-d');
        $righe = $proposta['righe'];
        $conti = (new ContoModel($this->dataMapper))->getMovimentabili($this->piano);
        $perId = [];
        foreach ($conti as $conto) {
            if ($conto->natura->isPatrimoniale()) {
                $perId[$conto->id] = $conto;
            }
        }
        if (($request->input['submitted'] ?? null) !== null) {
            $this->verificaCsrf($request);
            $data = (string) ($request->input['data'] ?? $data);
            $righe = [];
            foreach ((array) ($request->input['righe'] ?? []) as $riga) {
                $conto = $perId[(int) ($riga['conto'] ?? 0)] ?? null;
                $importo = (float) Importo::normalizza((string) ($riga['importo'] ?? ''));
                if (($conto !== null) && ($importo != 0)) {
                    $righe[] = ['conto' => $conto, 'importo' => $importo];
                }
            }
            try {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $data) ?: throw new \DomainException('Data non valida.');
                $registrazione = $servizio->registra($soggetto, $esercizio, $date, $righe);
                $this->aggiungiMessaggio('Destinazione del risultato registrata: registrazione n. ' . $registrazione?->numero . ' del ' . $date->format('d/m/Y') . '.');
                return $this->router->redirect('chiusura/index/soggetto/' . $soggetto->id . '/anno/' . $anno);
            } catch (\DomainException $errore) {
                $this->aggiungiMessaggio($errore->getMessage(), 'danger');
            }
        }
        $this->vars['risultato'] = $proposta['risultato'];
        $this->vars['righeDestinazione'] = array_pad($righe, 4, ['conto' => null, 'importo' => 0.0]);
        $this->vars['scelteConti'] = array_map(fn($conto) => $conto->codice . ' ' . $conto->descrizione, $perId);
        $this->vars['dataDestinazione'] = $data;
        $this->vars['successivo'] = $successivo;
        $this->vars['pageTitle'] = 'Destinazione del risultato ' . $esercizio->getEtichetta();
        $this->impostaBriciole($soggetto, $anno, $this->vars['pageTitle'], ['Chiusura dell\'esercizio' => '/chiusura/index/soggetto/' . $soggetto->id . '/anno/' . $anno]);
        return $this->render->generateView('chiusura/destinazione', $this->vars);
    }

    public function create(Request $request, Soggetto $soggetto, int $anno): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $voce = new RateoRisconto($this->dataMapper);
        $voce->studio = $soggetto->studio;
        $voce->soggetto = $soggetto;
        $voce->anno = $anno;
        $voce->registratoNellEsercizio = true;
        return $this->gestisci($request, $voce, 'Nuovo rateo o risconto');
    }

    public function update(Request $request, RateoRisconto $voce): Response
    {
        $this->caricaAzienda($voce->soggetto);
        return $this->gestisci($request, $voce, $voce->descrizione);
    }

    public function delete(Request $request, RateoRisconto $voce): Response
    {
        $soggetto = $voce->soggetto;
        $this->caricaAzienda($soggetto);
        $this->verificaCsrf($request);
        $this->dataMapper->delete($voce);
        $this->aggiungiMessaggio('Voce eliminata: rielaborare i ratei e risconti.');
        return $this->router->redirect('chiusura/index/soggetto/' . $soggetto->id . '/anno/' . $voce->anno);
    }

    private function gestisci(Request $request, RateoRisconto $voce, string $titolo): Response
    {
        $soggetto = $voce->soggetto;
        $form = new RateoRiscontoForm($voce);
        if ($this->isInvioValido($request, $form)) {
            $voce = $form->resolveEntity();
            $this->dataMapper->save($voce);
            $this->aggiungiMessaggio('Voce salvata: rielaborare i ratei e risconti.');
            return $this->router->redirect('chiusura/index/soggetto/' . $soggetto->id . '/anno/' . $voce->anno);
        }
        $this->impostaVariabiliForm($form, $voce);
        $this->getEsercizio($soggetto, $voce->anno);
        $scelte = [];
        foreach ((new ContoModel($this->dataMapper))->getMovimentabili($this->piano) as $conto) {
            if (in_array($conto->natura, [NaturaConto::costo, NaturaConto::ricavo], true)) {
                $scelte[$conto->id] = $conto->codice . ' ' . $conto->descrizione;
            }
        }
        $this->vars['voce'] = $voce;
        $this->vars['scelteConti'] = $scelte;
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBriciole($soggetto, $voce->anno, $titolo, ['Chiusura dell\'esercizio' => '/chiusura/index/soggetto/' . $soggetto->id . '/anno/' . $voce->anno]);
        return $this->render->generateView('chiusura/form', $this->vars, $form->getResponseType());
    }
}
