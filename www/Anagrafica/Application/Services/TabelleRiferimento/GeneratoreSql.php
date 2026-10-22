<?php

namespace Anagrafica\Application\Services\TabelleRiferimento;

/**
 * Istruzioni INSERT ... ON DUPLICATE KEY UPDATE sulla chiave unica id_anpr:
 * lo stesso script popola una tabella vuota e aggiorna una già popolata,
 * senza cancellare righe (referenziate dai soggetti) né cambiarne l'id.
 */
class GeneratoreSql
{

    private const RIGHE_PER_ISTRUZIONE = 500;

    /**
     * @param string[] $colonne la prima è la chiave di aggiornamento
     * @param array<array<string, string|int|null>> $righe
     * @return \Generator<string>
     */
    public function generaUpsert(string $tabella, array $colonne, array $righe): \Generator
    {
        $aggiornamenti = implode(', ', array_map(fn($colonna) => "`$colonna` = VALUES(`$colonna`)", array_slice($colonne, 1)));
        foreach (array_chunk($righe, self::RIGHE_PER_ISTRUZIONE) as $blocco) {
            $valori = array_map(fn(array $riga) => '(' . implode(', ', array_map(fn($colonna) => self::valore($riga[$colonna]), $colonne)) . ')', $blocco);
            yield 'INSERT INTO `' . $tabella . '` (`' . implode('`, `', $colonne) . "`) VALUES\n" . implode(",\n", $valori) . "\nON DUPLICATE KEY UPDATE " . $aggiornamenti . ';';
        }
    }

    private static function valore(string|int|null $valore): string
    {
        return match (true) {
            $valore === null => 'NULL',
            is_int($valore) => (string) $valore,
            default => "'" . str_replace(['\\', "'"], ['\\\\', "''"], $valore) . "'",
        };
    }
}
