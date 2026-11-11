<?php

namespace Dichiarativi\Application\Services\TabelleRiferimento;

/**
 * Codici degli Stati e territori esteri del modello Redditi (tabella 10
 * "Elenco dei Paesi e dei territori esteri" delle istruzioni del fascicolo
 * 1 del modello Redditi PF 2026), con i Paesi a fiscalità privilegiata del
 * paragrafo 36 delle specifiche tecniche (quadro RW, colonna 21). Sono
 * codici dell'Agenzia diversi dai codici catastali e ANPR dell'Anagrafica:
 * sorgente Database/sorgenti/stati-esteri-redditi.csv.
 */
final class StatiEsteriRedditi
{

    /** @var array<string, array{denominazione: string, privilegiato: bool}>|null */
    private static ?array $elenco = null;

    /**
     * @return array<string, string> codice => denominazione, in ordine alfabetico
     */
    public static function getElenco(): array
    {
        $elenco = array_map(fn(array $stato) => $stato['denominazione'], self::carica());
        asort($elenco);
        return $elenco;
    }

    public static function esiste(?string $codice): bool
    {
        return ($codice !== null) && isset(self::carica()[$codice]);
    }

    public static function getDenominazione(?string $codice): ?string
    {
        return $codice === null ? null : (self::carica()[$codice]['denominazione'] ?? null);
    }

    public static function isPrivilegiato(?string $codice): bool
    {
        return ($codice !== null) && (self::carica()[$codice]['privilegiato'] ?? false);
    }

    /**
     * @return array<string, array{denominazione: string, privilegiato: bool}>
     */
    private static function carica(): array
    {
        if (self::$elenco === null) {
            self::$elenco = [];
            $file = fopen(dirname(__DIR__, 3) . '/Database/sorgenti/stati-esteri-redditi.csv', 'r');
            fgetcsv($file, null, ';', '"', '');
            while (($riga = fgetcsv($file, null, ';', '"', '')) !== false) {
                if (count($riga) >= 3) {
                    self::$elenco[$riga[0]] = ['denominazione' => $riga[1], 'privilegiato' => $riga[2] === '1'];
                }
            }
            fclose($file);
        }
        return self::$elenco;
    }
}
