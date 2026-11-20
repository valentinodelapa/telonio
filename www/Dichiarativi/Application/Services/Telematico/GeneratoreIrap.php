<?php

namespace Dichiarativi\Application\Services\Telematico;

use Anagrafica\Application\Models\DatiFiscaliModel;
use Contabilita\Application\Models\EsercizioContabileModel;
use Deleghe\Application\Services\DatiContribuente;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\FormatoCampo;
use Dichiarativi\Application\Enumerations\TipologiaDichiarazione;
use Dichiarativi\Application\Models\SottoscrizioneControlloModel;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Redditi\VersamentiRedditi;
use Dichiarativi\Application\Services\TabelleRiferimento\SchemaXsd;
use Dichiarativi\Application\Services\TabelleRiferimento\SpecificheDichiarativi;
use Dichiarativi\Application\Services\TabelleRiferimento\TabelleDichiarativi;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Fornitura telematica del modello IRAP ("IRA26", XML; specifiche tecniche
 * del provvedimento del 27 febbraio 2026): messaggio con l'intestazione
 * del fornitore e il contenuto, fornitura con il tipo di fornitore e la
 * dichiarazione (frontespizio e dati contabili), scritta nell'ordine e nei
 * namespace dello schema XSD ufficiale (sorgenti xsd-IRA-<anno>,
 * ScritturaXml).
 *
 * I dati contabili vengono dai valori dei quadri: ogni codice ha il suo
 * percorso XML (sorgente xml-IRA-<anno>.csv, LettoreSpecificheIrap); i
 * righi dei moduli successivi al primo vanno negli elementi "Modulo". Gli
 * importi zero non si scrivono (lo schema non li ammette).
 *
 * Frontespizio delle società: codice e nome della regione del domicilio
 * fiscale, partita IVA, denominazione, periodo d'imposta, stato e
 * situazione ordinari, natura giuridica, modello Redditi (1 quadro IP, 2
 * IC, 3 IE, 4 IK), sottoscrittore, caselle dei quadri compilati, firme
 * dell'organo di controllo, impegno alla trasmissione, visto.
 */
class GeneratoreIrap
{

    public const NAMESPACE_TELEMATICO = 'www.agenziaentrate.gov.it:specificheTecniche:telent:v1';
    public const PREFISSI = [
        self::NAMESPACE_TELEMATICO => 'tm',
        'urn:www.agenziaentrate.gov.it:specificheTecniche:common' => 'cm',
        'urn:www.agenziaentrate.gov.it:specificheTecniche:sco:common' => 'sc',
        SpecificheDichiarativi::NAMESPACE_IRAP => 'ir',
    ];

    /** Regioni e province autonome (tabella delle istruzioni IRAP e delle deleghe F24) */
    public const REGIONI = [1 => 'ABRUZZO', 2 => 'BASILICATA', 3 => 'BOLZANO', 4 => 'CALABRIA', 5 => 'CAMPANIA', 6 => 'EMILIA ROMAGNA', 7 => 'FRIULI VENEZIA GIULIA',
        8 => 'LAZIO', 9 => 'LIGURIA', 10 => 'LOMBARDIA', 11 => 'MARCHE', 12 => 'MOLISE', 13 => 'PIEMONTE', 14 => 'PUGLIA', 15 => 'SARDEGNA', 16 => 'SICILIA',
        17 => 'TOSCANA', 18 => 'TRENTO', 19 => 'UMBRIA', 20 => "VALLE D'AOSTA", 21 => 'VENETO'];

    /** Modello Redditi per quadro del valore della produzione */
    private const MODELLI_REDDITI = ['IP' => 1, 'IC' => 2, 'IE' => 3, 'IK' => 4];

    public function __construct(private DataMapper $dataMapper = new DataMapper(), private string $cartella = TabelleDichiarativi::CARTELLA)
    {

    }

    public static function getNomeFile(Dichiarazione $dichiarazione): string
    {
        return sprintf('%s_%s_%d.xml', $dichiarazione->modello->getCodiceFornitura($dichiarazione->anno), preg_replace('/\W+/', '', $dichiarazione->soggetto->codiceFiscale), $dichiarazione->anno);
    }

    /**
     * @throws \InvalidArgumentException se mancano dati obbligatori o un valore non ha un posto nello schema
     */
    public function genera(Dichiarazione $dichiarazione, ?QuadriDichiarazione $quadri = null): string
    {
        $quadri ??= new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $annoModello = $dichiarazione->getAnnoModello();
        $schema = new SchemaXsd($this->cartella . 'xsd-IRA-' . $annoModello);
        $fornitura = $schema->getElementoGlobale(SpecificheDichiarativi::NAMESPACE_IRAP, 'Fornitura') ?? throw new \RuntimeException('Elemento Fornitura non trovato nello schema IRAP');
        $datiContabili = $this->getDatiContabili($quadri, $annoModello);
        $valori = [
            'Intestazione' => ['TipoFornitore' => $dichiarazione->intermediario === null ? '1' : '10'],
            'Dichiarazione' => [[
                '@identificativo' => '00001',
                'Frontespizio' => $this->getFrontespizio($dichiarazione, $quadri),
                'DatiContabili' => $datiContabili,
            ]],
        ];
        $documento = new \DOMDocument('1.0', 'UTF-8');
        $documento->formatOutput = true;
        $tm = self::NAMESPACE_TELEMATICO;
        $messaggio = $documento->createElementNS($tm, 'tm:Messaggio');
        foreach (self::PREFISSI as $namespace => $prefisso) {
            $messaggio->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . $prefisso, $namespace);
        }
        $documento->appendChild($messaggio);
        $intestazione = $messaggio->appendChild($documento->createElementNS($tm, 'tm:Intestazione'));
        $intestazione->appendChild($documento->createElementNS($tm, 'tm:CodiceFiscaleFornitore', strtoupper((string) ($dichiarazione->intermediario?->soggetto->codiceFiscale ?? $dichiarazione->soggetto->codiceFiscale))));
        $contenuto = $messaggio->appendChild($documento->createElementNS($tm, 'tm:Contenuto'));
        $contenuto->setAttribute('CodiceFornitura', $dichiarazione->modello->getCodiceFornitura($dichiarazione->anno));
        (new ScritturaXml($schema, self::PREFISSI))->scrivi($contenuto, $fornitura, $valori);
        return $documento->saveXML();
    }

    /**
     * Dati contabili dai valori dei quadri, con il percorso XML di ogni
     * codice: primo modulo nell'elemento "PrimoModulo", gli altri negli
     * elementi "Modulo" (uno per modulo successivo).
     *
     * @return array<string, mixed>
     */
    private function getDatiContabili(QuadriDichiarazione $quadri, int $annoModello): array
    {
        $percorsi = $this->getPercorsi($annoModello);
        $albero = [];
        foreach ($quadri->getValori() as $modulo => $campi) {
            foreach ($campi as $codice => $dati) {
                $valore = self::formatta($quadri->getCampo($codice)->formato, $dati['valore']);
                if ($valore === null) {
                    continue;
                }
                $percorso = $percorsi[$codice] ?? throw new \InvalidArgumentException('Campo ' . $codice . ' senza posto nello schema XML IRAP: toglierlo dalla dichiarazione.');
                if ($modulo > 1) {
                    if (!str_contains($percorso, '/PrimoModulo/') && !str_contains($percorso, '/Modulo[]/')) {
                        continue;
                    }
                    $percorso = str_replace(['/PrimoModulo/', '/Modulo[]/'], '/Modulo[' . ($modulo - 1) . ']/', $percorso);
                } elseif (str_contains($percorso, '/Modulo[]/')) {
                    $percorso = str_replace('/Modulo[]/', '/Modulo[1]/', $percorso);
                }
                self::imposta($albero, array_slice(explode('/', $percorso), 1), $valore);
            }
        }
        return $albero;
    }

    /**
     * @param string[] $parti
     */
    private static function imposta(array &$albero, array $parti, string $valore): void
    {
        $parte = array_shift($parti);
        if (preg_match('/^(.+)\[(\d+)\]$/', $parte, $occorrenza) === 1) {
            $nome = $occorrenza[1];
            $indice = (int) $occorrenza[2] - 1;
            $albero[$nome] ??= [];
            for ($i = 0; $i <= $indice; $i++) {
                $albero[$nome][$i] ??= [];
            }
            ksort($albero[$nome]);
            if (count($parti) === 0) {
                $albero[$nome][$indice] = $valore;
                return;
            }
            self::imposta($albero[$nome][$indice], $parti, $valore);
            return;
        }
        if (count($parti) === 0) {
            $albero[$parte] = $valore;
            return;
        }
        $albero[$parte] ??= [];
        self::imposta($albero[$parte], $parti, $valore);
    }

    /**
     * @return array<string, string> codice => percorso
     */
    private function getPercorsi(int $annoModello): array
    {
        $file = @fopen($this->cartella . 'xml-IRA-' . $annoModello . '.csv', 'r') ?: throw new \RuntimeException('Percorsi XML del modello IRAP ' . $annoModello . ' non trovati: eseguire "sisma specifiche-dichiarativi --modello=IRA".');
        $percorsi = [];
        fgetcsv($file, separator: ';', escape: '');
        while (($riga = fgetcsv($file, separator: ';', escape: '')) !== false) {
            if (count($riga) >= 2) {
                $percorsi[$riga[0]] = $riga[1];
            }
        }
        fclose($file);
        return $percorsi;
    }

    /**
     * Valore XML del campo: importi interi senza gli zeri, percentuali con
     * la virgola, caselle "1", date ggmmaaaa, testo in maiuscolo; null se
     * non si scrive.
     */
    public static function formatta(?FormatoCampo $formato, ?string $valore): ?string
    {
        if (($valore === null) || ($valore === '')) {
            return null;
        }
        return match ($formato) {
            FormatoCampo::positivo, FormatoCampo::numerico, FormatoCampo::cifre1, FormatoCampo::cifre2, FormatoCampo::cifre3, FormatoCampo::cifre4, FormatoCampo::anno
                => ((int) $valore === 0) && ($formato !== FormatoCampo::cifre2) ? null : (string) (int) $valore,
            FormatoCampo::casella => ((int) $valore === 0) ? null : '1',
            FormatoCampo::percentuale => str_replace('.', ',', rtrim(rtrim(number_format((float) $valore, 3, '.', ''), '0'), '.')),
            FormatoCampo::data => (new \DateTimeImmutable($valore))->format('dmY'),
            default => mb_strtoupper($valore),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function getFrontespizio(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri): array
    {
        $soggetto = $dichiarazione->soggetto;
        $anno = $dichiarazione->anno;
        $regione = VersamentiRedditi::getCodiceRegioneDomicilio($this->dataMapper, $soggetto, new \DateTimeImmutable($anno . '-12-31'))
                ?? throw new \InvalidArgumentException('Domicilio fiscale (sede legale) non trovato nell\'Anagrafica: serve per la regione del frontespizio IRAP.');
        $recapiti = (new Frontespizio($this->dataMapper))->getRecapiti($soggetto);
        $datiFiscali = (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($soggetto, new SismaDate($anno . '-12-31'));
        $esercizio = (new EsercizioContabileModel($this->dataMapper))->getPerAnno($soggetto, $anno);
        $compilati = [];
        foreach ($quadri->getModuli() as $modulo) {
            array_push($compilati, ...$quadri->getQuadriCompilati($modulo));
        }
        $compilati = array_values(array_unique($compilati));
        $modelloRedditi = null;
        foreach (self::MODELLI_REDDITI as $quadro => $codice) {
            if (in_array($quadro, $compilati, true)) {
                $modelloRedditi = $codice;
            }
        }
        $frontespizio = [
            'SoggettoFiscale' => ['CodiceFiscale' => strtoupper($soggetto->codiceFiscale), 'Email' => $recapiti['email'] === null ? null : strtoupper($recapiti['email']), 'Telefono' => $recapiti['telefono']],
            'CodiceRegione' => (string) $regione,
            'Regione' => self::REGIONI[$regione],
            'TipoDichiarazione' => $dichiarazione->tipologia === TipologiaDichiarazione::correttiva ? ['Correttiva' => '1'] : null,
            'PartitaIVA' => $datiFiscali?->partitaIva,
            'SoggettiDiversiDaPF' => [
                'Denominazione' => self::testo($soggetto->nominativo),
                'InizioPeriodoImposta' => ($esercizio?->dataInizio ?? new SismaDate($anno . '-01-01'))->format('dmY'),
                'FinePeriodoImposta' => ($esercizio?->dataFine ?? new SismaDate($anno . '-12-31'))->format('dmY'),
                'Stato' => '1',
                'NaturaGiuridica' => $dichiarazione->naturaGiuridica === null ? null : (string) $dichiarazione->naturaGiuridica,
                'Situazione' => '6',
            ],
            'DichiarazioneRedditi' => $modelloRedditi === null ? null : (string) $modelloRedditi,
        ];
        $rappresentante = $dichiarazione->dichiarante;
        if (($rappresentante !== null) && $rappresentante->isPersonaFisica()) {
            [$comune, $provincia] = DatiContribuente::getLuogoNascita($rappresentante);
            $frontespizio['DatiSottoscrittore'] = [
                'CodiceFiscale' => strtoupper($rappresentante->codiceFiscale),
                'CodiceCarica' => $dichiarazione->codiceCarica?->value,
                'DatiAnagrafici' => ['Cognome' => self::testo($rappresentante->cognome), 'Nome' => self::testo($rappresentante->nome), 'Sesso' => $rappresentante->sesso?->value,
                    'DataNascita' => $rappresentante->dataNascita?->format('dmY'), 'ComuneNascita' => self::testo($comune), 'ProvinciaNascita' => in_array($provincia, ['EE', '', null], true) ? null : $provincia],
            ];
        }
        $firma = [];
        foreach (['IP', 'IC', 'IE', 'IK', 'IR', 'IS'] as $quadro) {
            $firma['Casella' . $quadro] = in_array($quadro, $compilati, true) ? '1' : null;
        }
        $firma['FirmaDichiarante'] = '1';
        if (isset($dichiarazione->id)) {
            foreach (array_slice(iterator_to_array((new SottoscrizioneControlloModel($this->dataMapper))->getSottoscrizioni($dichiarazione), false), 0, 5) as $sottoscrizione) {
                $firma['FirmaRevisore'][] = ['Soggetto' => (string) $sottoscrizione->tipoSoggetto, 'CodiceFiscale' => strtoupper((string) $sottoscrizione->soggetto->codiceFiscale),
                    'FirmaDichiarante' => $sottoscrizione->isFirmata() ? '1' : null];
            }
        }
        $frontespizio['FirmaDichiarazione'] = $firma;
        if ($dichiarazione->intermediario !== null) {
            $frontespizio['ImpegnoATrasmettere'] = ['CFintermediario' => strtoupper($dichiarazione->intermediario->soggetto->codiceFiscale),
                'ImpegnoATrasmettere' => (string) ($dichiarazione->impegno?->value ?? 1), 'DataImpegno' => ($dichiarazione->dataImpegno ?? new SismaDate())->format('dmY'), 'FirmaIntermediario' => '1'];
        }
        if ($dichiarazione->vistoProfessionista !== null) {
            $frontespizio['VistoConformita'] = ['CFprofessionista' => strtoupper($dichiarazione->vistoProfessionista->codiceFiscale), 'Firma' => '1'];
        } elseif ($dichiarazione->vistoCaf !== null) {
            $frontespizio['VistoConformita'] = ['VistoCAF' => ['CFResp' => strtoupper((string) $dichiarazione->vistoResponsabileCaf?->codiceFiscale), 'CF_CAF' => $dichiarazione->vistoCaf->codiceFiscale], 'Firma' => '1'];
        }
        $frontespizio['FlagConferma'] = $dichiarazione->flagConferma ? '1' : null;
        $frontespizio['IdentificativoProdSoftware'] = 'TELONIO';
        return $frontespizio;
    }

    private static function testo(?string $testo): ?string
    {
        return $testo === null ? null : mb_strtoupper(trim($testo));
    }
}
