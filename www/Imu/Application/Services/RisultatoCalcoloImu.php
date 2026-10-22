<?php

namespace Imu\Application\Services;

use Imu\Application\Entities\RigoCalcoloImu;
use Imu\Application\Enumerations\FattispecieImu;

/**
 * Esito di CalcoloImposta: righi del prospetto (non salvati) e avvisi per
 * lo studio; i tributi sono i righi del modello F24 per comune e codice.
 */
class RisultatoCalcoloImu
{

    /** @var RigoCalcoloImu[] */
    public array $righi = [];

    /** @var string[] */
    public array $avvisi = [];

    /** @var array<string, string> denominazione per codice catastale */
    public array $comuni = [];

    public function avviso(string $testo): void
    {
        if (in_array($testo, $this->avvisi, true) === false) {
            $this->avvisi[] = $testo;
        }
    }

    /**
     * Importi per comune e codice tributo, al netto della detrazione: per
     * il gruppo D la quota dello Stato (3925) e quella del comune (3930).
     *
     * @return array<string, array{comune: string, codice: string, fattispecie: FattispecieImu, annuo: float, acconto: float, detrazioneAnnua: float, detrazioneAcconto: float, immobili: int[]}>
     */
    public function getTributi(): array
    {
        $tributi = [];
        foreach ($this->righi as $rigo) {
            if ($rigo->isImponibile() === false) {
                continue;
            }
            $quote = [$rigo->fattispecie->getCodiceTributo() => [$rigo->impostaAnnua - $rigo->quotaStatoAnnua - $rigo->detrazioneAnnua, $rigo->impostaAcconto - $rigo->quotaStatoAcconto - $rigo->detrazioneAcconto, $rigo->detrazioneAnnua, $rigo->detrazioneAcconto]];
            if ($rigo->fattispecie->getCodiceTributoStato() !== null) {
                $quote[$rigo->fattispecie->getCodiceTributoStato()] = [$rigo->quotaStatoAnnua, $rigo->quotaStatoAcconto, 0.0, 0.0];
            }
            foreach ($quote as $codice => [$annuo, $acconto, $detrazioneAnnua, $detrazioneAcconto]) {
                $chiave = $rigo->codiceComune . '|' . $codice;
                $tributi[$chiave] ??= ['comune' => $rigo->codiceComune, 'codice' => (string) $codice, 'fattispecie' => $rigo->fattispecie, 'annuo' => 0.0, 'acconto' => 0.0, 'detrazioneAnnua' => 0.0, 'detrazioneAcconto' => 0.0, 'immobili' => []];
                $tributi[$chiave]['annuo'] = round($tributi[$chiave]['annuo'] + $annuo, 2);
                $tributi[$chiave]['acconto'] = round($tributi[$chiave]['acconto'] + $acconto, 2);
                $tributi[$chiave]['detrazioneAnnua'] = round($tributi[$chiave]['detrazioneAnnua'] + $detrazioneAnnua, 2);
                $tributi[$chiave]['detrazioneAcconto'] = round($tributi[$chiave]['detrazioneAcconto'] + $detrazioneAcconto, 2);
                $tributi[$chiave]['immobili'][$rigo->immobile->id] = $rigo->immobile->id;
            }
        }
        ksort($tributi);
        return $tributi;
    }

    /**
     * Imposta annua netta per comune.
     *
     * @return array<string, float>
     */
    public function getTotaliComune(): array
    {
        $totali = [];
        foreach ($this->getTributi() as $tributo) {
            $totali[$tributo['comune']] = round(($totali[$tributo['comune']] ?? 0) + $tributo['annuo'], 2);
        }
        return $totali;
    }
}
