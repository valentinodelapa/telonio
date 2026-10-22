<?php

namespace Deleghe\Application\Services\TabelleRiferimento;

use Deleghe\Application\Entities\CausaleContributo;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\TabellaF24;

/**
 * Lettura delle tabelle dell'archivio F24 dell'Agenzia delle Entrate:
 * codici tributo (erariali, regionali e locali, IMU e tributi locali,
 * elementi identificativi, accise) con i formalismi di compilazione,
 * causali contributo INPS e degli altri enti, tabelle di decodifica.
 *
 * Ogni tabella viene riconosciuta dal nome del file e dalle intestazioni;
 * se il tracciato cambia o una tabella risulta incompleta la lettura si
 * ferma senza modificare nulla.
 */
class TabelleF24Agenzia
{

    public const COLONNE_CODICI = ['sezione', 'codice', 'descrizione', 'tipo_tributo', 'modalita', 'solo_agente_riscossione', 'formato_rateazione', 'anno_richiesto', 'anno_massimo',
        'codice_ufficio', 'codice_atto', 'identificativo_operazione', 'elementi_identificativi', 'tabelle_ente', 'ente_accise', 'provincia_richiesta', 'mese_richiesto',
        'codice_identificativo_ente', 'compensabile_crediti_pa', 'in_uso'];
    public const COLONNE_TABELLE = ['tabella', 'codice', 'descrizione', 'dati', 'in_uso'];
    public const COLONNE_CAUSALI = ['ente', 'causale', 'descrizione', 'formati_matricola', 'regola_posizione', 'periodo_da', 'periodo_a', 'regola_periodo_da', 'regola_periodo_a', 'modalita', 'compensabile_crediti_pa', 'in_uso'];
    private const MINIMI_CODICI = ['E' => 1000, 'R' => 20, 'L' => 50, 'I' => 100, 'A' => 100];
    private const MINIMI_TABELLE = ['T0' => 21, 'T1' => 5, 'T2' => 100, 'T6' => 90, 'SINP' => 100, 'SINA' => 100, 'SENP' => 5, 'ENTI' => 10, 'TELI' => 5, 'FINP' => 5];
    private const RATEAZIONI = ['0000', '00MM', 'NNRR', 'FFFF', '00T0', '00T1', '00T2', '00T3', '00T4'];

    private array $avvisi = [];
    private ?array $elencoCreditiPa = null;

    public function __construct(
            private ArchivioTabelleF24 $archivio,
            private LettoreFoglio $lettore = new LettoreFoglio(),
    ) {

    }

    /**
     * Messaggi non bloccanti dell'ultima lettura (codici ripetuti, enti
     * senza tabella delle causali, ...).
     *
     * @return string[]
     */
    public function getAvvisi(): array
    {
        return $this->avvisi;
    }

    // ---------------------------------------------------------------
    // Codici tributo
    // ---------------------------------------------------------------

    /**
     * @return array<array<string, string|int|null>>
     */
    public function leggiCodiciTributo(): array
    {
        $codici = [];
        $this->leggiErariRegionali($codici);
        $this->leggiTributiLocali($codici);
        $this->leggiElementiIdentificativi($codici);
        $this->leggiAccise($codici);
        $this->segnaCompensabiliCreditiPa($codici);
        $conteggi = array_count_values(array_column($codici, 'sezione'));
        foreach (self::MINIMI_CODICI as $sezione => $minimo) {
            if (($conteggi[$sezione] ?? 0) < $minimo) {
                throw new \UnexpectedValueException(sprintf('Codici tributo della sezione %s incompleti: %d.', SezioneCodiceTributo::from($sezione)->getLabel(), $conteggi[$sezione] ?? 0));
            }
        }
        return array_values($codici);
    }

    /**
     * Tabella dei codici erariali, regionali e degli enti locali usati nella
     * sezione IMU (addizionale comunale, diritto camerale).
     */
    private function leggiErariRegionali(array &$codici): void
    {
        $righe = $this->lettore->leggi($this->archivio->file('/^TABELLA_EREL_.*codice_tributo.*\.xlsx$/i'));
        $this->verificaIntestazione($righe[0], [0 => 'TRIBUTO', 1 => 'MODALITA', 2 => 'TIPO TRIBUTO', 5 => 'CODICE UFFICIO', 6 => 'CODICE ATTO', 7 => 'ANNO MASSIMO', 8 => 'DESCRIZIONE'], 'tabella codici erariali e regionali');
        foreach (array_slice($righe, 2) as $riga) {
            $codice = self::codice($riga[0] ?? '');
            if ($codice === null) {
                continue;
            }
            $tipo = $riga[2] ?? '';
            [$sezione, $tabelleEnte] = match (true) {
                $tipo === 'Erario' => ['E', null],
                $tipo === 'Regioni' => ['R', null],
                str_starts_with($tipo, 'Enti locali-Camere di commercio') => ['L', 'T6'],
                str_starts_with($tipo, 'Enti locali') => ['L', 'T4'],
                default => throw new \UnexpectedValueException('Tipo tributo sconosciuto per il codice ' . $codice . ': ' . $tipo),
            };
            $this->aggiungi($codici, [
                'sezione' => $sezione,
                'codice' => $codice,
                'descrizione' => $riga[8] ?? '',
                'tipo_tributo' => preg_replace('/\s*\(\*+\)$/', '', $tipo),
                'rateazione' => $riga[3] ?? '',
                'anno' => $riga[4] ?? '',
                'modalita' => $riga[1] ?? '',
                'codice_ufficio' => $riga[5] ?? '',
                'codice_atto' => $riga[6] ?? '',
                'identificativo_operazione' => '',
                'anno_massimo' => $riga[7] ?? '0',
                'tabelle_ente' => $tabelleEnte,
            ]);
        }
    }

    private function leggiTributiLocali(array &$codici): void
    {
        $righe = $this->lettore->leggi($this->archivio->file('/^Tabella_IMU.*\.xlsx?$/i'));
        $this->verificaIntestazione($righe[0], [0 => 'CODICE ENTE', 3 => 'CODICE TRIBUTO', 4 => 'MODALITA', 5 => 'TIPO TRIBUTO', 8 => 'CODICE UFFICIO', 9 => 'CODICE ATTO', 10 => 'IDENTIFICATIVO OPERAZIONE', 12 => 'DESCRIZIONE'], 'tabella codici IMU e tributi locali');
        foreach (array_slice($righe, 2) as $riga) {
            $codice = self::codice($riga[3] ?? '');
            if ($codice === null) {
                continue;
            }
            $tabelle = array_values(array_filter([$riga[0] ?? '', $riga[1] ?? '', $riga[2] ?? ''], fn($tabella) => preg_match('/^T\d$/', $tabella) === 1));
            $this->aggiungi($codici, [
                'sezione' => 'L',
                'codice' => $codice,
                'descrizione' => $riga[12] ?? '',
                'tipo_tributo' => $riga[5] ?? null,
                'rateazione' => $riga[6] ?? '',
                'anno' => $riga[7] ?? '',
                'modalita' => $riga[4] ?? '',
                'codice_ufficio' => $riga[8] ?? '',
                'codice_atto' => $riga[9] ?? '',
                'identificativo_operazione' => $riga[10] ?? '',
                'anno_massimo' => $riga[11] ?? '0',
                'tabelle_ente' => count($tabelle) > 0 ? implode(',', $tabelle) : 'T4',
            ]);
        }
    }

    private function leggiElementiIdentificativi(array &$codici): void
    {
        $righe = $this->lettore->leggi($this->archivio->file('/^Tabella_versamenti_con_elementi_identificativi.*codice_tributo.*\.xlsx$/i'));
        $this->verificaIntestazione($righe[0], [0 => 'TRIBUTO', 1 => 'TIPO TRIBUTO', 2 => 'ANNO', 3 => 'CODICE UFFICIO', 4 => 'CODICE ATTO', 5 => 'ELEMENTI IDENTIFICATIVI', 6 => 'ANNO MASSIMO', 7 => 'DESCRIZIONE'], 'tabella codici F24 ELIDE');
        foreach (array_slice($righe, 1) as $riga) {
            $codice = self::codice($riga[0] ?? '');
            if ($codice === null) {
                continue;
            }
            $elementi = preg_match('/^(\d)\s/', $riga[5] ?? '', $corrispondenza) === 1 ? $corrispondenza[1] : null;
            if ($elementi === null) {
                throw new \UnexpectedValueException('Formalismo degli elementi identificativi non riconosciuto per il codice ' . $codice . ': ' . ($riga[5] ?? ''));
            }
            $this->aggiungi($codici, [
                'sezione' => 'I',
                'codice' => $codice,
                'descrizione' => $riga[7] ?? '',
                'tipo_tributo' => $riga[1] ?? null,
                'rateazione' => '0000',
                'anno' => $riga[2] ?? '',
                'modalita' => 'D',
                'codice_ufficio' => $riga[3] ?? '',
                'codice_atto' => $riga[4] ?? '',
                'identificativo_operazione' => '',
                'anno_massimo' => $riga[6] ?? '0',
                'tabelle_ente' => null,
                'elementi_identificativi' => $elementi,
            ]);
        }
    }

    /**
     * Codici della sezione accise del modello F24 Accise (solo debito):
     * ente, provincia, codice identificativo richiesto dall'ente, mese e anno
     * del periodo, rateazione.
     */
    private function leggiAccise(array &$codici): void
    {
        $righe = $this->lettore->leggi($this->archivio->file('/^TABELLA_ACCISE.*\.xlsx$/i'));
        $this->verificaIntestazione($righe[1], [0 => 'ENTE', 1 => 'CODICE ENTE', 2 => 'TRIBUTO', 3 => 'PROVINCIA', 4 => 'CODICE IDENTIFICATIVO', 5 => 'MESE', 6 => 'ANNO', 7 => 'CODICE UFFICIO', 8 => 'CODICE ATTO', 9 => 'RATEAZIONE', 10 => 'DESCRIZIONE'], 'tabella codici accise');
        foreach (array_slice($righe, 2) as $riga) {
            $codice = self::codice($riga[2] ?? '');
            if ($codice === null) {
                continue;
            }
            $this->aggiungi($codici, [
                'sezione' => 'A',
                'codice' => $codice,
                'descrizione' => $riga[10] ?? '',
                'tipo_tributo' => $riga[0] ?? null,
                'rateazione' => strtoupper($riga[9] ?? '') === 'SI' ? 'NNRR' : '0000',
                'anno' => $riga[6] ?? '',
                'modalita' => 'D',
                'codice_ufficio' => $riga[7] ?? '',
                'codice_atto' => $riga[8] ?? '',
                'identificativo_operazione' => '',
                'anno_massimo' => '0',
                'tabelle_ente' => null,
                'ente_accise' => preg_match('/^[A-Z]$/', $riga[1] ?? '') === 1 ? $riga[1] : throw new \UnexpectedValueException('Codice ente non valido per il codice accise ' . $codice),
                'provincia_richiesta' => strtoupper($riga[3] ?? '') === 'SI' ? 1 : 0,
                'mese_richiesto' => ($riga[5] ?? '') === 'MM' ? 1 : 0,
                'codice_identificativo_ente' => ($riga[4] ?? '') === '' ? null : mb_substr($riga[4], 0, 150),
            ], false);
        }
    }

    /**
     * Converte i formalismi della tabella nella riga da importare.
     */
    private function aggiungi(array &$codici, array $dati, bool $segnalaRipetuti = true): void
    {
        $chiave = $dati['sezione'] . $dati['codice'];
        if (isset($codici[$chiave])) {
            if ($segnalaRipetuti) {
                $this->avvisi[] = 'codice ' . $dati['codice'] . ' (sezione ' . $dati['sezione'] . ') presente in più tabelle: conservata la prima';
            }
            return;
        }
        if ($dati['descrizione'] === '') {
            throw new \UnexpectedValueException('Codice tributo ' . $dati['codice'] . ' senza descrizione.');
        }
        preg_match('/^([DRE])\s*(\(C\))?$/', strtoupper($dati['modalita']), $modalita);
        if (($modalita === []) && ($dati['modalita'] !== '')) {
            throw new \UnexpectedValueException('Modalità di utilizzo non riconosciuta per il codice ' . $dati['codice'] . ': ' . $dati['modalita']);
        }
        $rateazione = strtoupper(preg_replace('/\s/', '', $dati['rateazione'])) ?: '0000';
        if (in_array($rateazione, self::RATEAZIONI, true) === false) {
            throw new \UnexpectedValueException('Formalismo della rateazione non riconosciuto per il codice ' . $dati['codice'] . ': ' . $rateazione);
        }
        $codici[$chiave] = [
            'sezione' => $dati['sezione'],
            'codice' => $dati['codice'],
            'descrizione' => mb_substr($dati['descrizione'], 0, 500),
            'tipo_tributo' => ($dati['tipo_tributo'] ?? '') === '' ? null : mb_substr($dati['tipo_tributo'], 0, 100),
            'modalita' => $modalita[1] ?? 'D',
            'solo_agente_riscossione' => isset($modalita[2]) ? 1 : 0,
            'formato_rateazione' => $rateazione,
            'anno_richiesto' => str_starts_with(strtoupper($dati['anno']), 'AAAA') ? 1 : 0,
            'anno_massimo' => (int) $dati['anno_massimo'],
            'codice_ufficio' => self::obbligo($dati['codice_ufficio']),
            'codice_atto' => self::obbligo($dati['codice_atto']),
            'identificativo_operazione' => self::obbligo($dati['identificativo_operazione']),
            'elementi_identificativi' => $dati['elementi_identificativi'] ?? null,
            'tabelle_ente' => $dati['tabelle_ente'],
            'ente_accise' => $dati['ente_accise'] ?? null,
            'provincia_richiesta' => $dati['provincia_richiesta'] ?? 0,
            'mese_richiesto' => $dati['mese_richiesto'] ?? 0,
            'codice_identificativo_ente' => $dati['codice_identificativo_ente'] ?? null,
            'compensabile_crediti_pa' => 0,
            'in_uso' => 1,
        ];
    }

    /**
     * Elenco dei codici tributo e delle causali pagabili con i crediti
     * certificati verso la PA (allegato 1 al D.M. 14 gennaio 2014, art.
     * 28-quinquies DPR 602/1973).
     *
     * @return string[] codici e causali dell'elenco
     */
    private function leggiElencoCreditiPa(): array
    {
        if ($this->elencoCreditiPa !== null) {
            return $this->elencoCreditiPa;
        }
        $righe = $this->lettore->leggi($this->archivio->file('/^Elenco codici tributo pagabili 28 quinquies.*\.xls$/i'));
        $this->verificaIntestazione($righe[2] ?? [], [0 => 'CODICE/CAUSALE'], 'elenco dei codici pagabili con i crediti verso la PA');
        $elenco = [];
        foreach (array_slice($righe, 3) as $riga) {
            $codice = self::codice($riga[0] ?? '');
            if ($codice !== null) {
                $elenco[] = $codice;
            }
        }
        if (count($elenco) < 50) {
            throw new \UnexpectedValueException('Elenco dei codici pagabili con i crediti verso la PA incompleto.');
        }
        return $this->elencoCreditiPa = $elenco;
    }

    private function segnaCompensabiliCreditiPa(array &$codici): void
    {
        $elenco = array_flip($this->leggiElencoCreditiPa());
        foreach ($codici as &$codice) {
            if (in_array($codice['sezione'], ['E', 'R', 'L'], true) && isset($elenco[$codice['codice']])) {
                $codice['compensabile_crediti_pa'] = 1;
            }
        }
        unset($codice);
    }

    private static function obbligo(string $valore): string
    {
        return match (strtoupper($valore)) {
            'SI' => 'O',
            'FA' => 'F',
            default => 'N',
        };
    }

    /**
     * Codice tributo di 4 caratteri (i codici numerici letti come numero
     * perdono gli zeri iniziali); null per le righe che non sono codici.
     */
    private static function codice(string $valore): ?string
    {
        $valore = strtoupper(trim($valore));
        if ($valore === 'NOTE') {
            return null;
        }
        if (ctype_digit($valore) && (strlen($valore) < 4)) {
            $valore = str_pad($valore, 4, '0', STR_PAD_LEFT);
        }
        return preg_match('/^[A-Z0-9]{4}$/', $valore) === 1 ? $valore : null;
    }

    private function verificaIntestazione(array $riga, array $attese, string $tabella): void
    {
        foreach ($attese as $colonna => $inizio) {
            if (str_starts_with(str_replace(['À', "'"], ['A', ''], mb_strtoupper($riga[$colonna] ?? '')), str_replace("'", '', $inizio)) === false) {
                throw new \UnexpectedValueException(sprintf('Tracciato della %s cambiato: colonna %d "%s" invece di "%s".', $tabella, $colonna + 1, $riga[$colonna] ?? '', $inizio));
            }
        }
    }

    // ---------------------------------------------------------------
    // Tabelle di decodifica
    // ---------------------------------------------------------------

    /**
     * @return array<array<string, string|int|null>>
     */
    public function leggiTabelle(): array
    {
        $voci = [];
        $aggiungi = function (TabellaF24 $tabella, string $codice, string $descrizione, ?string $dati = null) use (&$voci): void {
            if (($codice === '') || ($descrizione === '')) {
                return;
            }
            $voci[$tabella->value . '|' . $codice] = ['tabella' => $tabella->value, 'codice' => $codice, 'descrizione' => mb_substr($descrizione, 0, 255), 'dati' => $dati, 'in_uso' => 1];
        };
        foreach (array_slice($this->lettore->leggi($this->archivio->file('/^T0_.*\.xls$/i')), 6) as $riga) {
            foreach ([[2, 3], [5, 6]] as [$nome, $codice]) {
                $aggiungi(TabellaF24::regioni, self::numerico($riga[$codice] ?? '', 2), $riga[$nome] ?? '');
            }
        }
        foreach ($this->lettore->leggi($this->archivio->file('/^T1_.*\.xls$/i')) as $riga) {
            if (ctype_digit($riga[2] ?? '')) {
                $aggiungi(TabellaF24::entiLocali, self::numerico($riga[2], 2), $riga[1] ?? '');
            }
        }
        foreach ([[TabellaF24::province, '/^T2_.*\.xls$/i'], [TabellaF24::provinceDirittiCamerali, '/^T6_.*\.xls$/i']] as [$tabella, $modello]) {
            foreach (array_slice($this->lettore->leggi($this->archivio->file($modello)), 2) as $riga) {
                foreach ([[0, 1], [2, 3], [4, 5]] as [$sigla, $nome]) {
                    if (preg_match('/^[A-Z]{2}$/', $riga[$sigla] ?? '') === 1) {
                        $aggiungi($tabella, $riga[$sigla], $riga[$nome] ?? '');
                    }
                }
            }
        }
        $righe = $this->lettore->leggi($this->archivio->file('/^Tabella_codici_sedi_INPS.*\.xlsx?$/i'));
        $this->verificaIntestazione($righe[0], [0 => 'CODICE SEDE', 1 => 'DIZIONE SEDE'], 'tabella sedi INPS');
        foreach (array_slice($righe, 2) as $riga) {
            if (ctype_digit($riga[0] ?? '')) {
                $aggiungi(TabellaF24::sediInps, self::numerico($riga[0], 4), $riga[1] ?? '', sprintf('visite mediche: %s; artigiani e commercianti: %s; altri contributi: %s', $riga[2] ?? '', $riga[3] ?? '', $riga[4] ?? ''));
            }
        }
        $righe = $this->lettore->leggi($this->archivio->file('/^Sedi_INAIL.*\.xls$/i'));
        $this->verificaIntestazione($righe[0], [0 => 'CODICE', 1 => 'NOME SEDE', 5 => 'PROVINCIA'], 'tabella sedi INAIL');
        foreach (array_slice($righe, 1) as $riga) {
            if (ctype_digit($riga[0] ?? '')) {
                $aggiungi(TabellaF24::sediInail, self::numerico($riga[0], 5), $riga[1] ?? '', $riga[5] ?? null);
            }
        }
        foreach (array_slice($this->lettore->leggi($this->archivio->file('/^Sedi_ENPALS.*\.xls$/i')), 2) as $riga) {
            $aggiungi(TabellaF24::sediEnpals, strtoupper($riga[0] ?? ''), $riga[1] ?? '');
        }
        foreach (array_slice($this->lettore->leggi($this->archivio->file('/^Codici_ALTRI_ENTI.*\.xls$/i')), 2) as $riga) {
            if (ctype_digit($riga[0] ?? '')) {
                $aggiungi(TabellaF24::altriEnti, self::numerico($riga[0], 4), $riga[1] ?? '');
            }
        }
        foreach ($this->lettore->leggi($this->archivio->file('/^Tabella_tipi_versamento_ELIDE.*\.xls$/i')) as $riga) {
            if (preg_match('/^[A-Z]$/', $riga[3] ?? '') === 1) {
                $aggiungi(TabellaF24::tipiElide, $riga[3], $riga[2] ?? '');
            }
        }
        foreach ($this->lettore->leggi($this->archivio->file('/^Formati_.*INPS.*\.xls$/i')) as $riga) {
            if (ctype_digit($riga[0] ?? '')) {
                $aggiungi(TabellaF24::formatiMatricolaInps, $riga[0], $riga[1] ?? '', self::espressioneFormato($riga[2] ?? ''));
            }
        }
        $conteggi = array_count_values(array_column($voci, 'tabella'));
        foreach (self::MINIMI_TABELLE as $tabella => $minimo) {
            if (($conteggi[$tabella] ?? 0) < $minimo) {
                throw new \UnexpectedValueException(sprintf('Tabella %s incompleta: %d voci.', TabellaF24::from($tabella)->getLabel(), $conteggi[$tabella] ?? 0));
            }
        }
        return array_values($voci);
    }

    /**
     * Espressione regolare del formato della matricola INPS dalla
     * descrizione della tabella, es. "8 numerici + 2 alfabetici (+ 7 blank a
     * riempimento)" => ^\d{8}[A-Z]{2}$; "17 blank" => campo vuoto.
     */
    private static function espressioneFormato(string $descrizione): string
    {
        $significativa = trim(preg_replace('/\(.*\)/', '', $descrizione));
        if (preg_match_all('/(\d+)\s+(numerici|alfanumerici|alfabetici|blank)/i', $significativa, $parti, PREG_SET_ORDER) === 0) {
            throw new \UnexpectedValueException('Formato della matricola INPS non riconosciuto: ' . $descrizione);
        }
        $espressione = '';
        foreach ($parti as [, $lunghezza, $tipo]) {
            $espressione .= match (strtolower($tipo)) {
                'numerici' => '\d{' . $lunghezza . '}',
                'alfanumerici' => '[A-Z0-9]{' . $lunghezza . '}',
                'alfabetici' => '[A-Z]{' . $lunghezza . '}',
                'blank' => '',
            };
        }
        return '^' . $espressione . '$';
    }

    private static function numerico(string $valore, int $lunghezza): string
    {
        return ctype_digit($valore) ? str_pad($valore, $lunghezza, '0', STR_PAD_LEFT) : '';
    }

    // ---------------------------------------------------------------
    // Causali contributo
    // ---------------------------------------------------------------

    /**
     * @param array<array<string, mixed>> $tabelle voci lette da leggiTabelle (per gli enti)
     * @return array<array<string, string|int|null>>
     */
    public function leggiCausali(array $tabelle): array
    {
        $causali = [];
        $righe = $this->lettore->leggi($this->archivio->file('/^Causali_INPS.*\.xls$/i'));
        $this->verificaIntestazione($righe[0], [0 => 'CAUSALE', 2 => 'MATRICOLA', 3 => 'INIZIO RIFERIMENTO', 4 => 'FINE RIFERIMENTO', 5 => 'MODALITA'], 'tabella causali INPS');
        foreach (array_slice($righe, 1) as $riga) {
            if (strtoupper($riga[0] ?? '') === 'NOTE') {
                break;
            }
            preg_match_all('/\d+/', $riga[2] ?? '', $formati);
            foreach (explode('-', strtoupper($riga[0] ?? '')) as $causale) {
                $this->aggiungiCausale($causali, CausaleContributo::ENTE_INPS, trim($causale), $riga[1] ?? '', implode(',', $formati[0]), null, $riga[3] ?? '', $riga[4] ?? '', $riga[5] ?? '');
            }
        }
        $enti = [];
        foreach ($tabelle as $voce) {
            if ($voce['tabella'] === TabellaF24::altriEnti->value) {
                $enti[self::chiaveNome($voce['descrizione'])] = $voce['codice'];
            }
        }
        $entiConCausali = [];
        foreach ($this->archivio->tuttiIFile('/^Causali_(?!INPS).*\.xlsx?$/i') as $file) {
            $righe = $this->lettore->leggi($file);
            $nome = self::chiaveNome(preg_replace('/^causali\s+(contributo\s+)?/i', '', $righe[0][0] ?? ''));
            $ente = $enti[$nome] ?? throw new \UnexpectedValueException('Ente non riconosciuto per la tabella ' . basename($file) . ': ' . ($righe[0][0] ?? ''));
            $entiConCausali[] = $ente;
            $intestazione = array_map(fn($titolo) => strtolower($titolo), $righe[1] ?? []);
            $colonna = fn(string $inizio) => array_key_first(array_filter($intestazione, fn($titolo) => str_starts_with($titolo, $inizio)));
            [$codice, $descrizione, $posizione, $inizio, $fine] = [$colonna('codice'), $colonna('descrizione'), $colonna('codice posizione'), $colonna('tipo inizio'), $colonna('tipo fine')];
            if (($codice === null) || ($descrizione === null)) {
                throw new \UnexpectedValueException('Tracciato della tabella ' . basename($file) . ' cambiato.');
            }
            foreach (array_slice($righe, 2) as $riga) {
                $this->aggiungiCausale($causali, $ente, strtoupper($riga[$codice] ?? ''), $riga[$descrizione] ?? '', null,
                        $posizione === null ? null : ($riga[$posizione] ?? null), $inizio === null ? '0' : ($riga[$inizio] ?? ''), $fine === null ? '0' : ($riga[$fine] ?? ''), 'D');
            }
        }
        foreach (array_diff(array_values($enti), $entiConCausali) as $ente) {
            $this->avvisi[] = 'ente ' . $ente . ' senza tabella delle causali nell\'archivio: causali controllate solo nel formato';
        }
        if (count(array_filter($causali, fn($causale) => $causale['ente'] === CausaleContributo::ENTE_INPS)) < 100) {
            throw new \UnexpectedValueException('Tabella delle causali INPS incompleta.');
        }
        $elenco = array_flip($this->leggiElencoCreditiPa());
        foreach ($causali as &$causale) {
            $causale['compensabile_crediti_pa'] = (int) (($causale['ente'] === CausaleContributo::ENTE_INPS) && isset($elenco[$causale['causale']]));
        }
        unset($causale);
        return array_values($causali);
    }

    private function aggiungiCausale(array &$causali, string $ente, string $causale, string $descrizione, ?string $formati, ?string $regolaPosizione, string $da, string $a, string $modalita): void
    {
        if (preg_match('/^[A-Z0-9]{2,4}$/', $causale) !== 1) {
            return;
        }
        $causali[$ente . '|' . $causale] ??= [
            'ente' => $ente,
            'causale' => $causale,
            'descrizione' => mb_substr($descrizione === '' ? $causale : $descrizione, 0, 500),
            'formati_matricola' => ($formati === null || $formati === '') ? null : $formati,
            'regola_posizione' => ($regolaPosizione === null || $regolaPosizione === '') ? null : mb_substr($regolaPosizione, 0, 255),
            'periodo_da' => self::obbligoPeriodo($da),
            'periodo_a' => self::obbligoPeriodo($a),
            'regola_periodo_da' => self::regolaPeriodo($da),
            'regola_periodo_a' => self::regolaPeriodo($a),
            'modalita' => in_array(strtoupper($modalita), ['D', 'R', 'E'], true) ? strtoupper($modalita) : 'D',
            'compensabile_crediti_pa' => 0,
            'in_uso' => 1,
        ];
    }

    /**
     * "0", "Valorizzato a 0/zero" o "considerato comunque errato": il periodo
     * non va indicato; "... oppure 0": facoltativo; altrimenti obbligatorio.
     */
    private static function obbligoPeriodo(string $regola): string
    {
        $regola = strtolower(trim($regola));
        return match (true) {
            in_array($regola, ['', '0', '0.0'], true), str_contains($regola, 'valorizzato a 0'), str_contains($regola, 'valorizzato a zero'), str_contains($regola, 'errato') => 'N',
            str_contains($regola, 'oppure 0') => 'F',
            default => 'O',
        };
    }

    private static function regolaPeriodo(string $regola): ?string
    {
        return in_array(trim($regola), ['', '0', '0.0'], true) ? null : mb_substr($regola, 0, 255);
    }

    private static function chiaveNome(string $nome): string
    {
        return preg_replace('/[^A-Z]/', '', strtoupper($nome));
    }
}
