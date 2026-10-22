<?php

namespace Imu\Application\Services\TabelleRiferimento;

use Anagrafica\Application\Models\ComuneModel;
use Imu\Application\Enumerations\MotivoEsenzioneTerreni;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Righe delle tabelle di riferimento del modulo:
 * - coefficienti dei fabbricati del gruppo D a valore contabile
 *   (Database/sorgenti/coefficienti-fabbricati-d.csv, dai decreti annuali
 *   del MEF pubblicati dal Dipartimento delle Finanze o in Gazzetta);
 * - comuni con terreni esenti: circolare n. 9/1993 dalla Gazzetta Ufficiale,
 *   abbinata ai codici catastali dell'archivio comuni ANPR (versioni in
 *   vigore alla data della circolare) con gli abbinamenti curati
 *   (sorgenti/abbinamenti-circolare-9-1993.csv) per i nomi scritti
 *   diversamente; isole minori (sorgenti/isole-minori.csv, comuni delle isole
 *   dell'allegato A alla L. 448/2001).
 *
 * Ogni anomalia (anno incompleto, nome non abbinato, codice inesistente)
 * ferma l'importazione.
 */
class TabelleImu
{

    public const CARTELLA = __DIR__ . '/../../../Database/sorgenti/';
    public const COLONNE_COEFFICIENTI = ['anno_imposta', 'anno_costo', 'coefficiente', 'fonte'];
    public const COLONNE_ESENZIONI = ['codice_catastale', 'motivo', 'parziale', 'denominazione', 'provincia', 'fonte'];
    private const DATA_CIRCOLARE = '1993-06-14';
    private const FONTE_CIRCOLARE = 'Circolare MF n. 9 del 14/06/1993 (G.U. n. 141 del 18/06/1993, S.O. n. 53)';
    private const PRIMO_ANNO_COSTO = 1982;
    /** Province della circolare il cui nome non è quello del capoluogo */
    private const SIGLE_PROVINCE = ['PESARO' => 'PS', 'MASSA CARRARA' => 'MS', "FORLI'" => 'FO', 'REGGIO CALABRIA' => 'RC', 'REGGIO EMILIA' => 'RE', 'AQUILA' => 'AQ'];

    /** @var string[] */
    private array $avvisi = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public function getAvvisi(): array
    {
        return $this->avvisi;
    }

    /**
     * @return array<array<string, string|int|null>>
     */
    public function leggiCoefficienti(): array
    {
        $righe = self::leggiCsv('coefficienti-fabbricati-d.csv', self::COLONNE_COEFFICIENTI);
        $perAnno = [];
        foreach ($righe as &$riga) {
            if ((ctype_digit($riga['anno_imposta']) === false) || (ctype_digit($riga['anno_costo']) === false) || (is_numeric($riga['coefficiente']) === false)) {
                throw new \UnexpectedValueException('coefficienti-fabbricati-d.csv: riga non valida ' . implode(';', $riga));
            }
            $riga['anno_imposta'] = (int) $riga['anno_imposta'];
            $riga['anno_costo'] = (int) $riga['anno_costo'];
            $perAnno[$riga['anno_imposta']][$riga['anno_costo']] = true;
        }
        unset($riga);
        foreach ($perAnno as $anno => $anni) {
            $mancanti = array_diff(range(self::PRIMO_ANNO_COSTO, $anno), array_keys($anni));
            if (count($mancanti) > 0) {
                throw new \UnexpectedValueException('coefficienti-fabbricati-d.csv: per il ' . $anno . ' mancano gli anni di costo ' . implode(', ', $mancanti));
            }
        }
        return $righe;
    }

    /**
     * @param array<int, array{provincia: string, comune: string, parziale: bool}> $circolare
     * @return array<array<string, string|int|null>>
     */
    public function leggiEsenzioni(array $circolare): array
    {
        [$versioni1993, $tutte, $codici] = $this->getComuni();
        $abbinamenti = [];
        foreach (self::leggiCsv('abbinamenti-circolare-9-1993.csv', ['provincia', 'comune_circolare', 'codice_catastale', 'nota']) as $abbinamento) {
            $abbinamenti[$abbinamento['provincia'] . '|' . $abbinamento['comune_circolare']] = $abbinamento;
        }
        $sigle = [];
        $righe = [];
        $nonAbbinati = [];
        foreach ($circolare as ['provincia' => $provincia, 'comune' => $nome, 'parziale' => $parziale]) {
            $sigle[$provincia] ??= self::SIGLE_PROVINCE[$provincia] ?? ($versioni1993[self::normalizza($provincia)][0]['sigla'] ?? null);
            $abbinamento = $abbinamenti[$provincia . '|' . $nome] ?? null;
            if ($abbinamento !== null) {
                if ($abbinamento['codice_catastale'] === null) {
                    $this->avvisi[] = sprintf('Circolare 9/1993, %s (%s): %s', $nome, $provincia, $abbinamento['nota']);
                    continue;
                }
                $codice = $abbinamento['codice_catastale'];
            } else {
                $candidati = $this->getCandidati($versioni1993, $tutte, self::normalizza($nome), $sigle[$provincia]);
                if (count($candidati) !== 1) {
                    $nonAbbinati[] = $nome . ' (' . $provincia . ')';
                    continue;
                }
                $codice = reset($candidati);
            }
            if (isset($codici[$codice]) === false) {
                throw new \UnexpectedValueException('Codice catastale ' . $codice . ' non presente nell\'archivio comuni.');
            }
            if (isset($righe[$codice . '|M'])) {
                $this->avvisi[] = sprintf('Circolare 9/1993: %s (%s) e %s indicano lo stesso comune %s, si tiene la delimitazione parziale se una delle due lo è.', $nome, $provincia, $righe[$codice . '|M']['denominazione'], $codice);
                $parziale = $parziale || ($righe[$codice . '|M']['parziale'] === 1);
            }
            $righe[$codice . '|M'] = ['codice_catastale' => $codice, 'motivo' => MotivoEsenzioneTerreni::montano->value, 'parziale' => $parziale ? 1 : 0, 'denominazione' => $nome, 'provincia' => $provincia, 'fonte' => self::FONTE_CIRCOLARE];
        }
        if (count($nonAbbinati) > 0) {
            throw new \UnexpectedValueException('Comuni della circolare 9/1993 non abbinati all\'archivio comuni (aggiungerli in abbinamenti-circolare-9-1993.csv): ' . implode(', ', $nonAbbinati));
        }
        foreach (self::leggiCsv('isole-minori.csv', ['codice_catastale', 'comune', 'delimitazione', 'fonte']) as $isola) {
            if ((isset($codici[$isola['codice_catastale']]) === false) || (in_array($isola['delimitazione'], ['T', 'P'], true) === false)) {
                throw new \UnexpectedValueException('isole-minori.csv: riga non valida ' . implode(';', $isola));
            }
            $righe[$isola['codice_catastale'] . '|I'] = ['codice_catastale' => $isola['codice_catastale'], 'motivo' => MotivoEsenzioneTerreni::isolaMinore->value, 'parziale' => $isola['delimitazione'] === 'P' ? 1 : 0,
                'denominazione' => $isola['comune'], 'provincia' => null, 'fonte' => $isola['fonte']];
        }
        return array_values($righe);
    }

    /**
     * Codici dei comuni con il nome indicato: versione in vigore alla data
     * della circolare nella provincia indicata; in mancanza una versione di
     * qualsiasi data in quella provincia (comuni passati alle province
     * istituite dal 1992, come Lecco, Biella o Rimini, che nella circolare
     * figurano nella provincia di origine); infine l'unico comune con quel
     * nome nel 1993.
     *
     * @return string[]
     */
    private function getCandidati(array $versioni1993, array $tutte, string $nome, ?string $sigla): array
    {
        $filtra = fn(array $versioni) => array_values(array_unique(array_column(array_filter($versioni, fn(array $versione) => $versione['sigla'] === $sigla), 'codice')));
        $candidati = $filtra($versioni1993[$nome] ?? []);
        if (count($candidati) === 0) {
            $candidati = $filtra($tutte[$nome] ?? []);
        }
        if (count($candidati) === 0) {
            $candidati = array_values(array_unique(array_column($versioni1993[$nome] ?? [], 'codice')));
        }
        return $candidati;
    }

    /**
     * Versioni dei comuni in vigore alla data della circolare, per nome
     * normalizzato (con la sigla della provincia), e codici esistenti.
     *
     * @return array{0: array<string, array<int, array{codice: string, sigla: ?string}>>, 1: array<string, array<int, array{codice: string, sigla: ?string}>>, 2: array<string, true>}
     */
    private function getComuni(): array
    {
        $versioni = $tutte = $codici = [];
        foreach ((new ComuneModel($this->dataMapper))->getEntityCollection() as $comune) {
            $codici[$comune->codiceCatastale] = true;
            $tutte[self::normalizza($comune->denominazione)][] = ['codice' => $comune->codiceCatastale, 'sigla' => $comune->siglaProvincia];
            if (($comune->validoDal->format('Y-m-d') <= self::DATA_CIRCOLARE) && (($comune->validoAl === null) || ($comune->validoAl->format('Y-m-d') >= self::DATA_CIRCOLARE))) {
                $versioni[self::normalizza($comune->denominazione)][] = ['codice' => $comune->codiceCatastale, 'sigla' => $comune->siglaProvincia];
            }
        }
        return [$versioni, $tutte, $codici];
    }

    private static function normalizza(string $nome): string
    {
        $nome = strtr(mb_strtoupper($nome), ['À' => 'A', 'Á' => 'A', 'È' => 'E', 'É' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Ò' => 'O', 'Ó' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Ä' => 'A', 'Ö' => 'O', 'Ü' => 'U', 'Ë' => 'E', 'Ï' => 'I', 'Ç' => 'C', 'Â' => 'A', 'Ê' => 'E', 'Î' => 'I', 'Ô' => 'O', 'Û' => 'U', 'Š' => 'S', 'Ž' => 'Z']);
        return preg_replace('/[^A-Z0-9]/', '', $nome);
    }

    /**
     * @param string[] $colonne
     * @return array<array<string, ?string>>
     */
    private static function leggiCsv(string $nome, array $colonne): array
    {
        $file = fopen(self::CARTELLA . $nome, 'r') ?: throw new \RuntimeException('Impossibile aprire ' . $nome);
        $intestazione = fgetcsv($file, separator: ';', escape: '');
        if ($intestazione !== $colonne) {
            throw new \UnexpectedValueException($nome . ': intestazione non valida');
        }
        $righe = [];
        while (($valori = fgetcsv($file, separator: ';', escape: '')) !== false) {
            if ($valori === [null]) {
                continue;
            }
            $righe[] = array_combine($colonne, array_map(fn(?string $valore) => ($valore === null) || (trim($valore) === '') ? null : trim($valore), array_pad($valori, count($colonne), null)));
        }
        fclose($file);
        return $righe;
    }
}
