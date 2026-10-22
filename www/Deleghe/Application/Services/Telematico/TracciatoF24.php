<?php

namespace Deleghe\Application\Services\Telematico;

use Deleghe\Application\Enumerations\ModelloF24;
use Deleghe\Application\Enumerations\SezioneF24;

/**
 * Tracciato dei record A, M, V e Z delle "Specifiche tecniche per la
 * trasmissione telematica dei modelli F24 da parte degli intermediari"
 * (Agenzia delle Entrate, Allegato 2 al provvedimento del 9 marzo 2015):
 * posizione (da 1), lunghezza e tipo di ogni campo.
 *
 * @see https://www.agenziaentrate.gov.it/portale/schede/pagamenti/f24/specifiche-tecniche-f24-ordinario
 */
class TracciatoF24
{

    public const RECORD_A = [
        'tipoRecord' => [1, 1, 'AN'],
        'codiceFornitura' => [16, 5, 'AN'],
        'tipoFornitore' => [21, 2, 'AN'],
        'codiceFiscaleFornitore' => [23, 16, 'AN'],
        'cognome' => [39, 24, 'AN'],
        'nome' => [63, 20, 'AN'],
        'sesso' => [83, 1, 'AN'],
        'dataNascita' => [84, 8, 'NU'],
        'comuneNascita' => [92, 40, 'AN'],
        'provinciaNascita' => [132, 2, 'AN'],
        'comuneDomicilioPf' => [134, 40, 'AN'],
        'provinciaDomicilioPf' => [174, 2, 'AN'],
        'indirizzoDomicilioPf' => [176, 35, 'AN'],
        'capDomicilioPf' => [211, 5, 'NU'],
        'denominazione' => [216, 60, 'AN'],
        'comuneSede' => [276, 40, 'AN'],
        'provinciaSede' => [316, 2, 'AN'],
        'indirizzoSede' => [318, 35, 'AN'],
        'capSede' => [353, 5, 'NU'],
        'comuneDomicilioPnf' => [358, 40, 'AN'],
        'provinciaDomicilioPnf' => [398, 2, 'AN'],
        'indirizzoDomicilioPnf' => [400, 35, 'AN'],
        'capDomicilioPnf' => [435, 5, 'NU'],
        'flagOrigine' => [440, 1, 'AN'],
        'telefono' => [455, 12, 'AN'],
        'email' => [467, 55, 'AN'],
        'progressivoInvio' => [522, 3, 'NU'],
        'numeroInvii' => [525, 3, 'NU'],
        'campoUtente' => [528, 100, 'AN'],
        'flagAccettazione' => [628, 1, 'AN'],
    ];
    public const RECORD_M = [
        'tipoRecord' => [1, 1, 'AN'],
        'codiceFiscale' => [2, 16, 'AN'],
        'progressivoModulo' => [18, 8, 'NU'],
        'identificazioneModello' => [54, 20, 'AN'],
        'valutaDelega' => [91, 1, 'AN'],
        'esercizioACavallo' => [92, 1, 'NU'],
        'flagVersante' => [93, 1, 'NU'],
        'codiceFiscaleVersante' => [94, 16, 'AN'],
        'tipoVersante' => [110, 1, 'NU'],
        'cognomeVersante' => [111, 24, 'AN'],
        'nomeVersante' => [135, 20, 'AN'],
        'sessoVersante' => [155, 1, 'AN'],
        'dataNascitaVersante' => [156, 8, 'NU'],
        'comuneNascitaVersante' => [164, 40, 'AN'],
        'provinciaNascitaVersante' => [204, 2, 'AN'],
        'comuneResidenzaVersante' => [206, 40, 'AN'],
        'provinciaResidenzaVersante' => [246, 2, 'AN'],
        'capResidenzaVersante' => [248, 5, 'NU'],
        'indirizzoResidenzaVersante' => [253, 35, 'AN'],
        'comuneDomicilio' => [288, 40, 'AN'],
        'provinciaDomicilio' => [328, 2, 'AN'],
        'capDomicilio' => [330, 5, 'NU'],
        'indirizzoDomicilio' => [335, 35, 'AN'],
        'telefono' => [370, 12, 'AN'],
        'cognome' => [438, 24, 'AN'],
        'nome' => [462, 20, 'AN'],
        'dataNascita' => [482, 8, 'NU'],
        'sesso' => [490, 1, 'AN'],
        'comuneNascita' => [491, 25, 'AN'],
        'provinciaNascita' => [516, 2, 'AN'],
        'denominazione' => [518, 55, 'AN'],
        'codiceIdentificativo' => [573, 2, 'AN'],
        'codiceFiscaleCoobbligato' => [575, 16, 'AN'],
        'iban' => [1741, 27, 'AN'],
        'tipoTitolareConto' => [1768, 2, 'AN'],
        'codiceFiscaleTitolareConto' => [1770, 16, 'AN'],
        'valuta' => [1869, 4, 'AN'],
        'saldoTotale' => [1873, 15, 'AN'],
        'dataVersamento' => [1888, 10, 'AN'],
    ];
    public const RECORD_Z = [
        'tipoRecord' => [1, 1, 'AN'],
        'numeroRecordV' => [16, 9, 'NU'],
        'numeroRecordM' => [25, 9, 'NU'],
    ];
    private const TESTA_V = [
        'tipoRecord' => [1, 1, 'AN'],
        'codiceFiscale' => [2, 16, 'AN'],
        'progressivoModulo' => [18, 8, 'NU'],
        'identificazioneModello' => [54, 20, 'AN'],
        'tipoModello' => [90, 1, 'AN'],
        'codiceUfficio' => [91, 3, 'AN'],
        'codiceAtto' => [94, 11, 'NU'],
        'saldoFinale' => [1793, 15, 'NU'],
        'dataVersamento' => [1808, 8, 'NU'],
    ];
    /**
     * Sezioni del record V con sezione IMU (tipo modello "A"): primo rigo,
     * passo tra i righi, campi del rigo (scostamento, lunghezza, tipo) e
     * posizione dei totali.
     */
    public const SEZIONI_V = [
        'ERA' => ['inizio' => 105, 'passo' => 58, 'righi' => 6, 'totali' => 453, 'campi' => [
                'codiceTributo' => [0, 4, 'AN'], 'numeroCertificazione' => [4, 16, 'AN'], 'rateazione' => [20, 4, 'AN'], 'anno' => [24, 4, 'NU'], 'debito' => [28, 15, 'NU'], 'credito' => [43, 15, 'NU']]],
        'INP' => ['inizio' => 499, 'passo' => 67, 'righi' => 4, 'totali' => 767, 'campi' => [
                'codiceSede' => [0, 4, 'NU'], 'causale' => [4, 4, 'AN'], 'matricola' => [8, 17, 'AN'], 'periodoDa' => [25, 6, 'NU'], 'periodoA' => [31, 6, 'NU'], 'debito' => [37, 15, 'NU'], 'credito' => [52, 15, 'NU']]],
        'REG' => ['inizio' => 813, 'passo' => 44, 'righi' => 4, 'totali' => 989, 'campi' => [
                'codiceRegione' => [0, 2, 'NU'], 'codiceTributo' => [2, 4, 'AN'], 'rateazione' => [6, 4, 'AN'], 'anno' => [10, 4, 'NU'], 'debito' => [14, 15, 'NU'], 'credito' => [29, 15, 'NU']]],
        'LOC' => ['inizio' => 1053, 'passo' => 68, 'righi' => 4, 'totali' => 1325, 'campi' => [
                'codiceEnte' => [0, 4, 'AN'], 'ravvedimento' => [4, 1, 'NU'], 'immobiliVariati' => [5, 1, 'NU'], 'acconto' => [6, 1, 'NU'], 'saldo' => [7, 1, 'NU'],
                'numeroImmobili' => [8, 3, 'NU'], 'detrazione' => [11, 15, 'NU'], 'codiceTributo' => [26, 4, 'AN'], 'rateazione' => [30, 4, 'AN'], 'anno' => [34, 4, 'NU'],
                'debito' => [38, 15, 'NU'], 'credito' => [53, 15, 'NU']]],
        'INA' => ['inizio' => 1371, 'passo' => 52, 'righi' => 3, 'totali' => 1527, 'campi' => [
                'codiceSede' => [0, 5, 'NU'], 'codiceDitta' => [5, 8, 'NU'], 'cifraControllo' => [13, 2, 'NU'], 'numeroRiferimento' => [15, 6, 'NU'], 'causale' => [21, 1, 'AN'],
                'debito' => [22, 15, 'NU'], 'credito' => [37, 15, 'NU']]],
        'PRE' => ['inizio' => 1577, 'passo' => 60, 'righi' => 2, 'totali' => 1697, 'campi' => [
                'codiceSede' => [0, 5, 'AN'], 'causale' => [5, 4, 'AN'], 'codicePosizione' => [9, 9, 'NU'], 'periodoDa' => [18, 6, 'NU'], 'periodoA' => [24, 6, 'NU'],
                'debito' => [30, 15, 'NU'], 'credito' => [45, 15, 'NU']]],
    ];
    public const IDENTIFICATIVO_OPERAZIONE = [1035, 18, 'AN'];
    public const CODICE_ALTRO_ENTE = [1573, 4, 'NU'];
    /**
     * Record V del modello con elementi identificativi (tipo "8"): 28 righi
     * con il solo importo a debito.
     */
    public const SEZIONE_ELIDE = ['inizio' => 105, 'passo' => 41, 'righi' => 28, 'campi' => [
            'tipo' => [0, 1, 'AN'], 'elementiIdentificativi' => [1, 17, 'AN'], 'codiceTributo' => [18, 4, 'AN'], 'anno' => [22, 4, 'NU'], 'debito' => [26, 15, 'NU']]];
    public const TOTALE_ELIDE = 1253;
    /**
     * Record V del modello con sezione accise (tipo "B"): dalla posizione 91
     * alla 1370 come il tipo "A" (Erario, INPS, Regioni, IMU), poi la
     * sezione accise con codice ufficio e atto propri e 7 righi a debito.
     */
    public const SEZIONE_ACCISE = ['inizio' => 1385, 'passo' => 47, 'righi' => 7, 'campi' => [
            'ente' => [0, 2, 'AN'], 'provincia' => [2, 2, 'AN'], 'codiceTributo' => [4, 4, 'AN'], 'codiceIdentificativo' => [8, 14, 'AN'],
            'rateazione' => [22, 4, 'AN'], 'periodo' => [26, 6, 'NU'], 'debito' => [32, 15, 'NU']]];
    public const UFFICIO_ACCISE = [1371, 3, 'AN'];
    public const ATTO_ACCISE = [1374, 11, 'NU'];
    public const TOTALE_ACCISE = 1714;
    private const SEZIONI_ACCISE = ['ERA', 'INP', 'REG', 'LOC'];
    public const FILLER_ELIDE = 1268;

    /**
     * Campi del record V per il tipo di modello: testata, campi di ogni
     * rigo (es. "ERA.2.debito") e totali di sezione ("ERA.totaleDebito").
     *
     * @return array<string, array{0: int, 1: int, 2: string}>
     */
    public static function recordV(ModelloF24 $modello): array
    {
        $campi = self::TESTA_V;
        if ($modello === ModelloF24::elide) {
            self::aggiungiRighi($campi, SezioneF24::elide->value, self::SEZIONE_ELIDE);
            $campi['ELI.totaleDebito'] = [self::TOTALE_ELIDE, 15, 'NU'];
            $campi['ELI.filler'] = [self::FILLER_ELIDE, 15, 'NU'];
            $campi['ELI.segno'] = [self::TOTALE_ELIDE + 30, 1, 'AN'];
            $campi['ELI.saldo'] = [self::TOTALE_ELIDE + 31, 15, 'NU'];
            return $campi;
        }
        foreach (self::SEZIONI_V as $sezione => $definizione) {
            if (($modello === ModelloF24::accise) && (in_array($sezione, self::SEZIONI_ACCISE, true) === false)) {
                continue;
            }
            self::aggiungiRighi($campi, $sezione, $definizione);
            $campi[$sezione . '.totaleDebito'] = [$definizione['totali'], 15, 'NU'];
            $campi[$sezione . '.totaleCredito'] = [$definizione['totali'] + 15, 15, 'NU'];
            $campi[$sezione . '.segno'] = [$definizione['totali'] + 30, 1, 'AN'];
            $campi[$sezione . '.saldo'] = [$definizione['totali'] + 31, 15, 'NU'];
        }
        $campi['LOC.identificativoOperazione'] = self::IDENTIFICATIVO_OPERAZIONE;
        if ($modello === ModelloF24::accise) {
            self::aggiungiRighi($campi, SezioneF24::accise->value, self::SEZIONE_ACCISE);
            $campi['ACC.codiceUfficio'] = self::UFFICIO_ACCISE;
            $campi['ACC.codiceAtto'] = self::ATTO_ACCISE;
            $campi['ACC.totaleDebito'] = [self::TOTALE_ACCISE, 15, 'NU'];
            $campi['ACC.segno'] = [self::TOTALE_ACCISE + 15, 1, 'AN'];
            $campi['ACC.saldo'] = [self::TOTALE_ACCISE + 16, 15, 'NU'];
            return $campi;
        }
        $campi['PRE.codiceEnte'] = self::CODICE_ALTRO_ENTE;
        return $campi;
    }

    private static function aggiungiRighi(array &$campi, string $sezione, array $definizione): void
    {
        for ($rigo = 1; $rigo <= $definizione['righi']; $rigo++) {
            $inizio = $definizione['inizio'] + ($rigo - 1) * $definizione['passo'];
            foreach ($definizione['campi'] as $nome => [$scostamento, $lunghezza, $tipo]) {
                $campi[$sezione . '.' . $rigo . '.' . $nome] = [$inizio + $scostamento, $lunghezza, $tipo];
            }
        }
    }
}
