<?php

namespace Dichiarativi\Application\Services\Redditi;

use Anagrafica\Application\Entities\Soggetto;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Models\CreditoModel;
use Deleghe\Application\Models\VersamentoModel;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Quadro RU, sezione I (crediti d'imposta delle imprese) dei modelli
 * Redditi PF, SP e SC: un modulo per credito, con il codice credito della
 * tabella ufficiale e i codici tributo F24 per la compensazione
 * (Database/sorgenti/crediti-RU-<anno modello>.csv: codici e descrizioni
 * della tabella dei crediti delle istruzioni generali, codici tributo dalle
 * istruzioni di ciascun credito nei modelli SC, SP e PF).
 *
 * Dagli archivi di Deleghe, per i crediti con un codice tributo:
 * - credito spettante nell'anno (RU5, col. 3): crediti in archivio con il
 *   codice tributo e l'anno di riferimento della dichiarazione;
 * - credito utilizzato in compensazione (RU6): righe a credito delle deleghe
 *   (non in bozza) con il codice tributo e la data di versamento nell'anno;
 * - credito residuo della dichiarazione precedente (RU2): rigo RU12 della
 *   dichiarazione dell'anno prima nella suite per lo stesso codice o, il
 *   primo anno, crediti in archivio degli anni precedenti meno gli utilizzi
 *   prima dell'anno;
 * - credito residuo (RU12, col. 2) con la formula dei controlli delle
 *   specifiche (RU2 + RU3 + RU5 col. 3 + RU8 meno gli utilizzi dei righi
 *   RU6, RU7, RU9, RU10 e RU11).
 * Gli utilizzi in dichiarazione (RU7), le cessioni e i crediti senza codice
 * tributo restano a mano; un modulo compilato a mano con un codice credito
 * è aggiornato con le proposte.
 */
class CreditiImpostaRu
{

    public const CARTELLA = __DIR__ . '/../../../Database/sorgenti/';

    /** Campi sommati (+) e sottratti (-) nel residuo del rigo RU12, col. 2 */
    private const AUMENTI = ['RU002001', 'RU003001', 'RU005003', 'RU008001'];
    private const DIMINUZIONI = ['RU006001', 'RU007001', 'RU007002', 'RU007003', 'RU007004', 'RU007005', 'RU007006', 'RU007007', 'RU009001', 'RU009002', 'RU009003', 'RU010001', 'RU011001'];

    /** @var array<string, array<string, array{descrizione: string, tributi: string[]}>> per file */
    private static array $tabelle = [];

    /** @var string[] */
    private array $avvisi = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper(), private string $cartella = self::CARTELLA)
    {

    }

    /**
     * Tabella dei crediti per codice credito.
     *
     * @return array<string, array{descrizione: string, tributi: string[]}>
     */
    public function getTabella(int $annoModello): array
    {
        $file = $this->cartella . 'crediti-RU-' . $annoModello . '.csv';
        if (isset(self::$tabelle[$file])) {
            return self::$tabelle[$file];
        }
        $tabella = [];
        if (is_file($file) && (($gestore = fopen($file, 'r')) !== false)) {
            if (fgetcsv($gestore, separator: ';', escape: '') !== ['codice', 'descrizione', 'codici_tributo']) {
                fclose($gestore);
                throw new \UnexpectedValueException(basename($file) . ': intestazione non valida, attesa codice;descrizione;codici_tributo');
            }
            while (($riga = fgetcsv($gestore, separator: ';', escape: '')) !== false) {
                if (preg_match('/^[0-9A-Z]{2}$/', (string) ($riga[0] ?? '')) !== 1) {
                    continue;
                }
                $tabella[$riga[0]] = ['descrizione' => (string) ($riga[1] ?? ''), 'tributi' => array_values(array_filter(explode('|', (string) ($riga[2] ?? ''))))];
            }
            fclose($gestore);
        }
        return self::$tabelle[$file] = $tabella;
    }

    /**
     * Codice credito per codice tributo F24.
     *
     * @return array<string, string>
     */
    public function getCreditiPerTributo(int $annoModello): array
    {
        $crediti = [];
        foreach ($this->getTabella($annoModello) as $codice => ['tributi' => $tributi]) {
            foreach ($tributi as $tributo) {
                $crediti[$tributo] = $codice;
            }
        }
        return $crediti;
    }

    /**
     * @return string[] avvisi
     */
    public function proponi(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione): array
    {
        $this->avvisi = [];
        $soggetto = $dichiarazione->soggetto;
        $anno = $dichiarazione->anno;
        $annoModello = $dichiarazione->getAnnoModello();
        $perTributo = $this->getCreditiPerTributo($annoModello);
        $dati = $this->getDatiDeleghe($soggetto, $anno, $perTributo);
        $precedenti = $this->getResiduiPrecedenti($dichiarazione);
        // moduli del quadro RU: valori proposti azzerati, codici indicati a mano mantenuti
        $moduli = [];
        foreach ($quadri->getModuli() as $modulo) {
            $quadri->usaModulo($modulo)->avviaProposta('RU');
            $codice = $quadri->get('RU001001', $modulo);
            if (($codice !== null) && ($codice !== '')) {
                $moduli[$codice] = $modulo;
            }
        }
        $codici = array_unique(array_merge(array_keys($precedenti ?? []), array_keys($dati['spettante']), array_keys($dati['utilizzato']), $precedenti === null ? array_keys($dati['residuoIniziale']) : []));
        sort($codici);
        foreach ($codici as $codice) {
            $modulo = $moduli[$codice] ?? $this->getModuloLibero($quadri, $moduli);
            $moduli[$codice] = $modulo;
            $quadri->usaModulo($modulo);
            $quadri->proponi('RU001001', $codice);
            $residuo = $precedenti !== null ? ($precedenti[$codice] ?? 0) : (int) round($dati['residuoIniziale'][$codice] ?? 0);
            $quadri->proponi('RU002001', $residuo ?: null);
            $quadri->proponi('RU005003', ((int) round($dati['spettante'][$codice] ?? 0)) ?: null);
            $quadri->proponi('RU006001', ((int) round($dati['utilizzato'][$codice] ?? 0)) ?: null);
        }
        // residuo di tutti i moduli con un codice credito, anche quelli compilati a mano
        foreach ($moduli as $codice => $modulo) {
            $quadri->usaModulo($modulo);
            $saldo = 0;
            foreach (self::AUMENTI as $campo) {
                $saldo += $quadri->haCampo($campo) ? $quadri->getImporto($campo) : 0;
            }
            foreach (self::DIMINUZIONI as $campo) {
                $saldo -= $quadri->haCampo($campo) ? $quadri->getImporto($campo) : 0;
            }
            $quadri->proponi('RU012002', $saldo > 0 ? $saldo : null);
            if ($saldo < 0) {
                $this->avvisi[] = sprintf('Quadro RU, credito %s: utilizzi (%s euro) oltre il credito disponibile; verifica le compensazioni in Deleghe.', $codice, number_format(-$saldo, 0, ',', '.'));
            }
            if (in_array($codice, ['06', '23', '76'], true)) {
                $this->avvisi[] = sprintf('Quadro RU, credito %s: per questo codice le istruzioni prevedono regole proprie per i righi RU1 (casella 2) e RU12: verifica il modulo.', $codice);
            }
        }
        $quadri->usaModulo(1);
        if (count($codici) > 0) {
            $this->avvisi[] = sprintf('Quadro RU: crediti d\'imposta %s dagli archivi di Deleghe (crediti in archivio con il codice tributo e compensazioni nelle deleghe dell\'anno); utilizzi in dichiarazione (RU7) e cessioni a mano.', implode(', ', $codici));
        }
        return $this->avvisi;
    }

    /**
     * Residui del rigo RU12, col. 2, della dichiarazione dell'anno prima
     * nella suite per codice credito; null se non c'è.
     *
     * @return array<string, int>|null
     */
    private function getResiduiPrecedenti(Dichiarazione $dichiarazione): ?array
    {
        $precedente = (new DichiarazioneModel($this->dataMapper))->getPerModello($dichiarazione->soggetto, $dichiarazione->modello, $dichiarazione->anno - 1);
        if ($precedente === null) {
            return null;
        }
        $quadri = new QuadriDichiarazione($precedente, $this->dataMapper);
        $residui = [];
        foreach ($quadri->getModuli() as $modulo) {
            $codice = $quadri->get('RU001001', $modulo);
            $residuo = $quadri->getImporto('RU012002', $modulo);
            if (($codice !== null) && ($residuo > 0)) {
                $residui[$codice] = $residuo;
            }
        }
        return $residui;
    }

    /**
     * Crediti in archivio e compensazioni delle deleghe per codice credito.
     *
     * @param array<string, string> $perTributo
     * @return array{spettante: array<string, float>, residuoIniziale: array<string, float>, utilizzato: array<string, float>}
     */
    private function getDatiDeleghe(Soggetto $soggetto, int $anno, array $perTributo): array
    {
        $dati = ['spettante' => [], 'residuoIniziale' => [], 'utilizzato' => []];
        foreach ((new CreditoModel($this->dataMapper))->getElenco($soggetto) as $credito) {
            $codice = $perTributo[$credito->codiceTributo?->codice ?? ''] ?? null;
            if (($codice === null) || ($credito->annoRiferimento === null) || ($credito->annoRiferimento > $anno)) {
                continue;
            }
            $chiave = $credito->annoRiferimento === $anno ? 'spettante' : 'residuoIniziale';
            $dati[$chiave][$codice] = ($dati[$chiave][$codice] ?? 0.0) + $credito->importo;
        }
        $fine = new SismaDate($anno . '-12-31');
        foreach ((new VersamentoModel($this->dataMapper))->getPerPeriodo($soggetto->studio, new SismaDate('2000-01-01'), $fine, $soggetto) as $versamento) {
            if (($versamento->importoCredito <= 0) || ($versamento->delega === null) || ($versamento->delega->stato === StatoDelega::bozza)) {
                continue;
            }
            $tributo = (string) $versamento->codiceTributo?->codice;
            $codice = $perTributo[$tributo] ?? null;
            $nellAnno = (int) $versamento->scadenza->format('Y') === $anno;
            if ($codice === null) {
                continue;
            }
            if ($nellAnno) {
                $dati['utilizzato'][$codice] = ($dati['utilizzato'][$codice] ?? 0.0) + $versamento->importoCredito;
            } else {
                $dati['residuoIniziale'][$codice] = ($dati['residuoIniziale'][$codice] ?? 0.0) - $versamento->importoCredito;
            }
        }
        $dati['residuoIniziale'] = array_filter($dati['residuoIniziale'], fn(float $importo) => $importo > 0);
        return $dati;
    }

    /**
     * Primo modulo senza un codice credito nel quadro RU.
     *
     * @param array<string, int> $usati
     */
    private function getModuloLibero(QuadriDichiarazione $quadri, array $usati): int
    {
        $modulo = 1;
        while (in_array($modulo, $usati, true) || ($quadri->get('RU001001', $modulo) !== null)) {
            $modulo++;
        }
        return $modulo;
    }
}
