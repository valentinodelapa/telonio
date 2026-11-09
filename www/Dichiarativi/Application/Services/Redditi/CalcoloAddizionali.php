<?php

namespace Dichiarativi\Application\Services\Redditi;

use Dichiarativi\Application\Entities\AddizionaleComunale;
use Dichiarativi\Application\Entities\AddizionaleRegionale;

/**
 * Addizionali regionale e comunale all'IRPEF dovute sul reddito imponibile
 * (rigo RV1: reddito complessivo al netto degli oneri deducibili e della
 * deduzione per l'abitazione principale).
 *
 * Regionale (tabella delle istruzioni, quadro RV, rigo RV2): aliquote per
 * scaglioni progressive o, con la modalità "intero", aliquota dello
 * scaglione del reddito sull'intero importo; poi le regole strutturate
 * della tabella (esenzione per reddito complessivo, aliquota unica fino a
 * una soglia, deduzione, detrazioni per fascia di reddito). Le agevolazioni
 * legate ai figli o alla disabilità restano avvisi.
 *
 * Comunale (righi RV10 e RV17): con imponibile fino alla soglia di
 * esenzione non è dovuta; oltre, l'aliquota (o le aliquote per scaglioni,
 * come per l'IRPEF) si applica sull'intero imponibile. L'acconto è il 30
 * per cento con l'aliquota dell'anno d'imposta del comune del domicilio al
 * 1° gennaio dell'anno successivo. I casi particolari dei comuni
 * (tipizzazione) restano avvisi.
 */
class CalcoloAddizionali
{

    /**
     * @return array{importo: float, avvisi: string[]}
     */
    public function regionale(AddizionaleRegionale $tabella, float $imponibile, float $redditoComplessivo): array
    {
        $avvisi = $tabella->casiParticolari === null ? [] : [$tabella->regione . ': ' . $tabella->casiParticolari];
        if (($imponibile <= 0) || (($tabella->esenzioneReddito !== null) && ($redditoComplessivo <= $tabella->esenzioneReddito))) {
            return ['importo' => 0.0, 'avvisi' => $avvisi];
        }
        $base = $imponibile;
        if ($tabella->deduzione !== null) {
            [$importo, $fino] = array_map('floatval', explode(':', $tabella->deduzione));
            if ($imponibile <= $fino) {
                $base = max(0.0, $imponibile - $importo);
            }
        }
        $scaglioni = self::leggiScaglioni($tabella->scaglioni);
        if ($tabella->sogliaAliquota !== null) {
            [$soglia, $aliquota] = array_map('floatval', explode(':', $tabella->sogliaAliquota));
            $imposta = $imponibile <= $soglia ? $base * $aliquota / 100 : self::perScaglioni($base, $scaglioni);
        } elseif ($tabella->modalita === 'intero') {
            $imposta = $base * self::aliquotaDelloScaglione($base, $scaglioni) / 100;
        } else {
            $imposta = self::perScaglioni($base, $scaglioni);
        }
        foreach ($tabella->detrazioni === null ? [] : explode('|', $tabella->detrazioni) as $detrazione) {
            [$importo, $oltre, $fino] = array_pad(explode(':', $detrazione), 3, '');
            if ((($oltre === '') || ($imponibile > (float) $oltre)) && (($fino === '') || ($imponibile <= (float) $fino))) {
                $imposta -= (float) $importo;
            }
        }
        if ($tabella->detrazioneCrescente !== null) {
            [$da, $ampiezza, $massimo] = array_map('floatval', explode(':', $tabella->detrazioneCrescente));
            if ($imponibile > $da) {
                $imposta -= min($massimo, $massimo * ($imponibile - $da) / $ampiezza);
            }
        }
        return ['importo' => round(max(0.0, $imposta), 2), 'avvisi' => $avvisi];
    }

    /**
     * @return array{importo: float, esente: bool, avvisi: string[]}
     */
    public function comunale(?AddizionaleComunale $tabella, float $imponibile): array
    {
        if (($tabella === null) || ($imponibile <= 0)) {
            return ['importo' => 0.0, 'esente' => false, 'avvisi' => []];
        }
        $avvisi = [];
        if ($tabella->tipizzazione !== null) {
            $avvisi[] = sprintf('Casi particolari del comune %s (codice %s%s): %s', $tabella->codiceCatastale, $tabella->tipizzazione,
                    $tabella->esenzioneCasi !== null ? ', soglia ' . number_format($tabella->esenzioneCasi, 2, ',', '.') : '', $tabella->casiParticolari ?? 'vedi la legenda della tabella');
        }
        if (($tabella->esenzione !== null) && ($imponibile <= $tabella->esenzione)) {
            return ['importo' => 0.0, 'esente' => true, 'avvisi' => $avvisi];
        }
        return ['importo' => round(self::perScaglioni($imponibile, $tabella->getScaglioni()), 2), 'esente' => false, 'avvisi' => $avvisi];
    }

    /**
     * Acconto dell'addizionale comunale per l'anno successivo (30 per cento).
     *
     * @return array{importo: float, esente: bool, avvisi: string[]}
     */
    public function accontoComunale(?AddizionaleComunale $tabella, float $imponibile): array
    {
        $saldo = $this->comunale($tabella, $imponibile);
        $saldo['importo'] = round($saldo['importo'] * 0.3, 2);
        return $saldo;
    }

    /**
     * @return list<array{0: ?float, 1: float}>
     */
    private static function leggiScaglioni(string $scaglioni): array
    {
        return array_map(function (string $parte): array {
            [$limite, $aliquota] = explode(':', $parte);
            return [$limite === '' ? null : (float) $limite, (float) $aliquota];
        }, explode('|', $scaglioni));
    }

    /**
     * Imposta progressiva per scaglioni (aliquote in percentuale).
     *
     * @param list<array{0: ?float, 1: float}> $scaglioni
     */
    public static function perScaglioni(float $imponibile, array $scaglioni): float
    {
        $imposta = 0.0;
        $precedente = 0.0;
        foreach ($scaglioni as [$limite, $aliquota]) {
            $tratto = ($limite === null ? $imponibile : min($imponibile, $limite)) - $precedente;
            if ($tratto <= 0) {
                break;
            }
            $imposta += $tratto * $aliquota / 100;
            $precedente = $limite ?? $imponibile;
        }
        return $imposta;
    }

    private static function aliquotaDelloScaglione(float $imponibile, array $scaglioni): float
    {
        foreach ($scaglioni as [$limite, $aliquota]) {
            if (($limite === null) || ($imponibile <= $limite)) {
                return $aliquota;
            }
        }
        return end($scaglioni)[1];
    }
}
