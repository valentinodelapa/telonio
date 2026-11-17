<?php

namespace Dichiarativi\Application\Services\TabelleRiferimento;

/**
 * Lettura delle specifiche tecniche degli ISA (allegato 1 del provvedimento
 * di approvazione: un file Excel per ISA, "<codice ISA>_SPECDEF.xls",
 * convertito in testo con xls2csv del pacchetto catdoc) e dell'elenco degli
 * ISA con le attività (tabella 1 delle istruzioni "Parte generale",
 * convertita con pdftotext -layout).
 *
 * Del foglio "esplicitazione campi" si tengono i campi da compilare: tutti
 * tranne i risultati del calcolo (codici che iniziano con "I"), salvo le
 * variabili precalcolate fornite dall'Agenzia (IDF, IFU), che entrano nel
 * file d'ingresso del software di calcolo.
 */
class LettoreSpecificheIsa
{

    public const ESPRESSIONE_ISA = '/^[A-Z]{2}\d{2}[A-Z]$/';
    public const ESPRESSIONE_CODICE = '/^([A-HJ-Z][A-Z0-9]\d{6}|I(DF|FU)\d{5})$/';
    /**
     * Formati delle specifiche ISA con un nome diverso nel catalogo: la
     * percentuale intera "PC" degli ISA è "PC3" (FormatoCampo::percentualeIsa).
     */
    public const FORMATI = ['PC' => 'PC3'];
    /** Prefissi dei risultati del calcolo da tenere: variabili precalcolate */
    private const PRECALCOLATE = ['IDF', 'IFU'];

    /**
     * @param string $testo uscita di "xls2csv -d utf-8 -c ';'" del file delle specifiche di un ISA
     * @return array<string, array{formato: string, descrizione: string, valori: ?string}> campi per codice
     */
    public function leggiCampi(string $testo): array
    {
        $campi = [];
        foreach (explode("\f", $testo) as $foglio) {
            $righe = array_map(fn(string $riga) => str_getcsv($riga, ';', '"', ''), preg_split('/\r?\n/', trim($foglio)) ?: []);
            $intestazione = array_search(['Progressivo', 'Codice campo', 'Descrizione', 'Formato', 'Valore', 'Note'], array_map(fn(array $riga) => array_slice($riga, 0, 6), $righe), true);
            if ($intestazione === false) {
                continue;
            }
            foreach (array_slice($righe, $intestazione + 1) as $riga) {
                $codice = trim((string) ($riga[1] ?? ''));
                if (($codice === '') || (preg_match('/^[A-Z][A-Z0-9]{7}$/', $codice) !== 1)) {
                    continue;
                }
                if (str_starts_with($codice, 'I') && !in_array(substr($codice, 0, 3), self::PRECALCOLATE, true)) {
                    continue;
                }
                $valori = trim((string) ($riga[4] ?? ''));
                $campi[$codice] = ['formato' => trim((string) ($riga[3] ?? '')), 'descrizione' => self::pulisci((string) ($riga[2] ?? '')), 'valori' => $valori === '' ? null : $valori];
            }
        }
        return $campi;
    }

    /**
     * Elenco degli ISA dalla tabella 1 delle istruzioni "Parte generale":
     * riga "DM04U - Descrizione" seguita dalle attività "47.11.02 - ...".
     *
     * @return array<string, array{descrizione: string, attivita: string[]}> ISA per codice
     */
    public function leggiElenco(string $testo): array
    {
        $elenco = [];
        $corrente = null;
        $inizio = mb_stripos($testo, 'ELENCO DEGLI INDICI SINTETICI');
        foreach (preg_split('/\r?\n/', $inizio === false ? $testo : mb_substr($testo, $inizio)) ?: [] as $riga) {
            if (preg_match('/^\s*([A-Z]{2}\d{2}[A-Z]) - (.+)$/u', $riga, $parti) === 1) {
                $corrente = $parti[1];
                $elenco[$corrente] ??= ['descrizione' => self::pulisci($parti[2]), 'attivita' => []];
            } elseif (($corrente !== null) && (preg_match('/^\s*(\d{2}\.\d{2}\.\d{2}) - /', $riga, $parti) === 1)) {
                $elenco[$corrente]['attivita'][] = $parti[1];
            }
        }
        return array_filter($elenco, fn(array $isa) => count($isa['attivita']) > 0);
    }

    private static function pulisci(string $testo): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $testo)));
    }
}
