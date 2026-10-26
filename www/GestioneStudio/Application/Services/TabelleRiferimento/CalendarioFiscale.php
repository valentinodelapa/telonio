<?php

namespace GestioneStudio\Application\Services\TabelleRiferimento;

use Anagrafica\Application\Services\TabelleRiferimento\GeneratoreSql;
use GestioneStudio\Application\Entities\Adempimento;
use GestioneStudio\Application\Enumerations\AmbitoAdempimento;
use GestioneStudio\Application\Enumerations\CategoriaAdempimento;
use GestioneStudio\Application\Enumerations\CondizioneProroga;

/**
 * Calendario fiscale comune a tutti gli studi, dalle sorgenti del modulo
 * (Database/sorgenti): l'Agenzia delle Entrate pubblica lo scadenzario solo
 * come pagine web, senza un formato da importare, quindi adempimenti,
 * termini e proroghe di legge sono curati in CSV con la norma per riga.
 *
 * - adempimenti.csv: id stabile (sotto 1000), codice, denominazione,
 *   categoria e ambito (valori degli enum), proroga di agosto, fonte;
 * - termini.csv: termine per periodo dell'anno di riferimento (giorno 0 =
 *   ultimo del mese), id = adempimento * 100 + ordine;
 * - proroghe.csv: nuovo termine per adempimento, ordine e anno, con i
 *   soggetti a cui si applica (valore di CondizioneProroga), il
 *   provvedimento e le note.
 *
 * Ogni anomalia ferma l'importazione. Le righe non sono mai cancellate se
 * referenziate: gli adempimenti tolti dalle sorgenti restano non attivi.
 */
class CalendarioFiscale
{

    public const CARTELLA = __DIR__ . '/../../../Database/sorgenti/';
    public const COLONNE_ADEMPIMENTI = ['id', 'codice', 'denominazione', 'categoria', 'ambito', 'proroga_agosto', 'fonte'];
    public const COLONNE_TERMINI = ['adempimento_id', 'ordine', 'periodo', 'mese', 'giorno', 'anni_successivi'];
    public const COLONNE_PROROGHE = ['adempimento_id', 'ordine', 'anno', 'data', 'condizione', 'provvedimento', 'note'];
    private const ORDINE_MASSIMO = 99;
    private const GIORNI_MESE = [31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

    public function __construct(private string $cartella = self::CARTELLA)
    {

    }

    public static function getIdTermine(int $adempimento, int $ordine): int
    {
        return $adempimento * 100 + $ordine;
    }

    /**
     * @return array{adempimenti: array, termini: array, proroghe: array} righe pronte per le tabelle
     */
    public function leggi(): array
    {
        $adempimenti = $this->leggiAdempimenti();
        $termini = $this->leggiTermini($adempimenti);
        $proroghe = $this->leggiProroghe($termini);
        return ['adempimenti' => array_values($adempimenti), 'termini' => array_values($termini), 'proroghe' => $proroghe];
    }

    /**
     * Istruzioni SQL idempotenti che allineano il calendario fiscale alle
     * sorgenti.
     *
     * @return \Generator<string>
     */
    public function istruzioni(array $dati): \Generator
    {
        $generatore = new GeneratoreSql();
        yield from $generatore->generaUpsert('adempimento', ['id', 'studio_id', 'codice', 'denominazione', 'categoria', 'ambito', 'fonte', 'slittamento_festivi', 'proroga_agosto', 'attivo'], $dati['adempimenti']);
        $idAdempimenti = implode(', ', array_column($dati['adempimenti'], 'id'));
        yield 'UPDATE `adempimento` SET `attivo` = 0 WHERE `studio_id` IS NULL AND `id` NOT IN (' . $idAdempimenti . ');';
        yield from $generatore->generaUpsert('termine_adempimento', ['id', 'adempimento_id', 'ordine', 'periodo', 'mese', 'giorno', 'anni_successivi'], $dati['termini']);
        yield 'DELETE `termine_adempimento` FROM `termine_adempimento` JOIN `adempimento` ON `adempimento`.`id` = `termine_adempimento`.`adempimento_id`'
                . ' WHERE `adempimento`.`studio_id` IS NULL AND `termine_adempimento`.`id` NOT IN (' . implode(', ', array_column($dati['termini'], 'id')) . ');';
        yield 'DELETE FROM `proroga_adempimento` WHERE `studio_id` IS NULL;';
        if (count($dati['proroghe']) > 0) {
            yield from $generatore->generaUpsert('proroga_adempimento', ['termine_adempimento_id', 'studio_id', 'anno', 'data', 'condizione', 'provvedimento', 'note'], $dati['proroghe']);
        }
    }

    private function leggiAdempimenti(): array
    {
        $adempimenti = [];
        $codici = [];
        foreach ($this->leggiCsv('adempimenti.csv', self::COLONNE_ADEMPIMENTI) as $numero => $riga) {
            $id = self::intero($riga['id']);
            if (($id === null) || ($id < 1) || ($id >= Adempimento::PRIMO_ID_STUDIO) || isset($adempimenti[$id])) {
                throw new \UnexpectedValueException('adempimenti.csv, riga ' . $numero . ': id mancante, duplicato o non inferiore a ' . Adempimento::PRIMO_ID_STUDIO);
            }
            if (($riga['codice'] === null) || isset($codici[$riga['codice']]) || ($riga['denominazione'] === null)) {
                throw new \UnexpectedValueException('adempimenti.csv, riga ' . $numero . ': codice mancante o duplicato, o denominazione mancante');
            }
            if ((CategoriaAdempimento::tryFrom((string) $riga['categoria']) === null) || (AmbitoAdempimento::tryFrom((string) $riga['ambito']) === null) || (in_array($riga['proroga_agosto'], ['0', '1'], true) === false)) {
                throw new \UnexpectedValueException('adempimenti.csv, riga ' . $numero . ': categoria, ambito o proroga di agosto non validi');
            }
            if ($riga['fonte'] === null) {
                throw new \UnexpectedValueException('adempimenti.csv, riga ' . $numero . ': fonte normativa mancante');
            }
            $codici[$riga['codice']] = true;
            $adempimenti[$id] = ['id' => $id, 'studio_id' => null, 'codice' => $riga['codice'], 'denominazione' => $riga['denominazione'], 'categoria' => $riga['categoria'],
                'ambito' => $riga['ambito'], 'fonte' => $riga['fonte'], 'slittamento_festivi' => 1, 'proroga_agosto' => (int) $riga['proroga_agosto'], 'attivo' => 1];
        }
        return $adempimenti;
    }

    private function leggiTermini(array $adempimenti): array
    {
        $termini = [];
        foreach ($this->leggiCsv('termini.csv', self::COLONNE_TERMINI) as $numero => $riga) {
            [$adempimento, $ordine, $mese, $giorno, $anni] = array_map(fn(?string $valore) => self::intero($valore), [$riga['adempimento_id'], $riga['ordine'], $riga['mese'], $riga['giorno'], $riga['anni_successivi']]);
            if (($adempimento === null) || (isset($adempimenti[$adempimento]) === false)) {
                throw new \UnexpectedValueException('termini.csv, riga ' . $numero . ': adempimento inesistente');
            }
            if (($ordine === null) || ($ordine < 1) || ($ordine > self::ORDINE_MASSIMO) || isset($termini[self::getIdTermine($adempimento, $ordine)])) {
                throw new \UnexpectedValueException('termini.csv, riga ' . $numero . ': ordine mancante, duplicato o fuori da 1-' . self::ORDINE_MASSIMO);
            }
            if (($mese === null) || ($mese < 1) || ($mese > 12) || ($giorno === null) || ($giorno < 0) || ($giorno > self::GIORNI_MESE[$mese - 1]) || ($anni === null) || ($anni > 5) || ($riga['periodo'] === null)) {
                throw new \UnexpectedValueException('termini.csv, riga ' . $numero . ': periodo, mese, giorno o anni successivi non validi');
            }
            $id = self::getIdTermine($adempimento, $ordine);
            $termini[$id] = ['id' => $id, 'adempimento_id' => $adempimento, 'ordine' => $ordine, 'periodo' => $riga['periodo'], 'mese' => $mese, 'giorno' => $giorno, 'anni_successivi' => $anni];
        }
        $senzaTermini = array_diff(array_keys($adempimenti), array_column($termini, 'adempimento_id'));
        if (count($senzaTermini) > 0) {
            throw new \UnexpectedValueException('termini.csv: adempimenti senza termini: ' . implode(', ', $senzaTermini));
        }
        return $termini;
    }

    private function leggiProroghe(array $termini): array
    {
        $proroghe = [];
        foreach ($this->leggiCsv('proroghe.csv', self::COLONNE_PROROGHE) as $numero => $riga) {
            $adempimento = self::intero($riga['adempimento_id']);
            $ordine = self::intero($riga['ordine']);
            $anno = self::intero($riga['anno']);
            $data = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $riga['data']);
            if (($adempimento === null) || ($ordine === null) || (isset($termini[self::getIdTermine($adempimento, $ordine)]) === false)) {
                throw new \UnexpectedValueException('proroghe.csv, riga ' . $numero . ': termine inesistente');
            }
            if (($anno === null) || ($data === false) || ($data->format('Y-m-d') !== $riga['data']) || ($riga['provvedimento'] === null) || (CondizioneProroga::tryFrom((string) $riga['condizione']) === null)) {
                throw new \UnexpectedValueException('proroghe.csv, riga ' . $numero . ': anno, data (AAAA-MM-GG), condizione o provvedimento non validi');
            }
            $proroghe[] = ['termine_adempimento_id' => self::getIdTermine($adempimento, $ordine), 'studio_id' => null, 'anno' => $anno, 'data' => $riga['data'],
                'condizione' => $riga['condizione'], 'provvedimento' => $riga['provvedimento'], 'note' => $riga['note']];
        }
        return $proroghe;
    }

    private static function intero(?string $valore): ?int
    {
        return ($valore !== null) && ctype_digit($valore) ? (int) $valore : null;
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
