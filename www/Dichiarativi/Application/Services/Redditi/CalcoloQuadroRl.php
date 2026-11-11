<?php

namespace Dichiarativi\Application\Services\Redditi;

use Dichiarativi\Application\Entities\CertificazioneAutonomo;

/**
 * Quadro RL dalle Certificazioni Uniche del lavoro autonomo e dei redditi
 * diversi, con le regole delle specifiche tecniche del modello Redditi PF
 * 2026 e delle istruzioni del quadro (fascicolo 2):
 *
 * - sezione II-A: righi RL14 (V1), RL15 (M, M2, O) e RL16 (M1, O1) con i
 *   compensi imponibili e le spese inerenti (non oltre i compensi), totali
 *   RL18, reddito netto RL19 e ritenute d'acconto RL20 (anche sospese);
 * - sezione II-B (N): compensi RL21, parte a titolo d'imposta (da 10.000 a
 *   30.658 euro) e imponibile oltre 30.658 euro nel rigo RL22, ritenute
 *   RL23 (d'acconto: ritenute meno il 23 per cento della parte a titolo
 *   d'imposta) e addizionali trattenute RL24, colonne 1 e 3;
 * - sezione III: righi RL25 (B), RL26 (E codice 1, F codice 2) e RL27 (C,
 *   D), totale RL30, deduzioni forfetarie RL31 (25 per cento dei proventi
 *   del rigo RL25, 40 per cento se il contribuente è nato dopo il 1990, 15
 *   per cento dei compensi del codice 1 del rigo RL26), netto RL32 e
 *   ritenute RL33.
 *
 * Le causali L e L1 (rigo RL13), i redditi percepiti a Campione d'Italia e
 * le addizionali dovute della sezione II-B (RL24, colonne 2 e 4) restano a
 * mano, con un avviso.
 */
final class CalcoloQuadroRl
{

    public const ESENZIONE_DILETTANTI = 10000.0;
    public const LIMITE_RITENUTA_IMPOSTA = 30658.28;
    public const ALIQUOTA_DILETTANTI = 0.23;

    private const RIGHI_OCCASIONALI = ['V1' => 14, 'M' => 15, 'M2' => 15, 'O' => 15, 'M1' => 16, 'O1' => 16];

    /** @var string[] */
    private array $avvisi = [];

    /**
     * @return string[]
     */
    public function getAvvisi(): array
    {
        return array_values($this->avvisi);
    }

    /**
     * @param CertificazioneAutonomo[] $certificazioni
     * @return array<string, int>
     */
    public function calcola(array $certificazioni, bool $natoDopo1990): array
    {
        $this->avvisi = [];
        $redditi = $spese = [];
        $ritenuteOccasionali = 0.0;
        $dilettanti = ['compensi' => 0.0, 'ritenute' => 0.0, 'regionale' => 0.0, 'comunale' => 0.0];
        $autonomo = ['RL025002' => 0.0, 'RL027002' => 0.0, 'protesti' => 0.0, 'giudici' => 0.0, 'ritenute' => 0.0];
        foreach ($certificazioni as $certificazione) {
            $causale = $certificazione->causale;
            $imponibile = $certificazione->getImponibile();
            if (isset(self::RIGHI_OCCASIONALI[$causale])) {
                $rigo = self::RIGHI_OCCASIONALI[$causale];
                $redditi[$rigo] = ($redditi[$rigo] ?? 0.0) + $imponibile;
                $spese[$rigo] = ($spese[$rigo] ?? 0.0) + $certificazione->speseInerenti;
                $ritenuteOccasionali += $certificazione->ritenuteAcconto + $certificazione->ritenuteSospese;
            } elseif ($causale === 'N') {
                $dilettanti['compensi'] += $certificazione->ammontareLordo;
                $dilettanti['ritenute'] += $certificazione->ritenuteAcconto + $certificazione->ritenuteImposta;
                $dilettanti['regionale'] += $certificazione->addizionaleRegionale;
                $dilettanti['comunale'] += $certificazione->addizionaleComunale;
            } elseif (in_array($causale, ['B', 'C', 'D', 'E', 'F'], true)) {
                $chiave = match ($causale) {
                    'B' => 'RL025002',
                    'C', 'D' => 'RL027002',
                    'E' => 'protesti',
                    'F' => 'giudici',
                };
                $autonomo[$chiave] += $certificazione->ammontareLordo;
                $autonomo['ritenute'] += $certificazione->ritenuteAcconto + $certificazione->ritenuteSospese;
            } elseif (in_array($causale, ['L', 'L1'], true)) {
                $this->avvisi['L'] = 'Certificazioni con causale L o L1 (opere dell\'ingegno degli aventi causa): rigo RL13 da compilare a mano.';
            } else {
                $this->avvisi[$causale] = 'Certificazione con causale ' . $causale . ': i compensi non vanno nel quadro RL.';
            }
        }
        $campi = [];
        // sezione II-A
        $totaleRedditi = $totaleSpese = 0;
        foreach ($redditi as $rigo => $importo) {
            $reddito = (int) round($importo);
            $deduzioni = min($reddito, (int) round($spese[$rigo]));
            $campi[sprintf('RL%03d002', $rigo)] = $reddito;
            $campi[sprintf('RL%03d003', $rigo)] = $deduzioni;
            $totaleRedditi += $reddito;
            $totaleSpese += $deduzioni;
        }
        if (count($redditi) > 0) {
            $campi['RL018001'] = $totaleRedditi;
            $campi['RL018002'] = $totaleSpese;
            $campi['RL019001'] = max(0, $totaleRedditi - $totaleSpese);
            $campi['RL020001'] = (int) round($ritenuteOccasionali);
        }
        // sezione II-B
        if ($dilettanti['compensi'] > self::ESENZIONE_DILETTANTI) {
            $compensi = (int) round($dilettanti['compensi']);
            $campi['RL021001'] = $compensi;
            $campi['RL022001'] = (int) round(min($compensi, self::LIMITE_RITENUTA_IMPOSTA) - self::ESENZIONE_DILETTANTI);
            $campi['RL022002'] = (int) max(0, round($compensi - self::LIMITE_RITENUTA_IMPOSTA));
            $campi['RL023001'] = (int) round($dilettanti['ritenute']);
            $campi['RL023002'] = (int) max(0, round($dilettanti['ritenute'] - $campi['RL022001'] * self::ALIQUOTA_DILETTANTI));
            $campi['RL024001'] = (int) round($dilettanti['regionale']);
            $campi['RL024003'] = (int) round($dilettanti['comunale']);
            $this->avvisi['N'] = 'Compensi dei dilettanti oltre 10.000 euro: addizionali dovute sulla parte a titolo d\'imposta (rigo RL24, colonne 2 e 4) da calcolare a mano con le aliquote del domicilio fiscale.';
        }
        // sezione III
        $totale = (int) round($autonomo['RL025002']) + (int) round($autonomo['RL027002']) + (int) round($autonomo['protesti'] + $autonomo['giudici']);
        if ($totale > 0) {
            if ($autonomo['RL025002'] > 0) {
                $campi['RL025002'] = (int) round($autonomo['RL025002']);
            }
            if ($autonomo['protesti'] + $autonomo['giudici'] > 0) {
                if (($autonomo['protesti'] > 0) && ($autonomo['giudici'] > 0)) {
                    $this->avvisi['RL26'] = 'Compensi delle causali E ed F: il secondo codice del rigo RL26 va in un altro modulo, compilato a mano.';
                }
                $campi['RL026002'] = $autonomo['protesti'] > 0 ? 1 : 2;
                $campi['RL026003'] = (int) round($autonomo['protesti'] > 0 ? $autonomo['protesti'] : $autonomo['giudici']);
            }
            if ($autonomo['RL027002'] > 0) {
                $campi['RL027002'] = (int) round($autonomo['RL027002']);
            }
            $campi['RL030001'] = ($campi['RL025002'] ?? 0) + ($campi['RL026003'] ?? 0) + ($campi['RL027002'] ?? 0);
            $deduzioni = ($campi['RL025002'] ?? 0) * ($natoDopo1990 ? 0.40 : 0.25) + (($campi['RL026002'] ?? 0) === 1 ? $campi['RL026003'] * 0.15 : 0);
            $campi['RL031001'] = (int) round($deduzioni);
            $campi['RL032001'] = $campi['RL030001'] - $campi['RL031001'];
            $campi['RL033001'] = (int) round($autonomo['ritenute']);
        }
        return array_filter($campi, fn(int $valore) => $valore !== 0);
    }
}
