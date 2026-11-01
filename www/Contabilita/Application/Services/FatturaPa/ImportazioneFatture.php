<?php

namespace Contabilita\Application\Services\FatturaPa;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Models\NazioneModel;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Entities\FatturaElettronica;
use Contabilita\Application\Enumerations\DirezioneFattura;
use Contabilita\Application\Enumerations\EsigibilitaIva;
use Contabilita\Application\Enumerations\TipoDocumentoFattura;
use Contabilita\Application\Models\ControparteModel;
use Contabilita\Application\Models\FatturaElettronicaModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Importazione delle fatture elettroniche dell'azienda: file XML, firmati
 * .p7m o archivi ZIP che li contengono (come quelli scaricati dal portale
 * Fatture e corrispettivi, di cui si ignorano i file dei metadati). Per
 * ogni fattura del file:
 *
 * - la direzione si ricava confrontando cedente e cessionario con la
 *   partita IVA e il codice fiscale dell'azienda (le fatture che non la
 *   riguardano sono scartate);
 * - la controparte si cerca per partita IVA o codice fiscale tra quelle
 *   dell'azienda ed è creata se manca;
 * - l'impronta (SHA-256 del documento e posizione nel lotto) impedisce di
 *   importare due volte la stessa fattura.
 */
class ImportazioneFatture
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public function importa(Soggetto $soggetto, string $nomeFile, string $contenuto): EsitoImportazione
    {
        $esito = new EsitoImportazione();
        if (str_ends_with(strtolower($nomeFile), '.zip')) {
            foreach ($this->leggiArchivio($contenuto, $esito) as $nome => $file) {
                $this->importaFile($soggetto, $nome, $file, $esito);
            }
            return $esito;
        }
        $this->importaFile($soggetto, $nomeFile, $contenuto, $esito);
        return $esito;
    }

    /**
     * @return array<string, string> file delle fatture per nome
     */
    private function leggiArchivio(string $contenuto, EsitoImportazione $esito): array
    {
        if (class_exists(\ZipArchive::class) === false) {
            $esito->errori[] = 'Archivi ZIP non supportati dall\'installazione (estensione zip di PHP assente): caricare i singoli file.';
            return [];
        }
        $temporaneo = tempnam(sys_get_temp_dir(), 'fatture');
        file_put_contents($temporaneo, $contenuto);
        $archivio = new \ZipArchive();
        $file = [];
        if ($archivio->open($temporaneo) !== true) {
            $esito->errori[] = 'Archivio ZIP non leggibile.';
        } else {
            for ($indice = 0; $indice < $archivio->numFiles; $indice++) {
                $nome = (string) $archivio->getNameIndex($indice);
                $minuscolo = strtolower($nome);
                if ((str_ends_with($minuscolo, '.xml') || str_ends_with($minuscolo, '.p7m')) && (str_contains($minuscolo, 'metadato') === false)) {
                    $file[basename($nome)] = (string) $archivio->getFromIndex($indice);
                }
            }
            $archivio->close();
        }
        unlink($temporaneo);
        return $file;
    }

    private function importaFile(Soggetto $soggetto, string $nomeFile, string $contenuto, EsitoImportazione $esito): void
    {
        try {
            $xml = (new EstrazioneP7m())->estrai($contenuto);
            $documento = (new LetturaFatturaPa())->leggi($xml);
        } catch (\UnexpectedValueException $errore) {
            $esito->errori[] = $nomeFile . ': ' . $errore->getMessage();
            return;
        }
        $direzione = $this->getDirezione($soggetto, $documento);
        if ($direzione === null) {
            $esito->errori[] = $nomeFile . ': la fattura non riguarda l\'azienda (né cedente né cessionario hanno la sua partita IVA o il suo codice fiscale).';
            return;
        }
        $fatturaModel = new FatturaElettronicaModel($this->dataMapper);
        $controparteFattura = $direzione === DirezioneFattura::emessa ? $documento->cessionario : $documento->cedente;
        $controparte = null;
        foreach ($documento->corpi as $corpo) {
            $impronta = hash('sha256', $xml . '#' . $corpo->indice);
            $etichetta = $nomeFile . (count($documento->corpi) > 1 ? ' (documento ' . $corpo->indice . ')' : '');
            if ($fatturaModel->esisteImpronta($soggetto, $impronta)) {
                $esito->doppie[] = $etichetta;
                continue;
            }
            $fattura = new FatturaElettronica($this->dataMapper);
            $fattura->studio = $soggetto->studio;
            $fattura->soggetto = $soggetto;
            $fattura->direzione = $direzione;
            $fattura->nomeFile = mb_substr($nomeFile, 0, 255);
            $fattura->impronta = $impronta;
            $fattura->indiceCorpo = $corpo->indice;
            $fattura->tipoDocumento = $corpo->tipoDocumento;
            $fattura->numero = $corpo->numero;
            $fattura->data = new SismaDate($corpo->data->format('Y-m-d'));
            $fattura->denominazioneControparte = mb_substr($controparteFattura->getDenominazione(), 0, 255);
            $fattura->partitaIvaControparte = $controparteFattura->partitaIva;
            $fattura->codiceFiscaleControparte = $controparteFattura->codiceFiscale;
            $fattura->codicePaese = $controparteFattura->codicePaese;
            $fattura->importoTotale = $corpo->importoTotale ?? $corpo->getTotale();
            $controparte ??= $this->getControparte($soggetto, $controparteFattura, $direzione, $esito);
            $fattura->controparte = $controparte;
            $fattura->contenuto = $xml;
            $avvisi = self::avvisi($corpo, $direzione);
            $fattura->avvisi = count($avvisi) > 0 ? implode("\n", $avvisi) : null;
            $this->dataMapper->save($fattura);
            $esito->fatture[] = $fattura;
        }
    }

    private function getDirezione(Soggetto $soggetto, DocumentoFatturaPa $documento): ?DirezioneFattura
    {
        return match (true) {
            $documento->cedente->corrisponde($soggetto->partitaIva, $soggetto->codiceFiscale) => DirezioneFattura::emessa,
            $documento->cessionario->corrisponde($soggetto->partitaIva, $soggetto->codiceFiscale) => DirezioneFattura::ricevuta,
            default => null,
        };
    }

    private function getControparte(Soggetto $soggetto, SoggettoFattura $dati, DirezioneFattura $direzione, EsitoImportazione $esito): Controparte
    {
        $controparte = (new ControparteModel($this->dataMapper))->trova($soggetto, $dati->partitaIva, $dati->codiceFiscale);
        $cliente = $direzione === DirezioneFattura::emessa;
        if ($controparte !== null) {
            if (($cliente && ($controparte->cliente === false)) || (($cliente === false) && ($controparte->fornitore === false))) {
                $controparte->cliente = $controparte->cliente || $cliente;
                $controparte->fornitore = $controparte->fornitore || ($cliente === false);
                $this->dataMapper->save($controparte);
            }
            return $controparte;
        }
        $controparte = new Controparte($this->dataMapper);
        $controparte->studio = $soggetto->studio;
        $controparte->soggetto = $soggetto;
        $controparte->denominazione = mb_substr($dati->getDenominazione(), 0, 255);
        $controparte->cognome = $dati->cognome === null ? null : mb_substr($dati->cognome, 0, 60);
        $controparte->nome = $dati->nome === null ? null : mb_substr($dati->nome, 0, 60);
        $controparte->partitaIva = $dati->partitaIva === null ? null : mb_substr($dati->partitaIva, 0, 28);
        $controparte->codiceFiscale = $dati->codiceFiscale === null ? null : mb_substr($dati->codiceFiscale, 0, 16);
        $paese = $dati->codicePaese ?? $dati->nazione;
        if (($paese !== null) && ($paese !== 'IT')) {
            $controparte->nazione = (new NazioneModel($this->dataMapper))->getByCodiceIso($paese)[0] ?? null;
        }
        $controparte->indirizzo = $dati->indirizzo === null ? null : mb_substr(trim($dati->indirizzo . ' ' . ($dati->numeroCivico ?? '')), 0, 100);
        $controparte->cap = $dati->cap === null ? null : mb_substr($dati->cap, 0, 10);
        $controparte->comune = $dati->comune === null ? null : mb_substr($dati->comune, 0, 60);
        $controparte->provincia = ($dati->provincia === null) || (strlen($dati->provincia) !== 2) ? null : strtoupper($dati->provincia);
        $controparte->cliente = $cliente;
        $controparte->fornitore = $cliente === false;
        $this->dataMapper->save($controparte);
        $esito->nuoveControparti[] = $controparte->denominazione;
        return $controparte;
    }

    /**
     * @return string[]
     */
    private static function avvisi(CorpoFattura $corpo, DirezioneFattura $direzione): array
    {
        $avvisi = [];
        if ($corpo->divisa !== 'EUR') {
            $avvisi[] = 'Documento in ' . $corpo->divisa . ': gli importi vanno convertiti in euro.';
        }
        if ($corpo->tipoDocumento === TipoDocumentoFattura::integrazioneInversioneInterna) {
            $avvisi[] = 'Integrazione di una fattura in inversione contabile (TD16): se la fattura del fornitore è già registrata con l\'integrazione dell\'imposta, scartare questo documento.';
        }
        if ($corpo->getEsigibilita() === EsigibilitaIva::differita) {
            $avvisi[] = 'IVA a esigibilità differita: entra nella liquidazione all\'incasso o al pagamento.';
        }
        if (($corpo->importoTotale !== null) && (abs($corpo->importoTotale - $corpo->getTotale()) > 0.01)) {
            $avvisi[] = sprintf('Il totale del documento (%s) differisce da imponibili e imposte dei riepiloghi (%s): ritenute, bolli o altre voci.',
                    number_format($corpo->importoTotale, 2, ',', '.'), number_format($corpo->getTotale(), 2, ',', '.'));
        }
        if (($direzione === DirezioneFattura::ricevuta) && $corpo->tipoDocumento->isIntegrazione()) {
            $avvisi[] = 'Documento di integrazione emesso dall\'azienda come cessionario: si registra negli acquisti con l\'imposta anche a debito.';
        }
        return $avvisi;
    }
}
