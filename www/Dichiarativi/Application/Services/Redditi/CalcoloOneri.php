<?php

namespace Dichiarativi\Application\Services\Redditi;

use Dichiarativi\Application\Services\QuadriDichiarazione;

/**
 * Quadro RP, sezioni I e II, e rigo RN13 secondo le specifiche tecniche del
 * modello Redditi PF 2026 (par. 10, 11, 12, 23.1 e controlli del record C
 * dei righi RP15 e RP39):
 *
 * - totali del rigo RP15 per percentuale di detrazione dai righi da RP1 a
 *   RP14 di tutti i moduli: franchigia di 129 euro sulle spese sanitarie
 *   (anche rateizzate in quattro anni), spese detraibili commisurate al
 *   reddito (codici 11, 47, 56 entro i redditi dei terreni; 15 fino a 40.000
 *   euro di reddito; 24 e 28 entro il 30 per cento e 27 entro il 2 per cento
 *   del reddito), franchigia delle spese veterinarie (codice 29);
 * - rigo RN23 (patologie esenti dei familiari non a carico, rigo RP1 col. 1);
 * - righi RP32 col. 4, RP33 col. 3, RP34 col. 3, RP36 col. 3 e totale degli
 *   oneri deducibili del rigo RP39 (codici 7, 8 e 12 del rigo RP26 entro il
 *   2, 10 e 20 per cento del reddito);
 * - rigo RN13: oltre 75.000 euro di reddito di riferimento il riordino delle
 *   detrazioni (art. 16-ter Tuir): limite di spesa da importo base (14.000 o
 *   8.000 euro) e coefficiente per i figli del prospetto dei familiari,
 *   imputato agli oneri dalla percentuale più alta (imputazione automatica,
 *   salvo la casella "Riordino non automatizzato"), e oltre 120.000 euro il
 *   coefficiente di abbattimento degli oneri commisurati al reddito.
 *
 * I limiti degli importi che le specifiche pongono come controlli bloccanti
 * (per rigo, per codice, per gruppi di codici) non correggono gli importi
 * indicati: sono segnalati negli avvisi per la correzione del rigo.
 */
class CalcoloOneri
{

    public const FRANCHIGIA = 129;

    /** Codici dei righi da RP8 a RP13 per colonna del rigo RP15 (importi presi come indicati) */
    private const COLONNA_2 = [8, 9, 10, 20, 22, 32, 33, 45, 46, 55, 98, 99];
    private const COLONNA_3 = [12, 13, 14, 16, 17, 18, 21, 25, 26, 29, 30, 31, 35, 36, 38, 39, 40, 43, 44, 51, 52, 53, 54];
    private const ALTRE_COLONNE = [62 => 4, 61 => 5, 71 => 6, 76 => 7, 81 => 8];

    /** Limiti per rigo e per codice (controlli bloccanti) */
    private const LIMITI_RIGO = [12 => 1000, 14 => 1550, 16 => 210, 33 => 632, 45 => 1000];
    private const LIMITI_CODICE = [9 => 2582, 15 => 2100, 17 => 1000, 18 => 2633, 21 => 1500, 22 => 1300, 29 => 550, 40 => 250, 61 => 30000, 62 => 30000, 71 => 30000, 76 => 30000];

    /** Oneri soggetti al riordino (par. 10.5) per entità */
    private const RIORDINO_19_NO = [20, 22, 32, 33, 55, 98];
    private const RIORDINO_19_SI = [12, 13, 14, 16, 17, 18, 21, 25, 26, 29, 30, 31, 35, 40, 44, 51, 52, 53, 54];
    /** Oneri al 19% commisurati al reddito e non soggetti al riordino */
    private const COMMISURATI_19 = [36, 38, 39, 43];
    /** Oneri al 19% non commisurati e non soggetti al riordino (oltre ai righi da RP1 a RP7 e ai codici 11, 15, 47) */
    private const NON_COMMISURATI_19 = [8, 9, 10, 45, 46, 99];

    /** Ordine dell'imputazione automatica (tabella di supporto, entità del rigo RN13) */
    private const ORDINE_RIORDINO = ['90si', '35', '30', '26no', '26si', '19no', '19si'];

    /** @var string[] */
    private array $avvisi = [];

    /** @var array<string, float> entità per il rigo RN13 */
    private array $entita = [];

    /**
     * Totali delle sezioni I e II nel modulo 1 (già in proposta) e rigo RN23.
     *
     * @param float $redditoRiferimento rigo RN1 col. 1 (con RS37 col. 14)
     * @param float $terreni redditi dominicali e agrari (RA23 col. 11 e 12)
     */
    public function proponiTotali(QuadriDichiarazione $quadri, float $redditoRiferimento, float $terreni): void
    {
        $this->avvisi = [];
        $this->entita = array_fill_keys(['nonCommisurate19', 'commisurate19', '19no', '19si', '26no', '26si', '30', '35', '90si'], 0.0);
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        $tutti = fn(string $codice) => array_sum(array_map(fn(int $modulo) => $quadri->getImporto($codice, $modulo), $quadri->getModuli() ?: [1]));

        // righi da RP1 a RP7
        $rateizzazione = $quadri->get('RP015001', 1) !== null;
        if ($rateizzazione) {
            $sanitarie = (max(0, $tutti('RP001001') + $tutti('RP001002') + $tutti('RP002001') - self::FRANCHIGIA) + $tutti('RP003001')) / 4;
        } else {
            $sanitarie = max(0, $tutti('RP001002') + $tutti('RP002001') - self::FRANCHIGIA) + $tutti('RP003001');
        }
        if ($tutti('RP002001') > 6197) {
            $this->avvisi[] = 'Quadro RP, rigo RP2: le spese per i familiari non a carico non possono superare 6.197 euro.';
        }
        $interessiAbitazione = 0;
        $interessi57 = 0;
        foreach ($quadri->getModuli() ?: [1] as $modulo) {
            $codice = $quadri->getImporto('RP007001', $modulo);
            $importo = $quadri->getImporto('RP007002', $modulo);
            $codice === 57 ? $interessi57 += $importo : $interessiAbitazione += $importo;
        }
        if ($interessiAbitazione + $interessi57 > 4000) {
            $this->avvisi[] = 'Quadro RP, rigo RP7: gli interessi per l\'acquisto dell\'abitazione principale non possono superare 4.000 euro.';
        }
        $this->entita['nonCommisurate19'] += $sanitarie + $tutti('RP004002') + $tutti('RP005002') + $tutti('RP006002') + $interessiAbitazione;
        $this->entita['19no'] += $interessi57;

        // righi da RP8 a RP13
        $perCodice = [];
        foreach ($quadri->getModuli() ?: [1] as $modulo) {
            for ($rigo = 8; $rigo <= 13; $rigo++) {
                $importo = $quadri->getImporto(sprintf('RP%03d002', $rigo), $modulo);
                if ($importo === 0) {
                    continue;
                }
                $codice = $quadri->getImporto(sprintf('RP%03d001', $rigo), $modulo);
                if (isset(self::LIMITI_RIGO[$codice]) && ($importo > self::LIMITI_RIGO[$codice])) {
                    $this->avvisi[] = sprintf('Quadro RP, rigo RP%d%s: con il codice %d l\'importo di ciascun rigo non può superare %s euro.', $rigo, $modulo > 1 ? ' del modulo ' . $modulo : '', $codice, number_format(self::LIMITI_RIGO[$codice], 0, ',', '.'));
                }
                $perCodice[$codice] = ($perCodice[$codice] ?? 0) + $importo;
            }
        }
        $this->controllaCodici($perCodice, $interessiAbitazione + $interessi57);
        $codice = fn(int ...$codici) => array_sum(array_map(fn(int $c) => $perCodice[$c] ?? 0, $codici));

        $mutuiAgrari = min($codice(11, 47), $terreni);
        $interessi56 = min($codice(56), max(0, $terreni - $codice(11, 47)));
        $spesa15 = $redditoRiferimento <= 40000 ? min($codice(15), 2100) : 0;
        $spesa24 = min($codice(24), 0.3 * $redditoRiferimento);
        $spesa27 = min($codice(27), 0.02 * $redditoRiferimento);
        $spesa28 = min($codice(28), 0.3 * $redditoRiferimento);
        $veterinarie = max(0, $codice(29) - self::FRANCHIGIA);

        $leasing = $tutti('RP014003') + $tutti('RP014004');
        $colonna2 = $this->entita['nonCommisurate19'] + $interessi57 + $codice(...self::COLONNA_2) + $mutuiAgrari + $interessi56 + $spesa15 + $spesa24 + $spesa28;
        $colonna3 = $codice(...array_diff(self::COLONNA_3, [29])) + $veterinarie + $leasing + $spesa27;
        $totali = [2 => $colonna2, 3 => $colonna3];
        foreach (self::ALTRE_COLONNE as $c => $colonna) {
            $totali[$colonna] = $codice($c);
        }
        foreach ($totali as $colonna => $importo) {
            $quadri->proponi(sprintf('RP015%03d', $colonna), (int) round($importo) ?: null);
        }

        // entità per il rigo RN13
        $this->entita['nonCommisurate19'] += $codice(...self::NON_COMMISURATI_19) + $mutuiAgrari + $spesa15;
        $this->entita['commisurate19'] = $codice(...self::COMMISURATI_19) + $leasing;
        $this->entita['19no'] += $codice(...self::RIORDINO_19_NO) + $interessi56 + $spesa24 + $spesa28;
        $this->entita['19si'] = $codice(...array_diff(self::RIORDINO_19_SI, [29])) + $veterinarie + $spesa27;
        $this->entita['26no'] = $codice(62);
        $this->entita['26si'] = $codice(61);
        $this->entita['30'] = $codice(71);
        $this->entita['35'] = $codice(76);
        $this->entita['90si'] = $codice(81);

        // rigo RN23: patologie esenti dei familiari non a carico (senza rateizzazione)
        $patologie = $tutti('RP001001');
        if (($rateizzazione === false) && ($patologie > 0)) {
            $residuoFranchigia = max(0, self::FRANCHIGIA - ($tutti('RP001002') + $tutti('RP002001')));
            $quadri->proponi('RN023001', (int) round(max(0, $patologie - $residuoFranchigia) * 0.19) ?: null);
        } else {
            $quadri->proponi('RN023001', null);
        }

        $this->proponiDeducibili($quadri, $redditoRiferimento, $q, $tutti);
    }

    /**
     * Rigo RN13 (par. 23.1): fino a 75.000 euro di reddito di riferimento per
     * il riordino i totali del rigo RP15 per le percentuali; oltre, il limite
     * di spesa imputato dalla percentuale più alta.
     *
     * @param float $redditoRiordino RN1 col. 1 - RN2 (con RS37 col. 14)
     * @param int $figli figli del prospetto dei familiari (codici fiscali dei righi F, F1, D e affido preadottivo)
     */
    public function rn13(QuadriDichiarazione $quadri, float $redditoRiordino, int $figli, bool $disabile): int
    {
        if ($redditoRiordino <= 75000) {
            $q = fn(string $codice) => $quadri->getImporto($codice, 1);
            return (int) round($q('RP015002') * 0.19 + $q('RP015003') * 0.19 + $q('RP015004') * 0.26 + $q('RP015005') * 0.26
                    + $q('RP015006') * 0.30 + $q('RP015007') * 0.35 + $q('RP015008') * 0.90);
        }
        $limite = self::limiteRiordino($redditoRiordino, $figli, $disabile);
        $soggetti = array_sum(array_map(fn(string $entita) => $this->entita[$entita], self::ORDINE_RIORDINO));
        if ($quadri->get('RP000001', 1) !== null) {
            // riordino non automatizzato: il contribuente ha già ricondotto gli oneri nel limite
            if ($soggetti > $limite) {
                $this->avvisi[] = sprintf('Quadro RP: con la casella "Riordino delle detrazioni non automatizzato" gli oneri soggetti al riordino (%s euro) non possono superare il limite di %s euro (scarto della dichiarazione).',
                        number_format($soggetti, 0, ',', '.'), number_format($limite, 0, ',', '.'));
            }
            $imputati = $this->entita;
        } else {
            $imputati = $this->entita;
            $residuo = (float) $limite;
            foreach (self::ORDINE_RIORDINO as $entita) {
                $imputati[$entita] = min($this->entita[$entita], $residuo);
                $residuo -= $imputati[$entita];
            }
        }
        $abbattimento = self::coefficienteAbbattimento($redditoRiordino);
        return (int) round(($this->entita['commisurate19'] + $imputati['19si']) * 0.19 * $abbattimento
                + $this->entita['nonCommisurate19'] * 0.19 + $imputati['19no'] * 0.19
                + $imputati['26si'] * 0.26 * $abbattimento + $imputati['26no'] * 0.26
                + $imputati['30'] * 0.30 + $imputati['35'] * 0.35 + $imputati['90si'] * 0.90 * $abbattimento);
    }

    /**
     * Limite di spesa del riordino (par. 10.3): importo base per il
     * coefficiente dei figli, arrotondato all'euro.
     */
    public static function limiteRiordino(float $reddito, int $figli, bool $disabile): int
    {
        $base = $reddito <= 100000 ? 14000 : 8000;
        $coefficiente = match (true) {
            $disabile || ($figli > 2) => 1.0,
            $figli === 2 => 0.85,
            $figli === 1 => 0.70,
            default => 0.50,
        };
        return (int) round($base * $coefficiente);
    }

    /**
     * Coefficiente per gli oneri commisurati al reddito (art. 15, comma 3-bis,
     * Tuir): 1 fino a 120.000 euro, (240.000 - reddito) / 120.000 fino a
     * 240.000, zero oltre.
     */
    public static function coefficienteAbbattimento(float $reddito): float
    {
        if ($reddito <= 120000) {
            return 1.0;
        }
        return $reddito >= 240000 ? 0.0 : (240000 - $reddito) / 120000;
    }

    /**
     * @return string[]
     */
    public function getAvvisi(): array
    {
        return $this->avvisi;
    }

    /**
     * @return array<string, float>
     */
    public function getEntita(): array
    {
        return $this->entita;
    }

    /**
     * Righi RP32 col. 4, RP33 col. 3, RP34 col. 3, RP36 col. 3 e RP39.
     */
    private function proponiDeducibili(QuadriDichiarazione $quadri, float $redditoRiferimento, \Closure $q, \Closure $tutti): void
    {
        $spese32 = min(300000, $tutti('RP032002'));
        $deduzione32 = $spese32 > 0 || $tutti('RP032003') > 0 ? 0.2 * $spese32 / 8 + 0.2 * $tutti('RP032003') : 0;
        $quadri->proponi('RP032004', (int) round($deduzione32) ?: null);
        $restituzioni = $q('RP033001') + $q('RP033002');
        $quadri->proponi('RP033003', $restituzioni ?: null);
        $quadri->proponi('RP034003', $tutti('RP034002') ?: null);
        $erogazioni = $q('RP036001') + min($q('RP036002'), 0.1 * $redditoRiferimento);
        $quadri->proponi('RP036003', (int) round($erogazioni) ?: null);
        if ($q('RP023001') > 1549) {
            $this->avvisi[] = 'Quadro RP, rigo RP23: i contributi per gli addetti ai servizi domestici non possono superare 1.549 euro.';
        }
        if ($q('RP024001') > 14461) {
            $this->avvisi[] = 'Quadro RP, rigo RP24: le erogazioni alle istituzioni religiose non possono superare 14.461 euro.';
        }

        $altri = 0;
        $limitati = [7 => 0, 8 => 0, 12 => 0];
        $codici6e13 = 0;
        foreach ($quadri->getModuli() ?: [1] as $modulo) {
            $codice = $quadri->getImporto('RP026001', $modulo);
            $importo = $quadri->getImporto('RP026002', $modulo);
            if (isset($limitati[$codice])) {
                $limitati[$codice] += $importo;
            } else {
                $altri += $importo;
            }
            if (in_array($codice, [6, 13], true)) {
                $codici6e13 += $importo;
            }
        }
        if ($codici6e13 > 3615) {
            $this->avvisi[] = 'Quadro RP, rigo RP26: gli importi con i codici 6 e 13 non possono superare 3.615 euro.';
        }
        $deducibili26 = min($limitati[7], 0.02 * $redditoRiferimento) + min(min($limitati[8], 70000), 0.1 * $redditoRiferimento) + min(min($limitati[12], 100000), 0.2 * $redditoRiferimento);
        $this->controllaPrevidenza($quadri, $q);

        $totale = $q('RP021001') + $q('RP022002') + $q('RP023001') + $q('RP024001') + $q('RP025001') + $altri + $deducibili26
                + $q('RP027002') + $q('RP028002') + $q('RP029002') + $q('RP030002') + $quadri->getImporto('RP032004', 1) + $quadri->getImporto('RP033003', 1)
                + $quadri->getImporto('RP034003', 1) + $q('RP034004') + $q('RP034005') + $q('RP034006')
                + $quadri->getImporto('RP036003', 1) + $q('RP036004') + $q('RP036005') + $q('RP036006') + $q('RP036007');
        $quadri->proponi('RP039001', (int) round($totale) ?: null);
    }

    /**
     * Previdenza complementare (par. 12.2): limiti di deducibilità dei righi
     * da RP27 a RP30 (segnalati, non corretti).
     */
    private function controllaPrevidenza(QuadriDichiarazione $quadri, \Closure $q): void
    {
        $esclusi = $q('RP027001') + $q('RP028001') + $q('RP029001') + $q('RP030001');
        $richiesta = $q('RP027002') + $q('RP028002') + $q('RP030002');
        if ($richiesta === 0) {
            return;
        }
        $capienza28 = max(0, 7747 - $q('RP027001') - $q('RP028001') - $q('RP029001') - $q('RP029002') - $q('RP030001'));
        $limite1 = max(0, $capienza28 - 2582 - $q('RP028002'));
        $limiteFisso = $quadri->get('RP028001', 1) !== null || $quadri->get('RP028002', 1) !== null ? 7747 : 5165;
        if (($q('RP028002') > $capienza28) || ($q('RP027002') + $q('RP030002') > $limite1) || ($esclusi + $richiesta > $limiteFisso)) {
            $this->avvisi[] = 'Quadro RP, righi da RP27 a RP30: i contributi di previdenza complementare superano il limite di deducibilità (5.164,57 euro, 7.746,86 per i lavoratori di prima occupazione).';
        }
    }

    /**
     * Limiti per codice e per gruppi di codici dei righi da RP8 a RP13.
     *
     * @param array<int, int> $perCodice
     */
    private function controllaCodici(array $perCodice, int $interessiRp7): void
    {
        $importo = fn(int ...$codici) => array_sum(array_map(fn(int $c) => $perCodice[$c] ?? 0, $codici));
        foreach (self::LIMITI_CODICE as $codice => $limite) {
            if ($importo($codice) > $limite) {
                $this->avvisi[] = sprintf('Quadro RP, righi da RP8 a RP13: con il codice %d l\'importo non può superare %s euro.', $codice, number_format($limite, 0, ',', '.'));
            }
        }
        if ($importo(20) > 0) {
            $this->avvisi[] = 'Quadro RP, righi da RP8 a RP13: il codice 20 non è più utilizzabile, le erogazioni per le calamità vanno con il codice 61 (scarto della dichiarazione).';
        }
        if (($importo(62) > 0) && ($importo(62) < 30)) {
            $this->avvisi[] = 'Quadro RP, righi da RP8 a RP13: le erogazioni ai partiti politici (codice 62) non possono essere inferiori a 30 euro.';
        }
        if ($importo(8) > max(0, 2066 - $interessiRp7)) {
            $this->avvisi[] = 'Quadro RP, righi da RP8 a RP13: gli interessi per l\'acquisto di altri immobili (codice 8) non possono superare 2.066 euro meno gli interessi del rigo RP7.';
        }
        $gruppi = [[[10, 46, 55], 2582, 'interessi per la costruzione dell\'abitazione principale'], [[36, 51], 530, 'premi di assicurazione sulla vita e contro gli infortuni']];
        if ($importo(38, 52) > 0) {
            $gruppi[] = [[36, 38, 51, 52], 750, 'premi di assicurazione per la tutela delle persone con disabilità grave'];
        }
        if ($importo(39, 53) > 0) {
            $gruppi[] = [[36, 38, 39, 51, 52, 53], 1291, 'premi di assicurazione per il rischio di non autosufficienza'];
        }
        foreach ($gruppi as [$codici, $limite, $descrizione]) {
            if ($importo(...$codici) > $limite) {
                $this->avvisi[] = sprintf('Quadro RP, righi da RP8 a RP13: %s (codici %s) oltre il limite di %s euro.', $descrizione, implode(', ', $codici), number_format($limite, 0, ',', '.'));
            }
        }
    }
}
