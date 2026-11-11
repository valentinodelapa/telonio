<?php

namespace Dichiarativi\Application\Services\Redditi;

use Dichiarativi\Application\Entities\AttivitaEstera;
use Dichiarativi\Application\Enumerations\CodiceInvestimentoEstero;
use Dichiarativi\Application\Services\TabelleRiferimento\StatiEsteriRedditi;

/**
 * Righi da RW1 a RW5 dalle attività estere, con le regole delle
 * specifiche tecniche del modello Redditi PF 2026 (quadro RW, colonne 29
 * e seguenti) e delle istruzioni del fascicolo 2:
 *
 * - IVAFE (col. 29): 0,20 per cento del valore finale (0,40 nei Paesi a
 *   fiscalità privilegiata, col. 21) per quota e giorni/365; per i conti
 *   correnti e i libretti 34,20 euro per quota e giorni/365 se il valore
 *   medio di giacenza complessivo presso lo stesso intermediario (pro
 *   quota) supera 5.000 euro, valore indicato nella col. 8 di ogni conto;
 * - IVIE (col. 31): 1,06 per cento del valore per quota e mesi/12, se
 *   l'1,06 per cento del valore supera 200 euro; abitazione principale di
 *   categoria A/1, A/8 o A/9 (codice 19) allo 0,40 per cento con la
 *   detrazione di 200 euro (col. 13) per il periodo e la quota; non dovuta
 *   dal nudo proprietario;
 * - imposta sulle cripto-attività (col. 33): 0,20 per cento per quota e
 *   giorni/365;
 * - imposte dovute (col. 30, 32, 34) al netto del credito per l'imposta
 *   patrimoniale estera (col. 12), che non supera l'imposta.
 *
 * I conti correnti non si indicano se il valore massimo complessivo (pro
 * quota) non supera 15.000 euro e l'IVAFE non è dovuta (art. 2 L. 186/2014).
 */
final class CalcoloQuadroRw
{

    public const SOGLIA_MONITORAGGIO_CONTI = 15000.0;
    public const SOGLIA_IVAFE_CONTI = 5000.0;
    public const IVAFE_CONTI = 34.20;

    /** @var string[] */
    private array $avvisi = [];

    /**
     * @return string[]
     */
    public function getAvvisi(): array
    {
        return $this->avvisi;
    }

    /**
     * @param AttivitaEstera[] $attivita
     * @return list<array<int, int|string|bool|null>> colonne per rigo (1-34)
     */
    public function righi(array $attivita): array
    {
        $this->avvisi = [];
        $giacenze = [];
        $massimoConti = 0.0;
        foreach ($attivita as $voce) {
            if ($voce->codiceInvestimento === CodiceInvestimentoEstero::contiCorrenti) {
                $chiave = self::intermediario($voce);
                $giacenze[$chiave] = ($giacenze[$chiave] ?? 0.0) + $voce->valoreFinale * $voce->quota / 100;
                $massimoConti += ($voce->valoreMassimo ?? $voce->valoreFinale) * $voce->quota / 100;
            }
        }
        $righi = [];
        foreach ($attivita as $voce) {
            $rigo = $this->rigo($voce, $giacenze);
            if (($voce->codiceInvestimento === CodiceInvestimentoEstero::contiCorrenti) && ($massimoConti <= self::SOGLIA_MONITORAGGIO_CONTI) && ((int) ($rigo[30] ?? 0) === 0)) {
                $this->avvisi['conti'] = 'Conti correnti esteri con un valore massimo complessivo non superiore a 15.000 euro e senza IVAFE: non si indicano nel quadro RW.';
                continue;
            }
            $righi[] = $rigo;
        }
        return $righi;
    }

    /**
     * @param array<string, float> $giacenze valore medio complessivo dei conti per intermediario
     * @return array<int, int|string|bool|null>
     */
    private function rigo(AttivitaEstera $voce, array $giacenze): array
    {
        $codice = $voce->codiceInvestimento;
        $quota = $voce->quota / 100;
        $privilegiato = StatiEsteriRedditi::isPrivilegiato($voce->codiceStatoEstero);
        $valoreFinale = $codice === CodiceInvestimentoEstero::contiCorrenti ? $giacenze[self::intermediario($voce)] : $voce->valoreFinale;
        $rigo = [
            1 => $voce->titoloPossesso,
            2 => $voce->tipoContribuente,
            3 => $codice->value,
            4 => $codice === CodiceInvestimentoEstero::criptoAttivita ? null : $voce->codiceStatoEstero,
            5 => round($voce->quota, 2),
            6 => $voce->criterioValore,
            7 => (int) round($voce->valoreIniziale),
            8 => (int) round($valoreFinale),
            9 => (($codice === CodiceInvestimentoEstero::contiCorrenti) && $privilegiato && ($voce->valoreMassimo !== null)) ? (int) round($voce->valoreMassimo) : null,
            14 => $voce->codiceQuadri ?? (($voce->proventi ?? 0) > 0 ? 2 : ($codice->isFinanziario() ? 5 : null)),
            15 => $voce->quotaPartecipazione === null ? null : round($voce->quotaPartecipazione, 2),
            17 => $voce->codiceFiscaleEntita,
            18 => $voce->cointestatario1,
            19 => $voce->cointestatario2,
            20 => ($voce->piuCointestatari && ($voce->cointestatario1 !== null) && ($voce->cointestatario2 !== null)) ? true : null,
            21 => (($codice->isIvafeProporzionale()) && $privilegiato) ? true : null,
        ];
        $nudaProprieta = $codice->isIvie() && ($voce->titoloPossesso === 3);
        if ($voce->soloMonitoraggio || $codice->isSoloMonitoraggio() || $nudaProprieta) {
            $rigo[16] = true;
            return $rigo;
        }
        $credito = (float) ($voce->creditoImposta ?? 0);
        if ($codice->isIvie()) {
            $mesi = $voce->getMesi();
            $aliquota = $codice === CodiceInvestimentoEstero::abitazionePrincipale ? 0.004 : 0.0106;
            $imposta = (($codice === CodiceInvestimentoEstero::immobili) && ($voce->valoreFinale * 0.0106 <= 200)) ? 0.0 : $voce->valoreFinale * $aliquota * $quota * $mesi / 12;
            $detrazione = $codice === CodiceInvestimentoEstero::abitazionePrincipale ? min(200.0, 200 * ($voce->mesiAbitazionePrincipale ?? $mesi) / 12 * $quota) : 0.0;
            $rigo[11] = $mesi;
            $rigo[31] = (int) round($imposta);
            $rigo[12] = $credito > 0 ? (int) round(min($credito, $imposta)) : null;
            $rigo[13] = $detrazione > 0 ? (int) round($detrazione) : null;
            $rigo[32] = (int) max(0, $rigo[31] - (int) ($rigo[12] ?? 0) - (int) ($rigo[13] ?? 0));
            return $rigo;
        }
        $giorni = $voce->getGiorni();
        $rigo[10] = $giorni;
        if ($codice === CodiceInvestimentoEstero::criptoAttivita) {
            $imposta = $voce->valoreFinale * 0.002 * $quota * $giorni / 365;
            $rigo[33] = (int) round($imposta);
            $rigo[12] = $credito > 0 ? (int) round(min($credito, $imposta)) : null;
            $rigo[34] = (int) max(0, $rigo[33] - (int) ($rigo[12] ?? 0));
            return $rigo;
        }
        if ($codice === CodiceInvestimentoEstero::contiCorrenti) {
            $imposta = $valoreFinale > self::SOGLIA_IVAFE_CONTI ? self::IVAFE_CONTI * $quota * $giorni / 365 : 0.0;
        } else {
            $imposta = $voce->valoreFinale * ($privilegiato ? 0.004 : 0.002) * $quota * $giorni / 365;
        }
        $rigo[29] = (int) round($imposta);
        $rigo[12] = $credito > 0 ? (int) round(min($credito, $imposta)) : null;
        $rigo[30] = (int) max(0, $rigo[29] - (int) ($rigo[12] ?? 0));
        return $rigo;
    }

    private static function intermediario(AttivitaEstera $voce): string
    {
        $nome = mb_strtoupper(trim((string) $voce->intermediario));
        return $nome === '' ? 'id-' . spl_object_id($voce) : $nome;
    }
}
