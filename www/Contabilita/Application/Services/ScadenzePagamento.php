<?php

namespace Contabilita\Application\Services;

use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Enumerations\ModalitaPagamento;

/**
 * Rate (scadenze) delle partite dei documenti. Una rata è
 * ['data' => 'AAAA-MM-GG', 'importo' => float, 'modalita' => ?codice MP].
 *
 * Proposta, in ordine:
 *
 * - dati di pagamento della fattura elettronica (DettaglioPagamento):
 *   scadenza indicata o data di riferimento più i giorni dei termini; gli
 *   importi della fattura (al netto, ad esempio, di ritenute o della
 *   scissione dei pagamenti) si riportano in proporzione all'importo della
 *   partita;
 * - termini abituali della controparte: una rata per ciascun numero di
 *   giorni dalla data del documento ("30,60,90"), a fine mese se indicato
 *   (30 giorni fine mese di una fattura del 10 gennaio: 28 febbraio), con
 *   importi uguali e il resto sull'ultima;
 * - altrimenti una rata alla data del documento.
 *
 * Il residuo della partita si ripartisce sulle rate in ordine di scadenza:
 * gli abbinamenti chiudono prima le rate più vecchie.
 */
final class ScadenzePagamento
{

    public const MASSIMO_RATE = 24;
    public const MASSIMO_GIORNI = 730;
    private const TOLLERANZA = 0.005;

    /**
     * @return int[]|null giorni dei termini ("30,60,90"), null se non validi
     */
    public static function leggiGiorni(?string $giorni): ?array
    {
        $giorni = trim((string) $giorni);
        if ($giorni === '') {
            return [];
        }
        $valori = array_map('trim', explode(',', $giorni));
        foreach ($valori as $valore) {
            if ((ctype_digit($valore) === false) || ((int) $valore > self::MASSIMO_GIORNI)) {
                return null;
            }
        }
        $valori = array_map('intval', $valori);
        if ((count($valori) > self::MASSIMO_RATE) || ($valori !== array_values(array_unique($valori))) || ($valori !== self::ordinati($valori))) {
            return null;
        }
        return $valori;
    }

    /**
     * Rate dai termini abituali della controparte (vuoto se non ne ha).
     *
     * @return array<int, array{data: string, importo: float, modalita: ?string}>
     */
    public static function daTermini(Controparte $controparte, \DateTimeInterface $dataDocumento, float $importo): array
    {
        $giorni = self::leggiGiorni($controparte->giorniPagamento) ?? [];
        if (count($giorni) === 0) {
            return [];
        }
        $importi = self::dividi($importo, array_fill(0, count($giorni), 1.0));
        $rate = [];
        foreach ($giorni as $indice => $numero) {
            $data = (new \DateTimeImmutable($dataDocumento->format('Y-m-d')))->modify('+' . $numero . ' days');
            if ($controparte->fineMesePagamento) {
                $data = $data->modify('last day of this month');
            }
            $rate[] = ['data' => $data->format('Y-m-d'), 'importo' => $importi[$indice], 'modalita' => $controparte->modalitaPagamento?->value];
        }
        return $rate;
    }

    /**
     * Rate dai dati di pagamento della fattura elettronica, riportate
     * all'importo della partita (vuoto se la fattura non ne ha).
     *
     * @param array<int, array{modalita: ?ModalitaPagamento, scadenza: ?\DateTimeImmutable, importo: float}> $pagamenti
     * @return array<int, array{data: string, importo: float, modalita: ?string}>
     */
    public static function daFattura(array $pagamenti, \DateTimeInterface $dataDocumento, float $importo): array
    {
        if (count($pagamenti) === 0) {
            return [];
        }
        usort($pagamenti, fn(array $primo, array $secondo) => ($primo['scadenza'] ?? $dataDocumento)->format('Y-m-d') <=> ($secondo['scadenza'] ?? $dataDocumento)->format('Y-m-d'));
        $pesi = array_map(fn(array $pagamento) => max(0.0, $pagamento['importo']), $pagamenti);
        $importi = self::dividi($importo, array_sum($pesi) > 0 ? $pesi : array_fill(0, count($pagamenti), 1.0));
        $rate = [];
        foreach ($pagamenti as $indice => $pagamento) {
            $rate[] = ['data' => ($pagamento['scadenza'] ?? $dataDocumento)->format('Y-m-d'), 'importo' => $importi[$indice], 'modalita' => $pagamento['modalita']?->value];
        }
        return $rate;
    }

    /**
     * Rate valide per la partita: ordinate per data; la rata unica prende
     * l'importo della partita (che cambia se si modificano le righe).
     *
     * @param array<int, array{data: string, importo: float, modalita: ?string}> $rate
     * @return array<int, array{data: string, importo: float, modalita: ?string}>
     */
    public static function normalizza(array $rate, float $importo): array
    {
        $rate = array_values($rate);
        usort($rate, fn(array $prima, array $seconda) => $prima['data'] <=> $seconda['data']);
        if (count($rate) === 1) {
            $rate[0]['importo'] = round($importo, 2);
        }
        return $rate;
    }

    /**
     * @return string|null errore delle rate rispetto all'importo della partita
     */
    public static function verifica(array $rate, float $importo): ?string
    {
        if (count($rate) > self::MASSIMO_RATE) {
            return 'al massimo ' . self::MASSIMO_RATE . ' rate.';
        }
        $totale = 0.0;
        foreach ($rate as $rata) {
            $data = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $rata['data']);
            if (($data === false) || ($data->format('Y-m-d') !== $rata['data'])) {
                return 'indicare la data di ogni scadenza.';
            }
            if ($rata['importo'] <= 0) {
                return 'gli importi delle scadenze sono positivi.';
            }
            if (($rata['modalita'] !== null) && (ModalitaPagamento::tryFrom($rata['modalita']) === null)) {
                return 'modalità di pagamento non valida.';
            }
            $totale += $rata['importo'];
        }
        if ((count($rate) > 0) && (abs(round($totale, 2) - round($importo, 2)) > self::TOLLERANZA)) {
            return 'le scadenze sommano ' . number_format($totale, 2, ',', '.') . ' su un importo di ' . number_format($importo, 2, ',', '.') . '.';
        }
        return null;
    }

    /**
     * Residuo di ciascuna rata: l'importo abbinato chiude le rate in ordine
     * di scadenza.
     *
     * @param float[] $importi importi delle rate in ordine di scadenza
     * @return float[] residui nello stesso ordine
     */
    public static function residui(array $importi, float $abbinato): array
    {
        $residui = [];
        $disponibile = round($abbinato, 2);
        foreach ($importi as $chiave => $importo) {
            $chiuso = min($importo, max(0.0, $disponibile));
            $residui[$chiave] = round($importo - $chiuso, 2);
            $disponibile = round($disponibile - $chiuso, 2);
        }
        return $residui;
    }

    /**
     * Ripartisce l'importo in proporzione ai pesi, con il resto
     * dell'arrotondamento sull'ultima quota.
     *
     * @param float[] $pesi
     * @return float[]
     */
    public static function dividi(float $importo, array $pesi): array
    {
        $totale = array_sum($pesi);
        $quote = [];
        $assegnato = 0.0;
        $ultima = array_key_last($pesi);
        foreach ($pesi as $chiave => $peso) {
            $quota = $chiave === $ultima ? round($importo - $assegnato, 2) : round($importo * $peso / $totale, 2);
            $quote[$chiave] = $quota;
            $assegnato = round($assegnato + $quota, 2);
        }
        return $quote;
    }

    /**
     * @param int[] $valori
     * @return int[]
     */
    private static function ordinati(array $valori): array
    {
        sort($valori);
        return $valori;
    }
}
