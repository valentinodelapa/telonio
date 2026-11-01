<?php

namespace Contabilita\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Entities\FatturaElettronica;
use Contabilita\Application\Enumerations\DirezioneFattura;
use Contabilita\Application\Enumerations\StatoFatturaElettronica;
use Contabilita\Application\Models\FatturaElettronicaModel;
use Contabilita\Application\Services\FatturaPa\ImportazioneFatture;
use Contabilita\Application\Services\FatturaPa\LetturaFatturaPa;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Fatture elettroniche dell'azienda: caricamento dei file (XML, .p7m,
 * ZIP), elenco per stato e direzione, dettaglio, registrazione in prima
 * nota (RegistrazioneController::fattura), scarto e download del file.
 */
class FatturaElettronicaController extends ContabilitaController
{

    private const RISULTATI_PER_PAGINA = 30;
    public const DIMENSIONE_MASSIMA = 20 * 1024 * 1024;

    public function index(Soggetto $soggetto, string $stato = '', string $direzione = '', string $testo = '', int $pagina = 1): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $filtroStato = StatoFatturaElettronica::tryFrom($stato);
        $filtroDirezione = DirezioneFattura::tryFrom($direzione);
        $model = new FatturaElettronicaModel($this->dataMapper);
        $totale = $model->conta($soggetto, $filtroStato, $filtroDirezione, $testo);
        $pagine = max(1, (int) ceil($totale / self::RISULTATI_PER_PAGINA));
        $pagina = min(max(1, $pagina), $pagine);
        $this->vars['fatture'] = iterator_to_array($model->cerca($soggetto, $filtroStato, $filtroDirezione, $testo, ($pagina - 1) * self::RISULTATI_PER_PAGINA, self::RISULTATI_PER_PAGINA), false);
        $this->vars['totale'] = $totale;
        $this->vars['pagina'] = $pagina;
        $this->vars['pagine'] = $pagine;
        $this->vars['filtroStato'] = $filtroStato;
        $this->vars['filtroDirezione'] = $filtroDirezione;
        $this->vars['testo'] = $testo;
        $this->vars['stati'] = StatoFatturaElettronica::cases();
        $this->vars['direzioni'] = DirezioneFattura::cases();
        $this->getEsercizio($soggetto, 0);
        $this->vars['sezioneAzienda'] = 'fatture';
        $this->vars['pageTitle'] = $soggetto->nominativo . ' · Fatture elettroniche';
        $this->impostaBriciole($soggetto, null, 'Fatture elettroniche');
        return $this->render->generateView('fatturaElettronica/index', $this->vars);
    }

    /**
     * Caricamento di uno o più file.
     */
    public function carica(Request $request, Soggetto $soggetto): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $this->verificaCsrf($request);
        $importazione = new ImportazioneFatture($this->dataMapper);
        $importate = $doppie = 0;
        $controparti = [];
        foreach (self::fileCaricati($request->files['file'] ?? null) as $file) {
            if (($file['error'] !== UPLOAD_ERR_OK) || (is_uploaded_file($file['tmp_name']) === false) || ($file['size'] > self::DIMENSIONE_MASSIMA)) {
                $this->aggiungiMessaggio(basename((string) $file['name']) . ': file non caricato (massimo 20 MB).', 'danger');
                continue;
            }
            $primaTransazione = $this->dataMapper->startTransaction();
            $esito = $importazione->importa($soggetto, basename((string) $file['name']), (string) file_get_contents($file['tmp_name']));
            $this->dataMapper->commitTransaction($primaTransazione);
            $importate += count($esito->fatture);
            $doppie += count($esito->doppie);
            array_push($controparti, ...$esito->nuoveControparti);
            foreach ($esito->errori as $errore) {
                $this->aggiungiMessaggio($errore, 'danger');
            }
            foreach (array_unique($esito->nonConformi) as $nome) {
                $this->aggiungiMessaggio($nome . ': file non conforme allo schema della fattura elettronica, importato con avviso.', 'warning');
            }
        }
        $this->aggiungiMessaggio(sprintf('Fatture importate: %d%s%s.', $importate, $doppie > 0 ? sprintf(' (%d già presenti, ignorate)', $doppie) : '',
                        count($controparti) > 0 ? '; nuovi clienti o fornitori: ' . implode(', ', array_unique($controparti)) : ''), $importate > 0 ? 'success' : 'warning');
        return $this->router->redirect('fattura-elettronica/index/soggetto/' . $soggetto->id . '/stato/' . StatoFatturaElettronica::daRegistrare->value);
    }

    public function show(FatturaElettronica $fattura): Response
    {
        $soggetto = $fattura->soggetto;
        $this->caricaAzienda($soggetto);
        $this->getEsercizio($soggetto, 0);
        try {
            $documento = (new LetturaFatturaPa())->leggi($fattura->contenuto);
            $this->vars['documento'] = $documento;
            $this->vars['corpo'] = $documento->getCorpo($fattura->indiceCorpo);
        } catch (\UnexpectedValueException $errore) {
            $this->vars['documento'] = $this->vars['corpo'] = null;
            $this->aggiungiMessaggio($errore->getMessage(), 'danger');
        }
        $this->vars['fattura'] = $fattura;
        $this->vars['sezioneAzienda'] = 'fatture';
        $this->vars['pageTitle'] = $fattura->tipoDocumento->getLabel() . ' n. ' . $fattura->numero;
        $this->impostaBriciole($soggetto, null, $this->vars['pageTitle'], ['Fatture elettroniche' => '/fattura-elettronica/index/soggetto/' . $soggetto->id]);
        return $this->render->generateView('fatturaElettronica/show', $this->vars);
    }

    /**
     * Scarta la fattura (non da registrare) o la ripristina.
     */
    public function scarta(Request $request, FatturaElettronica $fattura, bool $ripristina = false): Response
    {
        $this->caricaAzienda($fattura->soggetto);
        $this->verificaCsrf($request);
        if ($fattura->stato === StatoFatturaElettronica::registrata) {
            $this->aggiungiMessaggio('La fattura è registrata: eliminare prima la registrazione.', 'warning');
        } else {
            $fattura->stato = $ripristina ? StatoFatturaElettronica::daRegistrare : StatoFatturaElettronica::scartata;
            $this->dataMapper->save($fattura);
            $this->aggiungiMessaggio($ripristina ? 'Fattura di nuovo da registrare.' : 'Fattura scartata.');
        }
        return $this->router->redirect('fattura-elettronica/show/fattura/' . $fattura->id);
    }

    public function delete(Request $request, FatturaElettronica $fattura): Response
    {
        $soggetto = $fattura->soggetto;
        $this->caricaAzienda($soggetto);
        $this->verificaCsrf($request);
        if ($fattura->stato === StatoFatturaElettronica::registrata) {
            $this->aggiungiMessaggio('La fattura è registrata: eliminare prima la registrazione.', 'warning');
            return $this->router->redirect('fattura-elettronica/show/fattura/' . $fattura->id);
        }
        $this->dataMapper->delete($fattura);
        $this->aggiungiMessaggio('Fattura eliminata: si può importare di nuovo.');
        return $this->router->redirect('fattura-elettronica/index/soggetto/' . $soggetto->id);
    }

    /**
     * Download del documento XML.
     */
    public function scarica(FatturaElettronica $fattura): Response
    {
        $this->caricaAzienda($fattura->soggetto);
        $this->vars['contenuto'] = $fattura->contenuto;
        $this->vars['nomeFile'] = preg_replace('/\.p7m$/i', '', $fattura->nomeFile);
        return $this->render->generateData('fatturaElettronica/scarica', $this->vars);
    }

    /**
     * File di un campo di upload, singolo o multiplo (name="file[]").
     *
     * @return array<int, array{name: string, tmp_name: string, error: int, size: int}>
     */
    public static function fileCaricati(mixed $campo): array
    {
        if (is_array($campo) === false) {
            return [];
        }
        if (is_array($campo['name'] ?? null) === false) {
            return isset($campo['tmp_name']) ? [$campo] : [];
        }
        $file = [];
        foreach (array_keys($campo['name']) as $indice) {
            $file[] = ['name' => $campo['name'][$indice], 'tmp_name' => $campo['tmp_name'][$indice] ?? '', 'error' => $campo['error'][$indice] ?? UPLOAD_ERR_NO_FILE, 'size' => $campo['size'][$indice] ?? 0];
        }
        return $file;
    }
}
