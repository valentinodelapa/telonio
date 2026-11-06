<?php

namespace Contabilita\Application\Services\TabelleRiferimento;

use Anagrafica\Application\Services\TabelleRiferimento\GeneratoreSql;
use Contabilita\Application\Entities\CodiceIva;
use Contabilita\Application\Entities\PianoConti;
use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Enumerations\LivelloConto;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Enumerations\NaturaIva;
use Contabilita\Application\Enumerations\VoceCee;

/**
 * Tabelle di riferimento del modulo comuni a tutti gli studi, dalle
 * sorgenti in Database/sorgenti. Non esiste una fonte ufficiale da
 * importare: il piano dei conti non è fissato dalla legge (lo è il
 * raccordo con le voci degli artt. 2424 e 2425 c.c., verificato
 * sull'enum VoceCee) e i codici IVA sono un'organizzazione delle aliquote
 * e delle nature delle specifiche della fattura elettronica, con la norma
 * per riga.
 *
 * - piani-conti.csv: piani modello della suite (id sotto 1000);
 * - conti.csv: conti dei piani (id sotto 100000), con natura, funzione e
 *   voce CEE; il livello e il conto superiore si ricavano dal codice;
 * - codici-iva.csv: codici della suite (id sotto 1000).
 *
 * Ogni anomalia ferma l'importazione. I conti tolti dalle sorgenti sono
 * cancellati (i piani delle aziende sono copie indipendenti), i codici
 * IVA tolti restano non attivi perché usati nelle registrazioni.
 */
class TabelleContabilita
{

    public const CARTELLA = __DIR__ . '/../../../Database/sorgenti/';
    public const COLONNE_PIANI = ['id', 'denominazione', 'descrizione'];
    public const COLONNE_CONTI = ['piano_conti_id', 'id', 'codice', 'descrizione', 'natura', 'funzione', 'voce_cee'];
    public const COLONNE_PRODOTTI = ['id', 'voce', 'descrizione', 'percentuale', 'aliquota', 'dal_anno', 'al_anno'];
    public const COLONNE_CODICI_IVA = ['id', 'codice', 'descrizione', 'aliquota', 'natura', 'detraibilita', 'inversione_contabile', 'ventilazione', 'norma'];
    public const PRIMO_ID_CONTO_STUDIO = 100000;
    private const DATA_CREAZIONE = '2026-10-31 00:00:00';

    public function __construct(private string $cartella = self::CARTELLA)
    {

    }

    /**
     * @return array{piani: array, conti: array, codiciIva: array, prodotti: array} righe pronte per le tabelle
     */
    public function leggi(): array
    {
        $piani = $this->leggiPiani();
        return ['piani' => array_values($piani), 'conti' => $this->leggiConti($piani), 'codiciIva' => $this->leggiCodiciIva(), 'prodotti' => $this->leggiProdotti()];
    }

    /**
     * Istruzioni SQL idempotenti che allineano le tabelle alle sorgenti.
     *
     * @return \Generator<string>
     */
    public function istruzioni(array $dati): \Generator
    {
        $generatore = new GeneratoreSql();
        yield from $generatore->generaUpsert('piano_conti', ['id', 'studio_id', 'soggetto_id', 'denominazione', 'descrizione', 'piano_origine_id', 'data_creazione'], $dati['piani']);
        $idPiani = implode(', ', array_column($dati['piani'], 'id'));
        yield 'DELETE FROM `conto` WHERE `piano_conti_id` IN (' . $idPiani . ') AND `id` NOT IN (' . implode(', ', array_column($dati['conti'], 'id')) . ');';
        yield from $generatore->generaUpsert('conto', ['id', 'piano_conti_id', 'parent_conto_id', 'codice', 'descrizione', 'livello', 'natura', 'funzione', 'voce_cee', 'attivo'], $dati['conti']);
        yield from $generatore->generaUpsert('codice_iva', ['id', 'studio_id', 'codice', 'descrizione', 'aliquota', 'natura', 'detraibilita', 'inversione_contabile', 'ventilazione', 'norma', 'attivo'], $dati['codiciIva']);
        yield 'DELETE FROM `prodotto_agricolo` WHERE `id` NOT IN (' . implode(', ', array_column($dati['prodotti'], 'id')) . ');';
        yield from $generatore->generaUpsert('prodotto_agricolo', ['id', 'voce', 'descrizione', 'percentuale', 'dal_anno', 'al_anno'], $dati['prodotti']);
        yield 'UPDATE `codice_iva` SET `attivo` = 0 WHERE `studio_id` IS NULL AND `id` NOT IN (' . implode(', ', array_column($dati['codiciIva'], 'id')) . ');';
    }

    private function leggiPiani(): array
    {
        $piani = [];
        foreach ($this->leggiCsv('piani-conti.csv', self::COLONNE_PIANI) as $numero => $riga) {
            $id = self::intero($riga['id']);
            if (($id === null) || ($id < 1) || ($id >= PianoConti::PRIMO_ID_STUDIO) || isset($piani[$id]) || ($riga['denominazione'] === null)) {
                throw new \UnexpectedValueException('piani-conti.csv, riga ' . $numero . ': id mancante, duplicato o non inferiore a ' . PianoConti::PRIMO_ID_STUDIO . ', o denominazione mancante');
            }
            $piani[$id] = ['id' => $id, 'studio_id' => null, 'soggetto_id' => null, 'denominazione' => $riga['denominazione'], 'descrizione' => $riga['descrizione'],
                'piano_origine_id' => null, 'data_creazione' => self::DATA_CREAZIONE];
        }
        if (count($piani) === 0) {
            throw new \UnexpectedValueException('piani-conti.csv: nessun piano');
        }
        return $piani;
    }

    private function leggiConti(array $piani): array
    {
        $conti = $codici = $funzioni = [];
        foreach ($this->leggiCsv('conti.csv', self::COLONNE_CONTI) as $numero => $riga) {
            $errore = fn(string $testo) => new \UnexpectedValueException('conti.csv, riga ' . $numero . ': ' . $testo);
            $piano = self::intero($riga['piano_conti_id']);
            $id = self::intero($riga['id']);
            if (($piano === null) || (isset($piani[$piano]) === false)) {
                throw $errore('piano inesistente');
            }
            if (($id === null) || ($id < 1) || ($id >= self::PRIMO_ID_CONTO_STUDIO) || isset($conti[$id])) {
                throw $errore('id mancante, duplicato o non inferiore a ' . self::PRIMO_ID_CONTO_STUDIO);
            }
            $codice = (string) $riga['codice'];
            $livello = LivelloConto::daCodice($codice);
            if (($livello === null) || isset($codici[$piano][$codice]) || ($riga['descrizione'] === null)) {
                throw $errore('codice non nella forma NN, NN.NN o NN.NN.NNNN, duplicato, o descrizione mancante');
            }
            $padre = LivelloConto::codicePadre($codice);
            if (($padre !== null) && (isset($codici[$piano][$padre]) === false)) {
                throw $errore('conto superiore ' . $padre . ' assente o successivo (ordinare per codice)');
            }
            $natura = NaturaConto::tryFrom((string) $riga['natura']);
            if ($natura === null) {
                throw $errore('natura non valida');
            }
            $funzione = $riga['funzione'] === null ? null : FunzioneConto::tryFrom($riga['funzione']);
            if (($riga['funzione'] !== null) && (($funzione === null) || ($funzione->getLivello() !== $livello) || isset($funzioni[$piano][$funzione->value]))) {
                throw $errore('funzione non valida, già assegnata o non ammessa al livello');
            }
            $voce = $riga['voce_cee'] === null ? null : VoceCee::tryFrom($riga['voce_cee']);
            if (($riga['voce_cee'] !== null) && (($voce === null) || ($voce->isCompatibile($natura) === false))) {
                throw $errore('voce CEE inesistente o incompatibile con la natura del conto');
            }
            $codici[$piano][$codice] = $id;
            if ($funzione !== null) {
                $funzioni[$piano][$funzione->value] = true;
            }
            $conti[$id] = ['id' => $id, 'piano_conti_id' => $piano, 'parent_conto_id' => $padre === null ? null : $codici[$piano][$padre], 'codice' => $codice,
                'descrizione' => $riga['descrizione'], 'livello' => $livello->value, 'natura' => $natura->value, 'funzione' => $funzione?->value, 'voce_cee' => $voce?->value, 'attivo' => 1];
        }
        foreach (array_keys($piani) as $piano) {
            foreach ([FunzioneConto::clienti, FunzioneConto::fornitori] as $funzione) {
                if (isset($funzioni[$piano][$funzione->value]) === false) {
                    throw new \UnexpectedValueException('conti.csv: il piano ' . $piano . ' non ha il conto ' . $funzione->getLabel());
                }
            }
        }
        return array_values($conti);
    }

    /**
     * Prodotti agricoli: la percentuale deve essere tra quelle del modello.
     */
    private function leggiProdotti(): array
    {
        $prodotti = [];
        foreach ($this->leggiCsv('prodotti-agricoli.csv', self::COLONNE_PRODOTTI) as $numero => $riga) {
            $errore = fn(string $testo) => new \UnexpectedValueException('prodotti-agricoli.csv, riga ' . $numero . ': ' . $testo);
            $id = self::intero($riga['id']);
            if (($id === null) || ($id < 1) || isset($prodotti[$id]) || ($riga['descrizione'] === null)) {
                throw $errore('id mancante o duplicato, o descrizione mancante');
            }
            $percentuale = self::percentuale($riga['percentuale']);
            if (($percentuale === null) || (in_array(round($percentuale, 2), RigaIva::PERCENTUALI_COMPENSAZIONE, true) === false)) {
                throw $errore('percentuale di compensazione non prevista dal modello');
            }
            $prodotti[$id] = ['id' => $id, 'voce' => $riga['voce'], 'descrizione' => $riga['descrizione'], 'percentuale' => number_format($percentuale, 2, '.', ''),
                'dal_anno' => self::intero($riga['dal_anno']), 'al_anno' => self::intero($riga['al_anno'])];
        }
        return array_values($prodotti);
    }

    private function leggiCodiciIva(): array
    {
        $codiciIva = $codici = [];
        foreach ($this->leggiCsv('codici-iva.csv', self::COLONNE_CODICI_IVA) as $numero => $riga) {
            $errore = fn(string $testo) => new \UnexpectedValueException('codici-iva.csv, riga ' . $numero . ': ' . $testo);
            $id = self::intero($riga['id']);
            if (($id === null) || ($id < 1) || ($id >= CodiceIva::PRIMO_ID_STUDIO) || isset($codiciIva[$id])) {
                throw $errore('id mancante, duplicato o non inferiore a ' . CodiceIva::PRIMO_ID_STUDIO);
            }
            if (($riga['codice'] === null) || (mb_strlen($riga['codice']) > 10) || isset($codici[$riga['codice']]) || ($riga['descrizione'] === null) || ($riga['norma'] === null)) {
                throw $errore('codice mancante, troppo lungo o duplicato, o descrizione o norma mancanti');
            }
            $aliquota = self::percentuale($riga['aliquota']);
            $detraibilita = self::percentuale($riga['detraibilita']);
            $natura = $riga['natura'] === null ? null : NaturaIva::tryFrom($riga['natura']);
            if (($aliquota === null) || ($detraibilita === null) || (($riga['natura'] !== null) && ($natura === null)) || (in_array($riga['inversione_contabile'], ['0', '1'], true) === false)
                    || (in_array($riga['ventilazione'], ['0', '1'], true) === false)) {
                throw $errore('aliquota, detraibilità, natura, inversione contabile o ventilazione non validi');
            }
            $ventilazione = $riga['ventilazione'] === '1';
            if ($ventilazione && (($aliquota > 0) || ($natura !== null) || ($riga['inversione_contabile'] === '1'))) {
                throw $errore('il codice di ventilazione non ha aliquota, natura né inversione contabile');
            }
            if ((($natura !== null) && ($aliquota > 0)) || (($natura === null) && ($aliquota <= 0) && ($ventilazione === false)) || (($riga['inversione_contabile'] === '1') && ($natura !== null))) {
                throw $errore('la natura si indica solo senza aliquota, e l\'inversione contabile solo per le aliquote');
            }
            $codici[$riga['codice']] = true;
            $codiciIva[$id] = ['id' => $id, 'studio_id' => null, 'codice' => $riga['codice'], 'descrizione' => $riga['descrizione'], 'aliquota' => number_format($aliquota, 2, '.', ''),
                'natura' => $natura?->value, 'detraibilita' => number_format($detraibilita, 2, '.', ''), 'inversione_contabile' => (int) $riga['inversione_contabile'], 'ventilazione' => (int) $ventilazione, 'norma' => $riga['norma'], 'attivo' => 1];
        }
        return array_values($codiciIva);
    }

    private static function intero(?string $valore): ?int
    {
        return ($valore !== null) && ctype_digit($valore) ? (int) $valore : null;
    }

    private static function percentuale(?string $valore): ?float
    {
        return ($valore !== null) && (preg_match('/^\d{1,3}(\.\d{1,2})?$/', $valore) === 1) && ((float) $valore <= 100) ? (float) $valore : null;
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
