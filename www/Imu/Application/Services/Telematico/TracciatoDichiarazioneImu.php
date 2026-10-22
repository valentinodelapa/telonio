<?php

namespace Imu\Application\Services\Telematico;

/**
 * Tracciati delle forniture telematiche della dichiarazione IMU (D.M. 24
 * aprile 2024, specifiche tecniche pubblicate dal Dipartimento delle
 * Finanze): posizione (da 1), lunghezza e tipo di ogni campo usato.
 *
 * Record di 1.900 caratteri; formati: AN (alfanumerico, a sinistra con
 * spazi; anche CF, PN, PR), NU (numerico, a destra con zeri; anche DT
 * GGMMAAAA, CB 0/1, CN). Importi in unità di euro, tranne quelli del quadro
 * B ENC indicati "in centesimi"; percentuali con parte intera e decimale in
 * campi distinti.
 *
 * Modello persone fisiche ed enti commerciali ("TAT00"): record A, B, C
 * (contitolari, non usato: ogni contitolare presenta la propria
 * dichiarazione), D (due immobili per record), Z. Modello enti non
 * commerciali ("TAS00"): A, B, C (quadro A, due immobili per record), D
 * (quadro B, un immobile per record), E (quadri C e D), Z.
 *
 * @see https://www.finanze.gov.it/it/fiscalita/fiscalita-regionale-e-locale/Imposta-municipale-propria-IMU/decreti-ministeriali/
 */
class TracciatoDichiarazioneImu
{

    public const RECORD_A = [
        'tipoRecord' => [1, 1, 'AN'],
        'codiceFornitura' => [16, 5, 'AN'],
        'tipoFornitore' => [21, 2, 'AN'],
        'codiceFiscaleFornitore' => [23, 16, 'AN'],
        'tipologiaDichiarazione' => [39, 1, 'AN'],
        'progressivoInvio' => [522, 4, 'NU'],
        'numeroInvii' => [526, 4, 'NU'],
    ];

    /** Frontespizio, modello persone fisiche ed enti commerciali */
    public const RECORD_B = [
        'tipoRecord' => [1, 1, 'AN'],
        'codiceFiscale' => [2, 16, 'AN'],
        'progressivoModulo' => [18, 8, 'NU'],
        'annoDichiarazione' => [90, 4, 'NU'],
        'periodoImposta' => [94, 4, 'NU'],
        'codiceComune' => [98, 4, 'AN'],
        'denominazioneComune' => [102, 50, 'AN'],
        'codiceFiscaleContribuente' => [152, 16, 'AN'],
        'telefonoPrefisso' => [168, 4, 'AN'],
        'telefonoNumero' => [172, 8, 'AN'],
        'email' => [180, 50, 'AN'],
        'cognome' => [230, 24, 'AN'],
        'nome' => [254, 20, 'AN'],
        'dataNascita' => [274, 8, 'NU'],
        'sesso' => [282, 1, 'AN'],
        'comuneNascita' => [283, 40, 'AN'],
        'provinciaNascita' => [323, 2, 'AN'],
        'indirizzo' => [328, 35, 'AN'],
        'numeroCivico' => [363, 5, 'AN'],
        'scala' => [368, 5, 'AN'],
        'piano' => [373, 5, 'AN'],
        'interno' => [378, 5, 'AN'],
        'cap' => [383, 5, 'AN'],
        'comune' => [388, 100, 'AN'],
        'provincia' => [488, 2, 'AN'],
        'statoEstero' => [490, 3, 'NU'],
        'codiceFiscaleDichiarante' => [493, 16, 'AN'],
        'codiceCarica' => [509, 2, 'AN'],
        'cognomeDichiarante' => [511, 24, 'AN'],
        'nomeDichiarante' => [535, 20, 'AN'],
        'telefonoPrefissoDichiarante' => [555, 4, 'AN'],
        'telefonoNumeroDichiarante' => [559, 8, 'AN'],
        'emailDichiarante' => [567, 50, 'AN'],
        'indirizzoDichiarante' => [617, 35, 'AN'],
        'numeroCivicoDichiarante' => [652, 5, 'AN'],
        'scalaDichiarante' => [657, 5, 'AN'],
        'pianoDichiarante' => [662, 5, 'AN'],
        'internoDichiarante' => [667, 5, 'AN'],
        'capDichiarante' => [672, 5, 'AN'],
        'comuneDichiarante' => [677, 100, 'AN'],
        'provinciaDichiarante' => [777, 2, 'AN'],
        'statoEsteroDichiarante' => [779, 3, 'NU'],
        'codiceFiscaleIntermediario' => [782, 16, 'AN'],
        'numeroAlboCaf' => [798, 5, 'NU'],
        'impegno' => [803, 1, 'NU'],
        'dataImpegno' => [804, 8, 'NU'],
        'firmaIntermediario' => [812, 1, 'NU'],
    ];

    /**
     * Immobile del record D (modello persone fisiche ed enti commerciali):
     * posizioni del primo immobile, il secondo è spostato di
     * SCOSTAMENTO_IMMOBILE_D.
     */
    public const IMMOBILE_D = [
        'numeroOrdine' => [90, 4, 'NU'],
        'progressivoImmobile' => [94, 4, 'NU'],
        'continuita' => [98, 1, 'NU'],
        'caratteristiche' => [99, 3, 'AN'],
        'indirizzo' => [102, 100, 'AN'],
        'catasto' => [202, 1, 'AN'],
        'codiceCatastale' => [203, 5, 'AN'],
        'sezione' => [208, 3, 'AN'],
        'foglio' => [211, 4, 'AN'],
        'particella' => [215, 10, 'AN'],
        'subalterno' => [225, 4, 'AN'],
        'categoria' => [229, 25, 'AN'],
        'classe' => [254, 10, 'AN'],
        'protocollo' => [264, 20, 'AN'],
        'annoProtocollo' => [284, 4, 'NU'],
        'valore' => [288, 15, 'NU'],
        'percentualeIntera' => [303, 3, 'NU'],
        'percentualeDecimale' => [306, 2, 'NU'],
        'detrazione' => [308, 9, 'NU'],
        'equiparazione' => [317, 1, 'AN'],
        'riduzioni' => [318, 1, 'AN'],
        'esenzione' => [319, 1, 'NU'],
        'acquisto' => [320, 1, 'NU'],
        'cessione' => [321, 1, 'NU'],
        'altro' => [322, 1, 'NU'],
        'altroDescrizione' => [323, 100, 'AN'],
        'dataVariazione' => [423, 8, 'NU'],
        'inizioTermine' => [431, 1, 'AN'],
        'agenziaEntrate' => [432, 24, 'AN'],
        'estremiTitolo' => [456, 24, 'AN'],
        'nonUtilizzabileTipo' => [480, 1, 'AN'],
        'nonUtilizzabileAutorita' => [481, 100, 'AN'],
        'nonUtilizzabileData' => [581, 8, 'NU'],
    ];
    public const SCOSTAMENTO_IMMOBILE_D = 499;
    public const CODA_D = [
        'annotazioni' => [1088, 500, 'AN'],
        'data' => [1588, 8, 'NU'],
        'firma' => [1596, 1, 'NU'],
    ];

    public const RECORD_Z = [
        'tipoRecord' => [1, 1, 'AN'],
        'numeroRecordB' => [2, 9, 'NU'],
        'numeroRecordC' => [11, 9, 'NU'],
        'numeroRecordD' => [20, 9, 'NU'],
        'numeroRecordE' => [29, 9, 'NU'],
    ];

    /** Frontespizio, modello enti non commerciali */
    public const RECORD_B_ENC = [
        'tipoRecord' => [1, 1, 'AN'],
        'codiceFiscale' => [2, 16, 'AN'],
        'progressivoModulo' => [18, 8, 'NU'],
        'annoDichiarazione' => [90, 4, 'NU'],
        'periodoImposta' => [94, 4, 'NU'],
        'codiceComune' => [98, 4, 'AN'],
        'denominazioneComune' => [102, 50, 'AN'],
        'denominazione' => [152, 60, 'AN'],
        'codiceFiscaleContribuente' => [212, 16, 'AN'],
        'telefonoPrefisso' => [228, 4, 'AN'],
        'telefonoNumero' => [232, 8, 'AN'],
        'email' => [240, 50, 'AN'],
        'indirizzo' => [290, 35, 'AN'],
        'numeroCivico' => [325, 5, 'AN'],
        'scala' => [330, 5, 'AN'],
        'piano' => [335, 5, 'AN'],
        'interno' => [340, 5, 'AN'],
        'cap' => [345, 5, 'AN'],
        'comune' => [350, 100, 'AN'],
        'provincia' => [450, 2, 'AN'],
        'statoEstero' => [452, 3, 'NU'],
        'codiceFiscaleRappresentante' => [455, 16, 'AN'],
        'codiceCarica' => [471, 2, 'AN'],
        'cognomeRappresentante' => [473, 24, 'AN'],
        'nomeRappresentante' => [497, 20, 'AN'],
        'sessoRappresentante' => [517, 1, 'AN'],
        'dataNascitaRappresentante' => [518, 8, 'NU'],
        'comuneNascitaRappresentante' => [526, 40, 'AN'],
        'provinciaNascitaRappresentante' => [566, 2, 'AN'],
        'residenzaEstero' => [568, 1, 'NU'],
        'statoEsteroResidenza' => [569, 3, 'NU'],
        'telefonoPrefissoRappresentante' => [655, 4, 'AN'],
        'telefonoNumeroRappresentante' => [659, 8, 'AN'],
        'codiceFiscaleSocietaDichiarante' => [667, 11, 'NU'],
        'numeroImmobiliQuadroA' => [678, 9, 'NU'],
        'numeroOrdiniQuadroA' => [687, 9, 'NU'],
        'numeroImmobiliQuadroB' => [696, 9, 'NU'],
        'quadroC' => [705, 1, 'NU'],
        'quadroD' => [706, 1, 'NU'],
        'firma' => [707, 1, 'NU'],
        'codiceFiscaleIntermediario' => [708, 16, 'AN'],
        'numeroAlboCaf' => [724, 5, 'NU'],
        'dataImpegno' => [729, 8, 'NU'],
        'firmaIntermediario' => [737, 1, 'NU'],
    ];

    /** Immobile del quadro A ENC (record C): primo immobile, il secondo +487 */
    public const IMMOBILE_C_ENC = [
        'numeroOrdine' => [90, 4, 'NU'],
        'progressivoImmobile' => [94, 4, 'NU'],
        'continuita' => [98, 1, 'NU'],
        'caratteristiche' => [99, 1, 'AN'],
        'indirizzo' => [100, 100, 'AN'],
        'catasto' => [200, 1, 'AN'],
        'codiceCatastale' => [201, 5, 'AN'],
        'sezione' => [206, 3, 'AN'],
        'foglio' => [209, 4, 'AN'],
        'particella' => [213, 10, 'AN'],
        'subalterno' => [223, 4, 'AN'],
        'categoria' => [227, 25, 'AN'],
        'classe' => [252, 10, 'AN'],
        'protocollo' => [262, 20, 'AN'],
        'annoProtocollo' => [282, 4, 'AN'],
        'valore' => [286, 15, 'NU'],
        'percentualeIntera' => [301, 3, 'NU'],
        'percentualeDecimale' => [304, 2, 'NU'],
        'riduzioni' => [306, 1, 'NU'],
        'esenzione' => [307, 1, 'NU'],
        'acquisto' => [308, 1, 'NU'],
        'cessione' => [309, 1, 'NU'],
        'altro' => [310, 1, 'NU'],
        'altroDescrizione' => [311, 100, 'AN'],
        'dataVariazione' => [411, 8, 'NU'],
        'inizioTermine' => [419, 1, 'AN'],
        'agenziaEntrate' => [420, 24, 'AN'],
        'estremiTitolo' => [444, 24, 'AN'],
        'nonUtilizzabileTipo' => [468, 1, 'AN'],
        'nonUtilizzabileAutorita' => [469, 100, 'AN'],
        'nonUtilizzabileData' => [569, 8, 'NU'],
    ];
    public const SCOSTAMENTO_IMMOBILE_C_ENC = 487;
    public const CODA_C_ENC = [
        'annotazioni' => [1064, 500, 'AN'],
    ];

    /** Immobile del quadro B ENC (record D), uno per record */
    public const IMMOBILE_D_ENC = [
        'numeroOrdine' => [90, 4, 'NU'],
        'progressivoImmobile' => [94, 4, 'NU'],
        'continuita' => [98, 1, 'NU'],
        'attivitaAS' => [99, 1, 'NU'],
        'attivitaPR' => [100, 1, 'NU'],
        'attivitaSA' => [101, 1, 'NU'],
        'attivitaDI' => [102, 1, 'NU'],
        'attivitaRI' => [103, 1, 'NU'],
        'attivitaCU' => [104, 1, 'NU'],
        'attivitaRC' => [105, 1, 'NU'],
        'attivitaSP' => [106, 1, 'NU'],
        'attivitaRE' => [107, 1, 'NU'],
        'attivitaRS' => [108, 1, 'NU'],
        'caratteristiche' => [109, 1, 'AN'],
        'indirizzo' => [110, 100, 'AN'],
        'catasto' => [210, 1, 'AN'],
        'codiceCatastale' => [211, 5, 'AN'],
        'sezione' => [216, 3, 'AN'],
        'foglio' => [219, 4, 'AN'],
        'particella' => [223, 10, 'AN'],
        'subalterno' => [233, 4, 'AN'],
        'categoria' => [237, 25, 'AN'],
        'classe' => [262, 10, 'AN'],
        'protocollo' => [272, 20, 'AN'],
        'annoProtocollo' => [292, 4, 'AN'],
        'valore' => [296, 15, 'NU'],
        'percentualeIntera' => [311, 3, 'NU'],
        'percentualeDecimale' => [314, 2, 'NU'],
        'riduzioni' => [316, 1, 'NU'],
        'esenzione' => [317, 1, 'NU'],
        'acquisto' => [318, 1, 'NU'],
        'cessione' => [319, 1, 'NU'],
        'altro' => [320, 1, 'NU'],
        'altroDescrizione' => [321, 100, 'AN'],
        'dataVariazione' => [421, 8, 'NU'],
        'inizioTermine' => [429, 1, 'AN'],
        'agenziaEntrate' => [430, 24, 'AN'],
        'estremiTitolo' => [454, 24, 'AN'],
        'tipoConcessione' => [478, 1, 'AN'],
        'comodatario' => [479, 100, 'AN'],
        'didatticaCm' => [579, 9, 'NU'],
        'didatticaCms' => [588, 9, 'NU'],
        'didatticaSuperficieIntera' => [597, 3, 'NU'],
        'didatticaSuperficieDecimale' => [600, 2, 'NU'],
        'didatticaSuperficieGiorniIntera' => [602, 3, 'NU'],
        'didatticaSuperficieGiorniDecimale' => [605, 2, 'NU'],
        'didatticaSoggettiIntera' => [607, 3, 'NU'],
        'didatticaSoggettiDecimale' => [610, 2, 'NU'],
        'didatticaSoggettiGiorniIntera' => [612, 3, 'NU'],
        'didatticaSoggettiGiorniDecimale' => [615, 2, 'NU'],
        'didatticaGiorniIntera' => [617, 3, 'NU'],
        'didatticaGiorniDecimale' => [620, 2, 'NU'],
        'didatticaPercentualeIntera' => [622, 3, 'NU'],
        'didatticaPercentualeDecimale' => [625, 2, 'NU'],
        'didatticaValoreImponibile' => [627, 12, 'NU'],
        'didatticaValoreEsente' => [639, 12, 'NU'],
        'didatticaCmMinoreCms' => [651, 1, 'NU'],
        'didatticaCmMaggioreCms' => [652, 1, 'NU'],
        'didatticaRapportoIntera' => [653, 3, 'NU'],
        'didatticaRapportoDecimale' => [656, 2, 'NU'],
        'didatticaValoreParziale' => [658, 12, 'NU'],
        'didatticaValoreComplessivo' => [670, 12, 'NU'],
        'altreCenc' => [682, 9, 'NU'],
        'altreCm' => [691, 9, 'NU'],
        'altreSuperficieIntera' => [700, 3, 'NU'],
        'altreSuperficieDecimale' => [703, 2, 'NU'],
        'altreSuperficieGiorniIntera' => [705, 3, 'NU'],
        'altreSuperficieGiorniDecimale' => [708, 2, 'NU'],
        'altreSoggettiIntera' => [710, 3, 'NU'],
        'altreSoggettiDecimale' => [713, 2, 'NU'],
        'altreSoggettiGiorniIntera' => [715, 3, 'NU'],
        'altreSoggettiGiorniDecimale' => [718, 2, 'NU'],
        'altreGiorniIntera' => [720, 3, 'NU'],
        'altreGiorniDecimale' => [723, 2, 'NU'],
        'altrePercentualeIntera' => [725, 3, 'NU'],
        'altrePercentualeDecimale' => [728, 2, 'NU'],
        'altreValoreImponibile' => [730, 12, 'NU'],
        'annotazioni' => [742, 500, 'AN'],
    ];

    /** Quadri C e D ENC (record E) */
    public const RECORD_E_ENC = [
        'tipoRecord' => [1, 1, 'AN'],
        'codiceFiscale' => [2, 16, 'AN'],
        'progressivoModulo' => [18, 8, 'NU'],
        'impostaDovuta' => [90, 12, 'NU'],
        'eccedenzaPrecedente' => [102, 12, 'NU'],
        'eccedenzaCompensata' => [114, 12, 'NU'],
        'rateVersate' => [126, 12, 'NU'],
        'impostaDebito' => [138, 12, 'NU'],
        'impostaCredito' => [150, 12, 'NU'],
        'creditoDichiarazione' => [162, 12, 'NU'],
        'creditoRimborso' => [174, 12, 'NU'],
        'creditoCompensazione' => [186, 12, 'NU'],
    ];

    public const RECORD_Z_ENC = [
        'tipoRecord' => [1, 1, 'AN'],
        'numeroRecordB' => [2, 9, 'NU'],
        'numeroRecordC' => [11, 9, 'NU'],
        'numeroRecordD' => [20, 9, 'NU'],
    ];

    /**
     * Campi di un immobile spostati della posizione indicata.
     *
     * @param array<string, array{0: int, 1: int, 2: string}> $campi
     * @return array<string, array{0: int, 1: int, 2: string}>
     */
    public static function sposta(array $campi, int $scostamento, string $prefisso): array
    {
        $spostati = [];
        foreach ($campi as $nome => [$posizione, $lunghezza, $tipo]) {
            $spostati[$prefisso . ucfirst($nome)] = [$posizione + $scostamento, $lunghezza, $tipo];
        }
        return $spostati;
    }
}
