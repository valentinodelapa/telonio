<?php

namespace Dichiarativi\Application\Services\Redditi;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\InterventoEdilizio;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoDetrazioneEdilizia as Tipo;
use Anagrafica\Application\Models\ContrattoLocazioneModel;
use Anagrafica\Application\Models\InterventoEdilizioModel;
use Anagrafica\Application\Models\PossessoModel;
use Anagrafica\Application\Services\TabellaDetrazioniEdilizie as Tabella;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Quadro RP, sezioni III A, III B, III C e IV, e righi RN14, RN15 e RN16
 * secondo le specifiche tecniche del modello Redditi PF 2026 (par. 13, 14 e
 * 15) dall'archivio degli interventi edilizi dell'Anagrafica:
 *
 * - righi da RP41 a RP47 (recupero edilizio), RP56 (colonnine), RP57
 *   (bonus mobili) e da RP61 a RP64 (risparmio energetico) con il numero
 *   della rata dell'anno e il suo importo, nei righi liberi (non compilati a
 *   mano) e nei moduli successivi;
 * - righi RP51 e RP52 con i dati catastali degli immobili (numero d'ordine
 *   della col. 11) o la casella "Condominio", rigo RP53 con gli estremi di
 *   registrazione del contratto se il contribuente è il conduttore;
 * - totali dei righi RP48, RP49, RP60, RP65 e RP66 per percentuale di
 *   detrazione dai righi di tutti i moduli, anche inseriti a mano
 *   (percentuale dalle tabelle delle specifiche);
 * - entità del riordino delle detrazioni (rate delle spese dell'anno) e
 *   righi RN14, RN15 e RN16, con le rate ricondotte nel limite del riordino
 *   oltre 75.000 euro di reddito di riferimento.
 */
class PropostaBonusEdilizi
{

    public const RIGHI_RISTRUTTURAZIONE = 7;
    public const RIGHI_CATASTALI = 2;
    public const RIGHI_ENERGETICO = 4;
    public const RIGHI_ARREDO = 2;

    /** Colonna dei righi RP48 e RP49 per percentuale */
    public const COLONNE_RP48 = ['60' => 1, '50' => 2, '65' => 3, '70' => 4, '75' => 5, '80' => 6, '85' => 7, '36' => 8, '90' => 9, '110' => 10];
    /** Colonna del rigo RP60 per percentuale */
    public const COLONNE_RP60 = ['50' => 1, '110' => 2, '90' => 3, '70' => 4, '65' => 5];
    /** Colonna dei righi RP65 e RP66 per percentuale */
    public const COLONNE_RP65 = ['50' => 1, '55' => 2, '65' => 3, '70' => 4, '75' => 5, '80' => 6, '85' => 7, '90' => 8, '110' => 9, '60' => 10, '36' => 11];

    /** @var string[] */
    private array $avvisi = [];

    /** @var array<string, InterventoEdilizio[]> interventi con una rata nell'anno per tipo */
    private array $interventi = [];

    /** @var array<string, float> percentuale dei righi proposti dall'archivio, per "modulo-codice del rigo" */
    private array $percentuali = [];

    /** @var array<string, array<string, float>> rate per sezione e percentuale: ordinarie (concorrono sempre) */
    private array $ordinarie = [];

    /** @var array<string, float> rate delle spese dell'anno soggette al riordino, per entità */
    private array $entita = [];

    private int $anno = 0;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Interventi del soggetto con una rata nell'anno d'imposta.
     */
    public function carica(Soggetto $soggetto, int $anno): self
    {
        $this->anno = $anno;
        $this->interventi = array_fill_keys(array_map(fn(Tipo $tipo) => $tipo->value, Tipo::cases()), []);
        foreach ((new InterventoEdilizioModel($this->dataMapper))->getConRataNellAnno($soggetto, $anno) as $intervento) {
            $this->interventi[$intervento->tipo->value][] = $intervento;
        }
        return $this;
    }

    /**
     * Moduli necessari per i righi degli interventi (senza contare i righi
     * inseriti a mano).
     */
    public function getModuliNecessari(): int
    {
        $immobili = [];
        foreach ($this->interventi[Tipo::ristrutturazione->value] ?? [] as $intervento) {
            $immobili[$this->chiaveImmobile($intervento)] = true;
        }
        return max(1, (int) ceil(count($this->interventi[Tipo::ristrutturazione->value] ?? []) / self::RIGHI_RISTRUTTURAZIONE),
                (int) ceil(count($immobili) / self::RIGHI_CATASTALI), count($this->interventi[Tipo::colonnine->value] ?? []),
                (int) ceil(count($this->interventi[Tipo::arredo->value] ?? []) / self::RIGHI_ARREDO),
                (int) ceil(count($this->interventi[Tipo::risparmioEnergetico->value] ?? []) / self::RIGHI_ENERGETICO));
    }

    /**
     * Righi degli interventi nei righi liberi dei moduli (i quadri sono già
     * in proposta).
     */
    public function proponiRighi(QuadriDichiarazione $quadri, Soggetto $soggetto): void
    {
        $this->percentuali = [];
        $this->proponiRistrutturazioni($quadri, $soggetto);
        foreach ($this->interventi[Tipo::colonnine->value] ?? [] as $intervento) {
            [$modulo] = $this->rigoLibero($quadri, [56], ['RP056001', 'RP056003', 'RP056006']);
            $quadri->proponi('RP056001', $intervento->tipologia, $modulo);
            $quadri->proponi('RP056002', $intervento->anno, $modulo);
            $quadri->proponi('RP056003', (int) round($intervento->spesa), $modulo);
            $quadri->proponi('RP056004', $intervento->codicePercentuale, $modulo);
            $quadri->proponi('RP056005', $intervento->anno === 2022 ? $intervento->getNumeroRata($this->anno) : null, $modulo);
            $quadri->proponi('RP05605A', $intervento->opzione2023 ?: null, $modulo);
            $quadri->proponi('RP056006', $intervento->getImportoRata(), $modulo);
            $this->percentuali[$modulo . '-RP056a'] = $intervento->percentualeDetrazione;
        }
        foreach ($this->interventi[Tipo::arredo->value] ?? [] as $intervento) {
            [$modulo, $colonna] = $this->rigoLibero($quadri, [1, 4], fn(int $colonna) => [sprintf('RP057%03d', $colonna), sprintf('RP057%03d', $colonna + 2)]);
            $quadri->proponi(sprintf('RP057%03d', $colonna), $intervento->getNumeroRata($this->anno), $modulo);
            $quadri->proponi(sprintf('RP057%03d', $colonna + 1), (int) round($intervento->spesa), $modulo);
            $quadri->proponi(sprintf('RP057%03d', $colonna + 2), $intervento->getImportoRata(), $modulo);
        }
        foreach ($this->interventi[Tipo::risparmioEnergetico->value] ?? [] as $intervento) {
            [$modulo, $rigo] = $this->rigoLibero($quadri, range(61, 64), fn(int $rigo) => [sprintf('RP%03d001', $rigo), sprintf('RP%03d008', $rigo)]);
            $codice = fn(string $colonna) => sprintf('RP%03d%s', $rigo, $colonna);
            $quadri->proponi($codice('001'), $intervento->tipologia, $modulo);
            $quadri->proponi($codice('002'), $intervento->anno, $modulo);
            $quadri->proponi($codice('004'), $intervento->casiParticolari, $modulo);
            $quadri->proponi($codice('006'), $intervento->codicePercentuale, $modulo);
            $quadri->proponi($codice('007'), $intervento->getNumeroRata($this->anno), $modulo);
            $quadri->proponi($codice('07A'), $intervento->opzione2023 ?: null, $modulo);
            $quadri->proponi($codice('008'), (int) round($intervento->spesa), $modulo);
            $quadri->proponi($codice('009'), $intervento->maggiorazioneSisma ?: null, $modulo);
            $quadri->proponi($codice('010'), $intervento->getImportoRata(), $modulo);
            $this->percentuali[$modulo . '-RP0' . $rigo] = $intervento->percentualeDetrazione;
        }
    }

    /**
     * Righi da RP41 a RP47 e dati catastali (RP51-RP53) con il numero
     * d'ordine dell'immobile.
     */
    private function proponiRistrutturazioni(QuadriDichiarazione $quadri, Soggetto $soggetto): void
    {
        $numeri = [];
        foreach ($quadri->getModuli() ?: [1] as $modulo) {
            foreach ([51, 52, 53] as $rigo) {
                $numeri[] = $quadri->getImporto(sprintf('RP%03d001', $rigo), $modulo);
            }
        }
        $prossimo = max([0, ...$numeri]) + 1;
        $ordine = [];
        foreach ($this->interventi[Tipo::ristrutturazione->value] ?? [] as $intervento) {
            $chiave = $this->chiaveImmobile($intervento);
            if (isset($ordine[$chiave]) === false) {
                $ordine[$chiave] = $prossimo++;
                $this->proponiImmobile($quadri, $soggetto, $intervento, $ordine[$chiave]);
            }
            [$modulo, $rigo] = $this->rigoLibero($quadri, range(41, 47), fn(int $rigo) => [sprintf('RP%03d001', $rigo), sprintf('RP%03d009', $rigo), sprintf('RP%03d010', $rigo)]);
            $codice = fn(string $colonna) => sprintf('RP%03d%s', $rigo, $colonna);
            $quadri->proponi($codice('001'), $intervento->anno, $modulo);
            $quadri->proponi($codice('002'), $intervento->tipologia, $modulo);
            $quadri->proponi($codice('003'), $intervento->codiceFiscaleCondominio, $modulo);
            $quadri->proponi($codice('004'), $intervento->casiParticolari, $modulo);
            $quadri->proponi($codice('005'), $intervento->acquistoEreditaDonazione ? 4 : null, $modulo);
            $quadri->proponi($codice('006'), $intervento->maggiorazioneSisma ?: null, $modulo);
            $quadri->proponi($codice('007'), $intervento->codicePercentuale, $modulo);
            $quadri->proponi($codice('008'), $intervento->getNumeroRata($this->anno), $modulo);
            $quadri->proponi($codice('08A'), $intervento->opzione2023 ?: null, $modulo);
            $quadri->proponi($codice('009'), (int) round($intervento->spesa), $modulo);
            $quadri->proponi($codice('010'), $intervento->getImportoRata(), $modulo);
            $quadri->proponi($codice('011'), $ordine[$chiave], $modulo);
            $this->percentuali[$modulo . '-RP0' . $rigo] = $intervento->percentualeDetrazione;
        }
        $this->controllaLimiti();
    }

    /**
     * Rigo RP51 o RP52 con i dati catastali (o la casella "Condominio") e
     * rigo RP53 con gli estremi del contratto se il contribuente non possiede
     * l'immobile ed è il conduttore (specifiche, par. 13.3).
     */
    private function proponiImmobile(QuadriDichiarazione $quadri, Soggetto $soggetto, InterventoEdilizio $intervento, int $numero): void
    {
        $immobile = $intervento->immobile;
        [$modulo, $rigo] = $this->rigoLibero($quadri, [51, 52], fn(int $rigo) => [sprintf('RP%03d001', $rigo)]);
        $codice = fn(string $colonna) => sprintf('RP%03d%s', $rigo, $colonna);
        $quadri->proponi($codice('001'), $numero, $modulo);
        if ($intervento->condominio) {
            $quadri->proponi($codice('002'), true, $modulo);
            return;
        }
        [$numeratore, $denominatore] = array_pad(explode('/', $immobile->particella, 2), 2, null);
        $quadri->proponi($codice('003'), $immobile->comune->codiceCatastale, $modulo);
        $quadri->proponi($codice('004'), $immobile->isTavolare() ? 'T' : 'U', $modulo);
        $quadri->proponi($codice('005'), $immobile->isTavolare() ? $immobile->comuneCatastale : $immobile->sezione, $modulo);
        $quadri->proponi($codice('006'), $immobile->foglio, $modulo);
        $quadri->proponi($codice('A07'), $numeratore, $modulo);
        $quadri->proponi($codice('B07'), $denominatore, $modulo);
        $quadri->proponi($codice('008'), $immobile->subalterno, $modulo);
        $this->proponiConduttore($quadri, $soggetto, $immobile, $numero);
    }

    private function proponiConduttore(QuadriDichiarazione $quadri, Soggetto $soggetto, Immobile $immobile, int $numero): void
    {
        $dal = new \DateTimeImmutable($this->anno . '-01-01');
        $al = new \DateTimeImmutable($this->anno . '-12-31');
        foreach ((new PossessoModel($this->dataMapper))->getStoricoImmobile($immobile) as $possesso) {
            if (($possesso->soggetto->id === $soggetto->id) && ($possesso->validoDal->format('Y-m-d') <= $al->format('Y-m-d'))
                    && (($possesso->validoAl === null) || ($possesso->validoAl->format('Y-m-d') >= $dal->format('Y-m-d')))) {
                return;
            }
        }
        foreach ((new ContrattoLocazioneModel($this->dataMapper))->getInCorsoTra($immobile, $dal, $al) as $contratto) {
            if ($contratto->codiceFiscaleConduttore !== $soggetto->codiceFiscale) {
                continue;
            }
            $modulo = $this->rigoLibero($quadri, [53], ['RP053001'])[0];
            [$numeroRegistrazione, $sottonumero] = array_pad(explode('/', (string) $contratto->numeroRegistrazione, 2), 2, null);
            $quadri->proponi('RP053001', $numero, $modulo);
            $quadri->proponi('RP053003', $contratto->dataRegistrazione?->format('Y-m-d'), $modulo);
            $quadri->proponi('RP053004', $contratto->serieRegistrazione, $modulo);
            $quadri->proponi('RP053A05', $numeroRegistrazione ?: null, $modulo);
            $quadri->proponi('RP053B05', $sottonumero, $modulo);
            $quadri->proponi('RP053006', $contratto->codiceUfficio, $modulo);
            $quadri->proponi('RP053007', $contratto->codiceIdentificativo, $modulo);
            return;
        }
        $this->avvisi[] = sprintf('Quadro RP, sezione III B: %s non possiede l\'immobile %s e non è il conduttore di un contratto dell\'archivio: indica a mano gli estremi del titolo nel rigo RP53.', $soggetto->nominativo, $immobile->getIdentificativo());
    }

    /**
     * Limiti di spesa per immobile e anno (par. 13.1.3): somma delle spese
     * con lo stesso limite (i codici 16 e 17 separatamente).
     */
    private function controllaLimiti(): void
    {
        $totali = [];
        foreach ($this->interventi[Tipo::ristrutturazione->value] ?? [] as $intervento) {
            $limite = Tabella::limiteSpesa(Tipo::ristrutturazione, $intervento->anno, $intervento->tipologia, $intervento->maggiorazioneSisma);
            $chiave = $this->chiaveImmobile($intervento) . '|' . $intervento->anno . '|' . $limite . (in_array($intervento->tipologia, [16, 17], true) ? '|' . $intervento->tipologia : '');
            $totali[$chiave] = ['limite' => $limite, 'spesa' => ($totali[$chiave]['spesa'] ?? 0.0) + $intervento->spesa, 'intervento' => $intervento];
        }
        foreach ($totali as $totale) {
            if ($totale['spesa'] > $totale['limite']) {
                $this->avvisi[] = sprintf('Quadro RP, righi da RP41 a RP47: le spese del %d per l\'immobile %s superano il limite di %s euro.', $totale['intervento']->anno,
                        $totale['intervento']->immobile->getIdentificativo(), number_format($totale['limite'], 0, ',', '.'));
            }
        }
    }

    /**
     * Totali dei righi RP48, RP49, RP60, RP65 e RP66 dai righi di tutti i
     * moduli e rate soggette al riordino (spese dell'anno d'imposta).
     */
    public function proponiTotali(QuadriDichiarazione $quadri, int $anno): void
    {
        $this->anno = $anno;
        $this->ordinarie = ['ristrutturazione' => [], 'colonnine' => [], 'energetico' => []];
        $this->entita = [];
        $moduli = $quadri->getModuli() ?: [1];
        $rp48 = [];
        $rp60 = [];
        $rp65 = [];
        foreach ($moduli as $modulo) {
            for ($rigo = 41; $rigo <= 47; $rigo++) {
                $rata = $quadri->getImporto(sprintf('RP%03d010', $rigo), $modulo);
                $annoSpesa = $quadri->getImporto(sprintf('RP%03d001', $rigo), $modulo);
                if ($rata === 0) {
                    continue;
                }
                $percentuale = $this->percentuali[$modulo . '-RP0' . $rigo] ?? Tabella::percentuale(Tipo::ristrutturazione, $annoSpesa, $this->intero($quadri, sprintf('RP%03d002', $rigo), $modulo), $this->intero($quadri, sprintf('RP%03d007', $rigo), $modulo));
                if (($percentuale === null) || (isset(self::COLONNE_RP48[$this->chiave($percentuale)]) === false)) {
                    $this->avvisi[] = sprintf('Quadro RP, rigo RP%d%s: percentuale di detrazione non determinabile dall\'anno, dalla tipologia e dalla casella "Percentuale": compila a mano il rigo RP48.', $rigo, $modulo > 1 ? ' del modulo ' . $modulo : '');
                    continue;
                }
                $chiave = $this->chiave($percentuale);
                $rp48[$chiave] = ($rp48[$chiave] ?? 0) + $rata;
                $this->accumula('ristrutturazione', 'ri', $chiave, $rata, $annoSpesa === $anno);
            }
            foreach ([[1, 2, 3, 4, 6, 'RP056a'], [8, 9, 10, 11, 13, 'RP056b']] as [$cCodice, $cAnno, $cImporto, $cPercentuale, $cRata, $chiaveRigo]) {
                $codice = $quadri->getImporto(sprintf('RP056%03d', $cCodice), $modulo);
                $rata = $quadri->getImporto(sprintf('RP056%03d', $cRata), $modulo);
                if ($rata === 0) {
                    continue;
                }
                $annoSpesa = $quadri->getImporto(sprintf('RP056%03d', $cAnno), $modulo);
                $percentuale = $codice === 1 ? 50.0 : ($this->percentuali[$modulo . '-' . $chiaveRigo] ?? Tabella::percentuale(Tipo::colonnine, $annoSpesa, $codice, $this->intero($quadri, sprintf('RP056%03d', $cPercentuale), $modulo)));
                if (($percentuale === null) || (isset(self::COLONNE_RP60[$this->chiave($percentuale)]) === false)) {
                    $this->avvisi[] = 'Quadro RP, rigo RP56' . ($modulo > 1 ? ' del modulo ' . $modulo : '') . ': percentuale di detrazione non determinabile, compila a mano il rigo RP60.';
                    continue;
                }
                $chiave = $this->chiave($percentuale);
                $rp60[$chiave] = ($rp60[$chiave] ?? 0) + $rata;
                $this->accumula('colonnine', 'co', $chiave, $rata, ($annoSpesa === $anno) && ($codice >= 3) && in_array($chiave, ['65', '110'], true));
            }
            foreach ([1, 4] as $colonna) {
                $rata = $quadri->getImporto(sprintf('RP057%03d', $colonna + 2), $modulo);
                $rp60['50'] = ($rp60['50'] ?? 0) + $rata;
                $this->accumula('colonnine', 'ar', '50', $rata, $quadri->getImporto(sprintf('RP057%03d', $colonna), $modulo) === 1);
            }
            $altre = $quadri->getImporto('RP058003', $modulo) + $quadri->getImporto('RP059003', $modulo);
            $rp60['50'] = ($rp60['50'] ?? 0) + $altre;
            $this->accumula('colonnine', '', '50', $altre, false);
            for ($rigo = 61; $rigo <= 64; $rigo++) {
                $rata = $quadri->getImporto(sprintf('RP%03d010', $rigo), $modulo);
                if ($rata === 0) {
                    continue;
                }
                $annoSpesa = $quadri->getImporto(sprintf('RP%03d002', $rigo), $modulo);
                $percentuale = $this->percentuali[$modulo . '-RP0' . $rigo] ?? ($annoSpesa === 2008 ? 55.0 : Tabella::percentuale(Tipo::risparmioEnergetico, $annoSpesa, $this->intero($quadri, sprintf('RP%03d001', $rigo), $modulo), $this->intero($quadri, sprintf('RP%03d006', $rigo), $modulo)));
                if (($percentuale === null) || (isset(self::COLONNE_RP65[$this->chiave($percentuale)]) === false)) {
                    $this->avvisi[] = sprintf('Quadro RP, rigo RP%d%s: percentuale di detrazione non determinabile dal tipo, dall\'anno e dalla casella "Percentuale": compila a mano il rigo RP65.', $rigo, $modulo > 1 ? ' del modulo ' . $modulo : '');
                    continue;
                }
                $chiave = $this->chiave($percentuale);
                $rp65[$chiave] = ($rp65[$chiave] ?? 0) + $rata;
                $this->accumula('energetico', 'rq', $chiave, $rata, $annoSpesa === $anno);
            }
        }
        foreach (self::COLONNE_RP48 as $chiave => $colonna) {
            $quadri->proponi(sprintf('RP048%03d', $colonna), ($rp48[$chiave] ?? 0) ?: null, 1);
            $quadri->proponi(sprintf('RP049%03d', $colonna), (int) round(($rp48[$chiave] ?? 0) * (float) $chiave / 100) ?: null, 1);
        }
        foreach (self::COLONNE_RP60 as $chiave => $colonna) {
            $quadri->proponi(sprintf('RP060%03d', $colonna), ($rp60[$chiave] ?? 0) ?: null, 1);
        }
        foreach (self::COLONNE_RP65 as $chiave => $colonna) {
            $quadri->proponi(sprintf('RP065%03d', $colonna), ($rp65[$chiave] ?? 0) ?: null, 1);
            $quadri->proponi(sprintf('RP066%03d', $colonna), (int) round(($rp65[$chiave] ?? 0) * (float) $chiave / 100) ?: null, 1);
        }
    }

    /**
     * Rate delle spese dell'anno soggette al riordino (tabella di supporto,
     * par. 10.4), per entità: percentuale e sezione ("ri" recupero edilizio,
     * "co" colonnine, "ar" bonus mobili, "rq" risparmio energetico).
     *
     * @return array<string, float>
     */
    public function getEntitaRiordino(): array
    {
        return $this->entita;
    }

    /**
     * Righi RN14, RN15 e RN16: rate ordinarie più quelle del riordino
     * (tutte fino a 75.000 euro, quelle ricondotte nel limite oltre).
     *
     * @param array<string, float>|null $imputate entità ricondotte nel limite del riordino, null senza riordino
     * @return array{RN014001: int, RN015001: int, RN016001: int}
     */
    public function detrazioni(?array $imputate): array
    {
        $rate = $this->ordinarie;
        foreach ($this->entita as $entita => $importo) {
            [$percentuale, $sezione] = [substr($entita, 0, -2), substr($entita, -2)];
            $gruppo = match ($sezione) {
                'ri' => 'ristrutturazione',
                'rq' => 'energetico',
                default => 'colonnine',
            };
            $rate[$gruppo][$percentuale] = ($rate[$gruppo][$percentuale] ?? 0.0) + ($imputate === null ? $importo : ($imputate[$entita] ?? 0.0));
        }
        $perColonna = fn(array $gruppo) => array_sum(array_map(fn(string $percentuale) => round($gruppo[$percentuale] * (float) $percentuale / 100), array_keys($gruppo)));
        $colonnine = 0.0;
        foreach ($rate['colonnine'] as $percentuale => $importo) {
            $colonnine += $importo * (float) $percentuale / 100;
        }
        return ['RN014001' => (int) $perColonna($rate['ristrutturazione']), 'RN015001' => (int) round($colonnine), 'RN016001' => (int) $perColonna($rate['energetico'])];
    }

    /**
     * @return string[]
     */
    public function getAvvisi(): array
    {
        return $this->avvisi;
    }

    /**
     * Rata ordinaria o, per le spese dell'anno, entità del riordino.
     */
    private function accumula(string $gruppo, string $sezione, string $percentuale, int $rata, bool $riordino): void
    {
        if ($rata === 0) {
            return;
        }
        if ($riordino) {
            $entita = $percentuale . $sezione;
            $this->entita[$entita] = ($this->entita[$entita] ?? 0.0) + $rata;
            return;
        }
        $this->ordinarie[$gruppo][$percentuale] = ($this->ordinarie[$gruppo][$percentuale] ?? 0.0) + $rata;
    }

    /**
     * Primo rigo libero (nessun valore inserito a mano nei campi chiave) dei
     * moduli, dal primo: [modulo, rigo o colonna].
     *
     * @param int[] $righi
     * @param string[]|\Closure(int): string[] $campi campi chiave del rigo
     * @return array{0: int, 1: int}
     */
    private function rigoLibero(QuadriDichiarazione $quadri, array $righi, array|\Closure $campi): array
    {
        for ($modulo = 1;; $modulo++) {
            foreach ($righi as $rigo) {
                $codici = $campi instanceof \Closure ? $campi($rigo) : $campi;
                $occupato = count(array_filter($codici, fn(string $codice) => $quadri->get($codice, $modulo) !== null)) > 0;
                if ($occupato === false) {
                    return [$modulo, $rigo];
                }
            }
        }
    }

    private function chiaveImmobile(InterventoEdilizio $intervento): string
    {
        return $intervento->immobile->id . ($intervento->condominio ? 'C' : '');
    }

    private function chiave(float $percentuale): string
    {
        return (string) (int) round($percentuale);
    }

    private function intero(QuadriDichiarazione $quadri, string $codice, int $modulo): ?int
    {
        $valore = $quadri->get($codice, $modulo);
        return ($valore === null) || ($valore === '') ? null : (int) $valore;
    }
}
