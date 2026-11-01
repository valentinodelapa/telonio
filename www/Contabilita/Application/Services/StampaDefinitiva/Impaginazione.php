<?php

namespace Contabilita\Application\Services\StampaDefinitiva;

/**
 * Suddivisione in pagine di un numero fisso di righe dei blocchi di una
 * stampa (tabelle con titolo e intestazione delle colonne). Ogni riga
 * occupa una sola linea (testi troncati nella stampa), così il numero
 * delle pagine non dipende dal browser e la numerazione registrata resta
 * quella stampata.
 *
 * Blocco: ['titolo' => ?string, 'colonne' => [['etichetta' => string,
 * 'numero' => bool, 'larghezza' => int], ...], 'righe' => [['celle' =>
 * array, 'classe' => ?string, 'senzaRiporto' => ?bool], ...], 'riporto' =>
 * ?array<int, float>]. Con il riporto (indici delle colonne numeriche con
 * i valori iniziali) le parti del blocco dopo la prima si aprono con la
 * riga "Riporto" e quelle che continuano si chiudono con "A riportare".
 */
final class Impaginazione
{

    /**
     * @param array<int, array<string, mixed>> $blocchi
     * @return array<int, array{numero: int, parti: array<int, array<string, mixed>>}>
     */
    public static function impagina(array $blocchi, int $righePerPagina, int $primaPagina): array
    {
        $pagine = [];
        $parti = [];
        $libere = $righePerPagina;
        foreach ($blocchi as $blocco) {
            $righe = array_values($blocco['righe']);
            $riporto = $blocco['riporto'] ?? null;
            $titolo = $blocco['titolo'] ?? null;
            $posizione = 0;
            $prima = true;
            while (true) {
                $conRiporto = ($riporto !== null) && (($prima === false) || (array_sum($riporto) != 0));
                $intestazione = ($titolo === null ? 0 : 1) + 1 + ($conRiporto ? 1 : 0);
                $restanti = count($righe) - $posizione;
                $minimo = min(1, $restanti) + ((($riporto !== null) && ($restanti > $libere - $intestazione)) ? 1 : 0);
                if (($libere - $intestazione < $minimo) && (count($parti) > 0)) {
                    $pagine[] = ['numero' => $primaPagina + count($pagine), 'parti' => $parti];
                    $parti = [];
                    $libere = $righePerPagina;
                }
                $capacita = $libere - $intestazione;
                $continua = $restanti > $capacita;
                $quante = $continua ? max(1, $capacita - ($riporto === null ? 0 : 1)) : $restanti;
                $selezione = array_slice($righe, $posizione, $quante);
                $parte = ['titolo' => $titolo === null ? null : $titolo . ($prima ? '' : ' (segue)'), 'colonne' => $blocco['colonne'],
                    'riporto' => $conRiporto ? $riporto : null, 'righe' => $selezione, 'aRiportare' => null];
                if ($riporto !== null) {
                    foreach ($selezione as $riga) {
                        foreach (array_keys(($riga['senzaRiporto'] ?? false) ? [] : $riporto) as $colonna) {
                            $riporto[$colonna] = round($riporto[$colonna] + (float) ($riga['celle'][$colonna] ?? 0), 2);
                        }
                    }
                    if ($continua) {
                        $parte['aRiportare'] = $riporto;
                    }
                }
                $parti[] = $parte;
                $libere -= $intestazione + $quante + ($parte['aRiportare'] === null ? 0 : 1);
                $posizione += $quante;
                $prima = false;
                if ($posizione >= count($righe)) {
                    break;
                }
                $pagine[] = ['numero' => $primaPagina + count($pagine), 'parti' => $parti];
                $parti = [];
                $libere = $righePerPagina;
            }
        }
        if (count($parti) > 0) {
            $pagine[] = ['numero' => $primaPagina + count($pagine), 'parti' => $parti];
        }
        return $pagine;
    }
}
