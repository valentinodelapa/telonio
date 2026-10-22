<?php

namespace Deleghe\Application\Services\TabelleRiferimento;

use Deleghe\Application\Enumerations\DichiarazioneRiferimento;

/**
 * Dati di riferimento senza una fonte leggibile da programma, mantenuti nel
 * modulo (Database/sorgenti) con il riferimento normativo di ogni riga:
 * - saggio degli interessi legali (decreti annuali del Ministro
 *   dell'economia e delle finanze, art. 1284 c.c.);
 * - codici di sanzione e interessi del ravvedimento per tributo
 *   (risoluzioni dell'Agenzia delle Entrate).
 */
class SorgentiModulo
{

    public const CARTELLA = __DIR__ . '/../../../Database/sorgenti/';
    public const COLONNE_TASSI = ['valido_dal', 'tasso', 'provvedimento'];
    public const COLONNE_REGOLE = ['sezione', 'codice', 'codice_sanzione', 'codice_interessi', 'dichiarazione', 'fonte'];

    /**
     * @return array<array<string, string|null>>
     */
    public function leggiTassi(): array
    {
        $tassi = $this->leggiCsv('tassi-interesse-legale.csv', self::COLONNE_TASSI);
        foreach ($tassi as $tasso) {
            if ((\DateTimeImmutable::createFromFormat('!Y-m-d', $tasso['valido_dal']) === false) || (is_numeric($tasso['tasso']) === false)) {
                throw new \UnexpectedValueException('Riga non valida in tassi-interesse-legale.csv: ' . implode(';', $tasso));
            }
        }
        return $tassi;
    }

    /**
     * @param array<string, true> $codiciEsistenti chiavi "sezione|codice" dei codici tributo
     * @return array<array<string, string|null>>
     */
    public function leggiRegoleRavvedimento(array $codiciEsistenti): array
    {
        $regole = $this->leggiCsv('regole-ravvedimento.csv', self::COLONNE_REGOLE);
        foreach ($regole as &$regola) {
            foreach (['codice', 'codice_sanzione', 'codice_interessi'] as $colonna) {
                if (($regola[$colonna] !== null) && (isset($codiciEsistenti[$regola['sezione'] . '|' . $regola[$colonna]]) === false)) {
                    throw new \UnexpectedValueException(sprintf('regole-ravvedimento.csv: il codice %s non esiste nella sezione %s delle tabelle dell\'Agenzia.', $regola[$colonna], $regola['sezione']));
                }
            }
            if (DichiarazioneRiferimento::tryFrom($regola['dichiarazione']) === null) {
                throw new \UnexpectedValueException('regole-ravvedimento.csv: dichiarazione non valida ' . $regola['dichiarazione']);
            }
        }
        return $regole;
    }

    private function leggiCsv(string $nome, array $colonne): array
    {
        $file = fopen(self::CARTELLA . $nome, 'r') ?: throw new \RuntimeException('Impossibile aprire ' . $nome);
        $intestazione = fgetcsv($file, separator: ';', escape: '');
        if ($intestazione !== $colonne) {
            throw new \UnexpectedValueException('Intestazione di ' . $nome . ' diversa da ' . implode(';', $colonne));
        }
        $righe = [];
        while (($valori = fgetcsv($file, separator: ';', escape: '')) !== false) {
            if ($valori === [null]) {
                continue;
            }
            $righe[] = array_map(fn($valore) => trim((string) $valore) === '' ? null : trim($valore), array_combine($colonne, $valori));
        }
        fclose($file);
        return $righe;
    }
}
