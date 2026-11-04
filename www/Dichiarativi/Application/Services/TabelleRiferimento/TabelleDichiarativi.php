<?php

namespace Dichiarativi\Application\Services\TabelleRiferimento;

use Anagrafica\Application\Services\TabelleRiferimento\GeneratoreSql;
use Dichiarativi\Application\Enumerations\FormatoCampo;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;

/**
 * Catalogo dei campi e dei quadri dei modelli dalle sorgenti del modulo
 * (Database/sorgenti): specifiche.csv elenca modello, anno, indirizzo del
 * PDF e provvedimento; campi-<modello>-<anno>.csv e quadri-<modello>-<anno>.csv
 * sono ricavati dalle specifiche con SpecificheDichiarativi. La lettura
 * verifica codici, formati e quadri e produce le istruzioni SQL idempotenti
 * per le tabelle campo_modello e quadro_modello.
 */
class TabelleDichiarativi
{

    public const CARTELLA = __DIR__ . '/../../../Database/sorgenti/';
    public const COLONNE_CAMPI = ['codice', 'formato', 'descrizione'];
    public const COLONNE_QUADRI = ['quadro', 'denominazione'];

    public function __construct(private string $cartella = self::CARTELLA)
    {

    }

    /**
     * @return list<array{modello: string, anno: int, url: string, provvedimento: string}>
     */
    public function leggiSpecifiche(): array
    {
        $specifiche = [];
        foreach ($this->leggiCsv('specifiche.csv', SpecificheDichiarativi::COLONNE_SPECIFICHE) as $numero => $riga) {
            $anno = ($riga['anno'] !== null) && (preg_match('/^20\d\d$/', $riga['anno']) === 1) ? (int) $riga['anno'] : null;
            if ((ModelloDichiarativo::tryFrom((string) $riga['modello']) === null) || ($anno === null) || ($riga['url'] === null) || ($riga['provvedimento'] === null)) {
                throw new \UnexpectedValueException('specifiche.csv, riga ' . $numero . ': modello, anno, indirizzo o provvedimento non validi');
            }
            $specifiche[] = ['modello' => $riga['modello'], 'anno' => $anno, 'url' => $riga['url'], 'provvedimento' => $riga['provvedimento']];
        }
        return $specifiche;
    }

    /**
     * @return array{campi: list<array<string, string|int|null>>, quadri: list<array<string, string|int>>, modelli: list<array{modello: string, anno: int}>}
     */
    public function leggi(): array
    {
        $dati = ['campi' => [], 'quadri' => [], 'modelli' => []];
        foreach ($this->leggiSpecifiche() as ['modello' => $modello, 'anno' => $anno]) {
            $nome = $modello . '-' . $anno . '.csv';
            $quadri = [];
            foreach ($this->leggiCsv('quadri-' . $nome, self::COLONNE_QUADRI) as $numero => $riga) {
                if ((preg_match('/^[A-Z]{2}$/', (string) $riga['quadro']) !== 1) || isset($quadri[$riga['quadro']]) || ($riga['denominazione'] === null)) {
                    throw new \UnexpectedValueException('quadri-' . $nome . ', riga ' . $numero . ': quadro non valido o duplicato, o denominazione mancante');
                }
                $quadri[$riga['quadro']] = true;
                $dati['quadri'][] = ['modello' => $modello, 'anno' => $anno, 'quadro' => $riga['quadro'], 'denominazione' => mb_substr($riga['denominazione'], 0, 255)];
            }
            $codici = [];
            foreach ($this->leggiCsv('campi-' . $nome, self::COLONNE_CAMPI) as $numero => $riga) {
                $codice = (string) $riga['codice'];
                if ((preg_match(LettoreSpecifiche::ESPRESSIONE_CODICE, $codice) !== 1) || isset($codici[$codice])) {
                    throw new \UnexpectedValueException('campi-' . $nome . ', riga ' . $numero . ': codice non valido o duplicato');
                }
                if (($riga['formato'] !== null) && (FormatoCampo::tryFrom($riga['formato']) === null)) {
                    throw new \UnexpectedValueException('campi-' . $nome . ', riga ' . $numero . ': formato ' . $riga['formato'] . ' sconosciuto');
                }
                $codici[$codice] = true;
                $dati['campi'][] = ['modello' => $modello, 'anno' => $anno, 'codice' => $codice, 'quadro' => substr($codice, 0, 2),
                    'descrizione' => mb_substr((string) $riga['descrizione'], 0, 500), 'formato' => $riga['formato']];
            }
            if (count($codici) === 0) {
                throw new \UnexpectedValueException('campi-' . $nome . ': nessun campo');
            }
            $dati['modelli'][] = ['modello' => $modello, 'anno' => $anno];
        }
        return $dati;
    }

    /**
     * Istruzioni SQL idempotenti che allineano le tabelle alle sorgenti
     * (i campi e i quadri non più presenti nelle sorgenti sono eliminati).
     *
     * @return \Generator<string>
     */
    public function istruzioni(array $dati): \Generator
    {
        $generatore = new GeneratoreSql();
        foreach ($dati['modelli'] as ['modello' => $modello, 'anno' => $anno]) {
            $campi = array_filter($dati['campi'], fn(array $campo) => ($campo['modello'] === $modello) && ($campo['anno'] === $anno));
            $quadri = array_filter($dati['quadri'], fn(array $quadro) => ($quadro['modello'] === $modello) && ($quadro['anno'] === $anno));
            yield sprintf("DELETE FROM `campo_modello` WHERE `modello` = '%s' AND `anno` = %d AND `codice` NOT IN (%s);", $modello, $anno,
                    implode(', ', array_map(fn(array $campo) => "'" . $campo['codice'] . "'", $campi)));
            yield sprintf("DELETE FROM `quadro_modello` WHERE `modello` = '%s' AND `anno` = %d AND `quadro` NOT IN (%s);", $modello, $anno,
                    count($quadri) === 0 ? "''" : implode(', ', array_map(fn(array $quadro) => "'" . $quadro['quadro'] . "'", $quadri)));
            yield from $generatore->generaUpsert('campo_modello', ['modello', 'anno', 'codice', 'quadro', 'descrizione', 'formato'], array_values($campi));
            if (count($quadri) > 0) {
                yield from $generatore->generaUpsert('quadro_modello', ['modello', 'anno', 'quadro', 'denominazione'], array_values($quadri));
            }
        }
    }

    /**
     * @return array<int, array<string, ?string>> righe per numero di riga del file
     */
    private function leggiCsv(string $nome, array $colonne): array
    {
        $file = @fopen($this->cartella . $nome, 'r') ?: throw new \RuntimeException('Impossibile aprire ' . $nome);
        $intestazione = fgetcsv($file, separator: ';', escape: '');
        if ($intestazione !== $colonne) {
            fclose($file);
            throw new \UnexpectedValueException($nome . ': intestazione non valida, attesa ' . implode(';', $colonne));
        }
        $righe = [];
        $numero = 1;
        while (($valori = fgetcsv($file, separator: ';', escape: '')) !== false) {
            $numero++;
            if ($valori === [null]) {
                continue;
            }
            $righe[$numero] = array_combine($colonne, array_map(fn(?string $valore) => ($valore === null) || (trim($valore) === '') ? null : trim($valore), array_slice(array_pad($valori, count($colonne), null), 0, count($colonne))));
        }
        fclose($file);
        return $righe;
    }
}
