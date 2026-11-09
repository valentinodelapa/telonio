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
 *
 * Le aliquote delle addizionali all'IRPEF sono in
 * addizionali-regionali-<anno>.csv (trascritta dalla tabella delle
 * istruzioni del modello Redditi PF) e addizionali-comunali-<anno>.csv
 * (letta dall'elenco dell'Agenzia delle Entrate con AddizionaliComunali);
 * addizionali.csv elenca anni e fonti.
 */
class TabelleDichiarativi
{

    public const CARTELLA = __DIR__ . '/../../../Database/sorgenti/';
    public const COLONNE_CAMPI = ['codice', 'formato', 'descrizione'];
    public const COLONNE_QUADRI = ['quadro', 'denominazione'];
    public const COLONNE_REGIONALI = ['codice', 'regione', 'scaglioni', 'modalita', 'esenzione_reddito', 'soglia_aliquota', 'detrazioni', 'detrazione_crescente', 'deduzione', 'casi_particolari'];

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
        $dati = ['campi' => [], 'quadri' => [], 'modelli' => [], 'regionali' => [], 'comunali' => [], 'anniAddizionali' => []];
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
        foreach ($this->leggiFontiAddizionali() as ['anno' => $anno]) {
            $this->leggiAddizionali($anno, $dati);
            $dati['anniAddizionali'][] = $anno;
        }
        return $dati;
    }

    /**
     * @return list<array{anno: int, url: string, fonte: string}>
     */
    public function leggiFontiAddizionali(): array
    {
        if (is_file($this->cartella . 'addizionali.csv') === false) {
            return [];
        }
        $fonti = [];
        foreach ($this->leggiCsv('addizionali.csv', AddizionaliComunali::COLONNE_FONTI) as $numero => $riga) {
            if ((preg_match('/^20\d\d$/', (string) $riga['anno']) !== 1) || ($riga['url'] === null) || ($riga['fonte'] === null)) {
                throw new \UnexpectedValueException('addizionali.csv, riga ' . $numero . ': anno, indirizzo o fonte non validi');
            }
            $fonti[] = ['anno' => (int) $riga['anno'], 'url' => $riga['url'], 'fonte' => $riga['fonte']];
        }
        return $fonti;
    }

    private function leggiAddizionali(int $anno, array &$dati): void
    {
        $regionali = 'addizionali-regionali-' . $anno . '.csv';
        foreach ($this->leggiCsv($regionali, self::COLONNE_REGIONALI) as $numero => $riga) {
            if ((preg_match('/^\d\d$/', (string) $riga['codice']) !== 1) || ($riga['regione'] === null) || (self::isScaglioni((string) $riga['scaglioni']) === false)
                    || (in_array($riga['modalita'], ['progressiva', 'intero'], true) === false)) {
                throw new \UnexpectedValueException($regionali . ', riga ' . $numero . ': codice, regione, scaglioni o modalità non validi');
            }
            $dati['regionali'][] = ['anno' => $anno] + $riga;
        }
        $comunali = 'addizionali-comunali-' . $anno . '.csv';
        foreach ($this->leggiCsv($comunali, AddizionaliComunali::COLONNE) as $numero => $riga) {
            $aliquote = $riga['aliquote'] === null ? [] : explode('|', $riga['aliquote']);
            if ((preg_match('/^[A-Z]\d{3}$/', (string) $riga['codice']) !== 1) || (($riga['aliquota_unica'] === null) === (count($aliquote) === 0))
                    || (count(array_filter($aliquote, fn(string $aliquota) => is_numeric($aliquota) === false)) > 0)
                    || ((count($aliquote) > 0) && (count($aliquote) !== ($riga['tipologia'] === '2' ? 3 : 4)))) {
                throw new \UnexpectedValueException($comunali . ', riga ' . $numero . ': codice o aliquote non validi');
            }
            $dati['comunali'][] = ['anno' => $anno, 'codice_catastale' => $riga['codice'], 'aliquota_unica' => $riga['aliquota_unica'], 'aliquote' => $riga['aliquote'],
                'tipologia' => $riga['tipologia'] === null ? null : (int) $riga['tipologia'], 'esenzione' => $riga['esenzione'], 'tipizzazione' => $riga['tipizzazione'],
                'esenzione_casi' => $riga['esenzione_casi'], 'casi_particolari' => $riga['casi_particolari']];
        }
    }

    /**
     * Scaglioni "limite:aliquota|...|:aliquota" con limiti crescenti e
     * l'ultimo senza limite.
     */
    public static function isScaglioni(string $scaglioni): bool
    {
        $precedente = 0.0;
        $parti = explode('|', $scaglioni);
        foreach ($parti as $indice => $parte) {
            if (preg_match('/^(\d*):(\d+(\.\d+)?)$/', $parte, $corrispondenze) !== 1) {
                return false;
            }
            $ultimo = $indice === count($parti) - 1;
            if ($ultimo !== ($corrispondenze[1] === '')) {
                return false;
            }
            if (!$ultimo && ((float) $corrispondenze[1] <= $precedente)) {
                return false;
            }
            $precedente = (float) $corrispondenze[1];
        }
        return true;
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
        foreach ($dati['anniAddizionali'] ?? [] as $anno) {
            $regionali = array_values(array_filter($dati['regionali'], fn(array $riga) => $riga['anno'] === $anno));
            $comunali = array_values(array_filter($dati['comunali'], fn(array $riga) => $riga['anno'] === $anno));
            yield sprintf("DELETE FROM `addizionale_regionale` WHERE `anno` = %d AND `codice` NOT IN (%s);", $anno, implode(', ', array_map(fn(array $riga) => "'" . $riga['codice'] . "'", $regionali)) ?: "''");
            yield sprintf("DELETE FROM `addizionale_comunale` WHERE `anno` = %d AND `codice_catastale` NOT IN (%s);", $anno, implode(', ', array_map(fn(array $riga) => "'" . $riga['codice_catastale'] . "'", $comunali)) ?: "''");
            yield from $generatore->generaUpsert('addizionale_regionale', ['anno', 'codice', 'regione', 'scaglioni', 'modalita', 'esenzione_reddito', 'soglia_aliquota', 'detrazioni', 'detrazione_crescente', 'deduzione', 'casi_particolari'], $regionali);
            yield from $generatore->generaUpsert('addizionale_comunale', ['anno', 'codice_catastale', 'aliquota_unica', 'aliquote', 'tipologia', 'esenzione', 'tipizzazione', 'esenzione_casi', 'casi_particolari'], $comunali);
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
