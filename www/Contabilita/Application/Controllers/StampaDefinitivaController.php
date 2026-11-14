<?php

namespace Contabilita\Application\Controllers;

use Contabilita\Application\Models\CespiteModel;
use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Entities\RegistroIva;
use Contabilita\Application\Entities\StampaDefinitiva;
use Contabilita\Application\Enumerations\TipoStampaDefinitiva;
use Contabilita\Application\Models\RegistroIvaModel;
use Contabilita\Application\Models\StampaDefinitivaModel;
use Contabilita\Application\Services\StampaDefinitiva\StampeDefinitive;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Stampe definitive con le pagine numerate del libro giornale
 * (dell'esercizio) e dei registri IVA (dell'anno solare): elenco per
 * libro, anteprima, registrazione della stampa, ristampa e annullamento
 * dell'ultima.
 *
 * Il libro si indica con "giornale" o con l'id del registro IVA.
 */
class StampaDefinitivaController extends ContabilitaController
{

    public function index(Soggetto $soggetto, int $anno = 0): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $anno = $anno > 0 ? $anno : (int) date('Y');
        $esercizio = $this->getEsercizio($soggetto, $anno);
        $servizio = new StampeDefinitive($this->dataMapper);
        $model = new StampaDefinitivaModel($this->dataMapper);
        $libri = [];
        if (($esercizio !== null) && ($esercizio->anno === $anno)) {
            $libri['giornale'] = $this->libro($servizio, $model, $soggetto, TipoStampaDefinitiva::giornale, null, $anno, 'Libro giornale');
            if (count((new CespiteModel($this->dataMapper))->getElenco($soggetto)) > 0) {
                $libri['beni'] = $this->libro($servizio, $model, $soggetto, TipoStampaDefinitiva::registroBeni, null, $anno, TipoStampaDefinitiva::registroBeni->getLabel());
                // il registro si stampa per l'intero esercizio
                $libri['beni']['proposta'] = $libri['beni']['fine']->format('Y-m-d');
            }
        }
        foreach ((new RegistroIvaModel($this->dataMapper))->getElenco($soggetto) as $registro) {
            $libri[(string) $registro->id] = $this->libro($servizio, $model, $soggetto, TipoStampaDefinitiva::registroIva, $registro, $anno, $registro->getEtichetta());
        }
        $this->vars['annoStampe'] = $anno;
        $this->vars['libri'] = $libri;
        $this->vars['anniStampe'] = range((int) date('Y'), (int) date('Y') - 5);
        $this->vars['sezioneAzienda'] = 'stampeDefinitive';
        $this->vars['pageTitle'] = $soggetto->nominativo . ' · Stampe definitive ' . $anno;
        $this->impostaBriciole($soggetto, $esercizio?->anno, 'Stampe definitive ' . $anno);
        return $this->render->generateView('stampaDefinitiva/index', $this->vars);
    }

    /**
     * Pagine della prossima stampa del libro fino ad $al, senza registrarla.
     */
    public function anteprima(Soggetto $soggetto, int $anno, string $libro, string $al): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        try {
            [$tipo, $registro] = $this->getLibro($soggetto, $libro);
            $servizio = new StampeDefinitive($this->dataMapper);
            [$dal] = $servizio->getPeriodoSuccessivo($soggetto, $tipo, $registro, $anno);
            $ultima = (new StampaDefinitivaModel($this->dataMapper))->getUltima($soggetto, $tipo, $registro, $anno);
            $stampa = new StampaDefinitiva($this->dataMapper);
            $stampa->soggetto = $soggetto;
            $stampa->tipo = $tipo;
            $stampa->registroIva = $registro;
            $stampa->anno = $anno;
            $stampa->dal = $dal;
            $stampa->al = self::dataFine($al);
            $stampa->primaPagina = $ultima === null ? 1 : $ultima->ultimaPagina + 1;
            $stampa->ultimaPagina = $stampa->primaPagina;
            return $this->mostraPagine($stampa, true);
        } catch (\DomainException $errore) {
            $this->aggiungiMessaggio($errore->getMessage(), 'danger');
            return $this->vaiAlleStampe($soggetto, $anno);
        }
    }

    public function registra(Request $request, Soggetto $soggetto, int $anno, string $libro): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $this->verificaCsrf($request);
        try {
            [$tipo, $registro] = $this->getLibro($soggetto, $libro);
            $stampa = (new StampeDefinitive($this->dataMapper))->registra($soggetto, $tipo, $registro, $anno, self::dataFine((string) ($request->input['al'] ?? '')));
            $this->aggiungiMessaggio(sprintf('Stampa definitiva registrata: %s, pagine %s. Stamparla ora e conservarla.', $stampa->getDescrizione(), $stampa->getPagine()));
        } catch (\DomainException $errore) {
            $this->aggiungiMessaggio($errore->getMessage(), 'danger');
        }
        return $this->vaiAlleStampe($soggetto, $anno);
    }

    /**
     * Ristampa di una stampa definitiva registrata, con le stesse pagine.
     */
    public function stampa(StampaDefinitiva $stampa): Response
    {
        $this->caricaAzienda($stampa->soggetto);
        return $this->mostraPagine($stampa, false);
    }

    public function annulla(Request $request, StampaDefinitiva $stampa): Response
    {
        $soggetto = $stampa->soggetto;
        $this->caricaAzienda($soggetto);
        $this->verificaCsrf($request);
        try {
            (new StampeDefinitive($this->dataMapper))->annulla($stampa);
            $this->aggiungiMessaggio('Stampa definitiva annullata (' . $stampa->getDescrizione() . '): le registrazioni del periodo tornano modificabili; distruggere le pagine stampate.');
        } catch (\DomainException $errore) {
            $this->aggiungiMessaggio($errore->getMessage(), 'danger');
        }
        return $this->vaiAlleStampe($soggetto, $stampa->anno);
    }

    /**
     * @return array<string, mixed>
     */
    private function libro(StampeDefinitive $servizio, StampaDefinitivaModel $model, Soggetto $soggetto, TipoStampaDefinitiva $tipo, ?RegistroIva $registro, int $anno, string $titolo): array
    {
        [$dal, $fine] = $servizio->getPeriodoSuccessivo($soggetto, $tipo, $registro, $anno);
        $ieri = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');
        // proposta: fine del mese precedente (o la fine del periodo, se già conclusa)
        $proposta = min($fine->format('Y-m-d'), (new \DateTimeImmutable('first day of this month'))->modify('-1 day')->format('Y-m-d'));
        return ['titolo' => $titolo, 'tipo' => $tipo, 'stampe' => $model->getStampe($soggetto, $tipo, $registro, $anno), 'dal' => $dal, 'fine' => $fine,
            'completo' => $dal->format('Y-m-d') > $fine->format('Y-m-d'), 'proposta' => max($proposta, $dal->format('Y-m-d')), 'massimo' => min($fine->format('Y-m-d'), $ieri)];
    }

    /**
     * @return array{0: TipoStampaDefinitiva, 1: ?RegistroIva}
     */
    private function getLibro(Soggetto $soggetto, string $libro): array
    {
        if ($libro === 'giornale') {
            return [TipoStampaDefinitiva::giornale, null];
        }
        if ($libro === 'beni') {
            return [TipoStampaDefinitiva::registroBeni, null];
        }
        foreach ((new RegistroIvaModel($this->dataMapper))->getElenco($soggetto) as $registro) {
            if ((string) $registro->id === $libro) {
                return [TipoStampaDefinitiva::registroIva, $registro];
            }
        }
        throw new \DomainException('Registro IVA non trovato.');
    }

    private function mostraPagine(StampaDefinitiva $stampa, bool $anteprima): Response
    {
        $this->vars['stampa'] = $stampa;
        $this->vars['pagine'] = (new StampeDefinitive($this->dataMapper))->getPagine($stampa);
        $this->vars['anteprima'] = $anteprima;
        $this->vars['titoloStampa'] = $stampa->tipo === TipoStampaDefinitiva::registroIva ? $stampa->registroIva->getEtichetta() : $stampa->tipo->getLabel();
        $this->vars['pageTitle'] = $stampa->soggetto->nominativo . ' · ' . $stampa->getDescrizione();
        return $this->render->generateView('stampaDefinitiva/pagine', $this->vars);
    }

    private static function dataFine(string $valore): SismaDate
    {
        $data = \DateTimeImmutable::createFromFormat('!Y-m-d', $valore);
        if ($data === false) {
            throw new \DomainException('Indicare la data di fine della stampa.');
        }
        return new SismaDate($data->format('Y-m-d'));
    }

    private function vaiAlleStampe(Soggetto $soggetto, int $anno): Response
    {
        return $this->router->redirect('stampa-definitiva/index/soggetto/' . $soggetto->id . '/anno/' . $anno);
    }
}
