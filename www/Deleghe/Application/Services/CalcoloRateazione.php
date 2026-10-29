<?php

namespace Deleghe\Application\Services;

use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Entities\RateazioneSecondoAcconto;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Models\CodiceTributoModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;
use Telonio\Application\HelperClasses\Calendario;

/**
 * Pagamento rateale del saldo e del primo acconto (art. 20, c. 1, D.Lgs.
 * 241/1997, nel testo modificato dall'art. 8 del D.Lgs. 1/2024; circolare
 * n. 9/E del 2024):
 * - rate mensili di pari importo: la prima alla scadenza ordinaria, le
 *   successive entro il 16 di ciascun mese, non oltre il 16 dicembre;
 * - sulle rate successive alla prima interessi del 4% annuo con il metodo
 *   commerciale (mesi di 30 giorni, anno di 360), come nelle tabelle delle
 *   istruzioni: la frazione dalla scadenza della prima rata alla seconda,
 *   arrotondata al centesimo, più 0,33% per ogni mese successivo (prima rata
 *   30/06: 0,18 - 0,51 - 0,84 - 1,17 - 1,50%; prima rata 01/07: 0,17 - 0,50
 *   - 0,83 - 1,16%), calcolati sulle scadenze nominali (il 16);
 * - il versamento slitta al primo giorno lavorativo, e dal 1° al 20 agosto
 *   al 20 agosto (Calendario::termineFiscale).
 *
 * Sono rateizzabili i codici tributo con rateazione nel formato NNRR (saldo e
 * primo acconto di IRPEF, IRES, IRAP, addizionali, IVA, cedolare, ...; non
 * il secondo acconto, che ha 0000) e i contributi INPS eccedenti il minimale
 * e della gestione separata (causali AP, CP, P10, PXX). Interessi su righi
 * separati: 1668 per l'Erario, 3805 per le Regioni (con il codice regione),
 * 3857 per l'addizionale comunale (con il codice del comune e la rata); per
 * l'INPS tutte le rate hanno la causale del pagamento rateale (APR, CPR,
 * P10R, PXXR) e gli interessi le causali API, CPI e DPPI (circolare INPS n.
 * 105 del 2025).
 *
 * Secondo acconto (codici con rateazione 0000): solo per i periodi d'imposta
 * in cui una norma straordinaria lo ha consentito (RateazioneSecondoAcconto),
 * in unica soluzione al termine differito (16 gennaio) o fino al numero di
 * rate mensili della norma, il 16 di ogni mese, con gli stessi interessi
 * dalla seconda rata (1668); il campo rateazione resta vuoto come richiede
 * il formato dei codici (0000).
 */
class CalcoloRateazione
{

    public const TASSO_ANNUO = 4.0;
    public const GIORNO_RATE = 16;
    private const MESE_ULTIMA_RATA = 12;
    public const CODICE_INTERESSI_ERARIO = '1668';
    public const CODICE_INTERESSI_REGIONI = '3805';
    public const CODICE_INTERESSI_ADDIZIONALE_COMUNALE = '3857';

    /**
     * Causale INPS del versamento in unica soluzione =>
     * [causale del pagamento rateale, causale degli interessi].
     */
    public const CAUSALI_INPS = [
        'AP' => ['APR', 'API'],
        'CP' => ['CPR', 'CPI'],
        'P10' => ['P10R', 'DPPI'],
        'PXX' => ['PXXR', 'DPPI'],
    ];

    /**
     * @param \Closure(SezioneF24, string): ?CodiceTributo|null $codici ricerca dei codici
     *        degli interessi (di default nella tabella dei codici tributo)
     */
    public function __construct(private DataMapper $dataMapper = new DataMapper(), private ?\Closure $codici = null)
    {

    }

    public static function isRateizzabile(Versamento $versamento): bool
    {
        if (($versamento->importoDebito <= 0) || ($versamento->importoCredito > 0)) {
            return false;
        }
        return match ($versamento->sezione) {
            SezioneF24::erario, SezioneF24::regioni, SezioneF24::tributiLocali => $versamento->codiceTributo?->formatoRateazione === 'NNRR'
                && ($versamento->codiceTributo->isImu() === false),
            SezioneF24::inps => isset(self::CAUSALI_INPS[$versamento->causale]),
            default => false,
        };
    }

    public static function isInteresse(Versamento $versamento): bool
    {
        return in_array($versamento->codiceTributo?->codice, [self::CODICE_INTERESSI_ERARIO, self::CODICE_INTERESSI_REGIONI, self::CODICE_INTERESSI_ADDIZIONALE_COMUNALE], true)
                || in_array($versamento->causale, array_column(self::CAUSALI_INPS, 1), true);
    }

    /**
     * Numero della rata di un versamento generato dal piano (dalla
     * descrizione "Rata N/M ..." o "Interessi rata N/M"), o null.
     */
    public static function getNumeroRata(Versamento $versamento): ?int
    {
        return preg_match('/\brata (\d+)\/\d+/i', (string) $versamento->descrizione, $parti) === 1 ? (int) $parti[1] : null;
    }

    /**
     * Data della prima rata: la scadenza dei versamenti, o il termine
     * differito della norma straordinaria del secondo acconto.
     */
    public static function getPrimaRata(\DateTimeInterface $scadenza, ?RateazioneSecondoAcconto $norma = null): SismaDate
    {
        return new SismaDate(($norma?->primaRata ?? $scadenza)->format('Y-m-d'));
    }

    /**
     * Numero minimo di rate: due, o una sola (versamento in unica soluzione
     * al termine differito) con la norma straordinaria del secondo acconto.
     */
    public static function getMinimoRate(?RateazioneSecondoAcconto $norma = null): int
    {
        return $norma === null ? 2 : 1;
    }

    /**
     * Numero massimo di rate con la prima alla data indicata: le successive
     * il 16 di ciascuno dei mesi seguenti, fino a dicembre; con la norma
     * straordinaria del secondo acconto il numero che questa consente.
     */
    public static function getMassimoRate(\DateTimeInterface $primaRata, ?RateazioneSecondoAcconto $norma = null): int
    {
        return $norma?->numeroRate ?? (1 + self::MESE_ULTIMA_RATA - (int) $primaRata->format('n'));
    }

    /**
     * Date proposte: la prima rata alla scadenza, poi il 16 dei mesi
     * successivi (scadenze nominali; il versamento è alla data di
     * getDataVersamento).
     *
     * @return SismaDate[]
     */
    public static function getDateProposte(\DateTimeInterface $primaRata, int $numeroRate, ?RateazioneSecondoAcconto $norma = null): array
    {
        $date = [new SismaDate($primaRata->format('Y-m-d'))];
        $mese = new \DateTimeImmutable($primaRata->format('Y-m-01'));
        for ($numero = 2; $numero <= min($numeroRate, self::getMassimoRate($primaRata, $norma)); $numero++) {
            $mese = $mese->modify('+1 month');
            $date[] = new SismaDate($mese->format('Y-m-') . self::GIORNO_RATE);
        }
        return $date;
    }

    /**
     * Giorni con il metodo commerciale (30/360) dal giorno successivo a $da
     * fino ad $a incluso.
     */
    public static function giorniCommerciali(\DateTimeInterface $da, \DateTimeInterface $a): int
    {
        $giornoDa = min(30, (int) $da->format('j'));
        $giornoA = min(30, (int) $a->format('j'));
        return (((int) $a->format('Y') - (int) $da->format('Y')) * 360) + (((int) $a->format('n') - (int) $da->format('n')) * 30) + $giornoA - $giornoDa;
    }

    /**
     * Percentuale di interessi della rata come nelle tabelle delle
     * istruzioni: frazione dalla prima alla seconda rata arrotondata al
     * centesimo, più la quota mensile arrotondata (0,33%) per ogni mese
     * (30 giorni commerciali) dalla seconda rata.
     */
    public static function getPercentualeInteressi(\DateTimeInterface $primaRata, \DateTimeInterface $secondaRata, \DateTimeInterface $rata): float
    {
        $primoPeriodo = round(self::TASSO_ANNUO * self::giorniCommerciali($primaRata, $secondaRata) / 360, 2);
        $quotaMensile = round(self::TASSO_ANNUO / 12, 2);
        return round($primoPeriodo + ($quotaMensile * self::giorniCommerciali($secondaRata, $rata) / 30), 2);
    }

    /**
     * Data di versamento della rata: primo giorno lavorativo, e dal 1° al 20
     * agosto il 20 agosto.
     */
    public static function getDataVersamento(\DateTimeInterface $data): SismaDate
    {
        return Calendario::termineFiscale($data);
    }

    /**
     * Quote di pari importo in centesimi; i centesimi che avanzano vanno
     * sulla prima rata.
     *
     * @return int[]
     */
    public static function ripartisci(int $centesimi, int $numeroRate): array
    {
        $quota = intdiv($centesimi, $numeroRate);
        $quote = array_fill(0, $numeroRate, $quota);
        $quote[0] += $centesimi - ($quota * $numeroRate);
        return $quote;
    }

    /**
     * Errori delle date delle rate (vuoto se valide): crescenti, la prima
     * uguale alla scadenza dei versamenti, al più una per mese e non oltre il
     * 16 dicembre dell'anno; con la norma straordinaria del secondo acconto
     * la prima al termine differito, al più il numero di rate della norma e
     * non oltre il 16 del mese dell'ultima.
     *
     * @param \DateTimeInterface[] $date
     * @return string[]
     */
    public static function verificaDate(\DateTimeInterface $scadenza, array $date, ?RateazioneSecondoAcconto $norma = null): array
    {
        $errori = [];
        $minimo = self::getMinimoRate($norma);
        if (count($date) < $minimo) {
            $errori[] = $minimo === 1 ? 'Indicare almeno una rata.' : 'Indicare almeno due rate.';
            return $errori;
        }
        $primaRata = self::getPrimaRata($scadenza, $norma);
        if ($date[0]->format('Y-m-d') !== $primaRata->format('Y-m-d')) {
            $errori[] = $norma === null ? 'La prima rata si versa alla scadenza dei versamenti (' . $scadenza->format('d/m/Y') . ').'
                    : 'La prima rata (o l\'unica soluzione) si versa al termine differito dalla norma (' . $primaRata->format('d/m/Y') . ').';
        }
        if (($norma !== null) && (count($date) > $norma->numeroRate)) {
            $errori[] = 'La norma consente al più ' . $norma->numeroRate . ' rate.';
        }
        $ultima = $norma === null ? null : self::getDateProposte($primaRata, $norma->numeroRate, $norma);
        $limite = $norma === null ? $scadenza->format('Y') . '-12-' . self::GIORNO_RATE : end($ultima)->format('Y-m-d');
        $mesi = [];
        foreach ($date as $indice => $data) {
            if (($indice > 0) && ($data->format('Y-m-d') <= $date[$indice - 1]->format('Y-m-d'))) {
                $errori[] = 'La rata ' . ($indice + 1) . ' deve seguire la precedente.';
            }
            if ($data->format('Y-m-d') > $limite) {
                $errori[] = 'La rata ' . ($indice + 1) . ' supera il ' . (new \DateTimeImmutable($limite))->format('d/m/Y') . ', termine del pagamento rateale.';
            }
            $mesi[] = $data->format('Y-m');
        }
        if (count(array_unique($mesi)) !== count($mesi)) {
            $errori[] = 'Le rate sono mensili: al più una rata per mese.';
        }
        return $errori;
    }

    /**
     * Versamenti delle rate (non salvati), con quote e interessi; le date
     * sono le scadenze nominali, il versamento slitta al primo giorno
     * lavorativo.
     *
     * @param Versamento[] $originali versamenti rateizzabili con la stessa scadenza
     * @param \DateTimeInterface[] $date date delle rate (la prima è la scadenza)
     * @return Versamento[]
     * @throws \InvalidArgumentException se un codice degli interessi non è in tabella
     */
    public function calcola(array $originali, array $date): array
    {
        $numeroRate = count($date);
        $prima = $date[0];
        $quote = [];
        foreach ($originali as $chiave => $originale) {
            $quote[$chiave] = self::ripartisci(EsitoControllo::centesimi($originale->importoDebito), $numeroRate);
        }
        $rate = [];
        foreach ($date as $indice => $data) {
            $numero = $indice + 1;
            $percentuale = $indice === 0 ? 0.0 : self::getPercentualeInteressi($prima, $date[1], $data);
            $interessi = [];
            foreach ($originali as $chiave => $originale) {
                $rata = $this->copiaRata($originale, $data, $numero, $numeroRate);
                $rata->importoDebito = $quote[$chiave][$indice] / 100;
                $rate[] = $rata;
                if ($percentuale > 0) {
                    $gruppo = self::getChiaveInteressi($originale);
                    $interessi[$gruppo] ??= ['originale' => $originale, 'quota' => 0];
                    $interessi[$gruppo]['quota'] += $quote[$chiave][$indice];
                }
            }
            foreach ($interessi as ['originale' => $originale, 'quota' => $quota]) {
                $importo = (int) round($quota * $percentuale / 100);
                if ($importo > 0) {
                    $rate[] = $this->creaInteressi($originale, $data, $numero, $numeroRate, $importo / 100);
                }
            }
        }
        return $rate;
    }

    private function copiaRata(Versamento $originale, \DateTimeInterface $data, int $numero, int $numeroRate): Versamento
    {
        $rata = new Versamento($this->dataMapper);
        foreach (['sezione', 'codiceTributo', 'codiceEnte', 'codiceSede', 'causale', 'posizione', 'annoRiferimento', 'periodoDa', 'periodoA',
    'acconto', 'saldo', 'codiceFiscaleCoobbligato', 'codiceIdentificativo'] as $campo) {
            $rata->$campo = $originale->$campo;
        }
        $rata->scadenza = self::getDataVersamento($data);
        if ($rata->sezione === SezioneF24::inps) {
            $rata->causale = self::CAUSALI_INPS[$originale->causale][0];
        } elseif ($originale->codiceTributo?->formatoRateazione === 'NNRR') {
            $rata->rateazione = sprintf('%02d%02d', $numero, $numeroRate);
        }
        $rata->descrizione = mb_substr('Rata ' . $numero . '/' . $numeroRate . ' ' . ($originale->descrizione ?? $originale->getCodice()), 0, 100);
        return $rata;
    }

    /**
     * Rigo degli interessi a cui appartiene un versamento (originale, quota o
     * interessi stessi): uno per sezione, ente (regione o comune) e anno; per
     * l'INPS uno per causale degli interessi, posizione e periodo.
     */
    public static function getChiaveInteressi(Versamento $versamento): string
    {
        if ($versamento->sezione === SezioneF24::inps) {
            $causaleInteressi = $versamento->causale;
            foreach (self::CAUSALI_INPS as $causale => [$causaleRate, $interessi]) {
                if (in_array($versamento->causale, [$causale, $causaleRate], true)) {
                    $causaleInteressi = $interessi;
                }
            }
            return implode('|', ['INP', $causaleInteressi, $versamento->codiceSede, $versamento->posizione, $versamento->periodoDa, $versamento->periodoA]);
        }
        return implode('|', [$versamento->sezione->value, $versamento->codiceEnte, $versamento->annoRiferimento, $versamento->codiceFiscaleCoobbligato]);
    }

    private function creaInteressi(Versamento $originale, \DateTimeInterface $data, int $numero, int $numeroRate, float $importo): Versamento
    {
        $rigo = new Versamento($this->dataMapper);
        $rigo->sezione = $originale->sezione;
        $rigo->scadenza = self::getDataVersamento($data);
        $rigo->importoDebito = $importo;
        $rigo->codiceFiscaleCoobbligato = $originale->codiceFiscaleCoobbligato;
        $rigo->codiceIdentificativo = $originale->codiceIdentificativo;
        $rigo->descrizione = 'Interessi rata ' . $numero . '/' . $numeroRate;
        if ($originale->sezione === SezioneF24::inps) {
            foreach (['codiceSede', 'posizione', 'periodoDa', 'periodoA'] as $campo) {
                $rigo->$campo = $originale->$campo;
            }
            $rigo->causale = self::CAUSALI_INPS[$originale->causale][1];
            return $rigo;
        }
        $codice = match ($originale->sezione) {
            SezioneF24::erario => self::CODICE_INTERESSI_ERARIO,
            SezioneF24::regioni => self::CODICE_INTERESSI_REGIONI,
            default => self::CODICE_INTERESSI_ADDIZIONALE_COMUNALE,
        };
        $rigo->codiceTributo = $this->getCodice($originale->sezione, $codice);
        $rigo->codiceEnte = $originale->codiceEnte;
        $rigo->annoRiferimento = $originale->annoRiferimento;
        if ($rigo->codiceTributo->formatoRateazione === 'NNRR') {
            $rigo->rateazione = sprintf('%02d%02d', $numero, $numeroRate);
        }
        return $rigo;
    }

    private function getCodice(SezioneF24 $sezione, string $codice): CodiceTributo
    {
        $trovato = $this->codici !== null ? ($this->codici)($sezione, $codice)
                : (new CodiceTributoModel($this->dataMapper))->getByCodice($sezione->getSezioneCodici(), $codice);
        if ($trovato === null) {
            throw new \InvalidArgumentException('Codice tributo degli interessi ' . $codice . ' non presente nelle tabelle: aggiornarle con sisma tabelle-f24.');
        }
        return $trovato;
    }
}
