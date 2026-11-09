<?php

namespace Dichiarativi\Application\Services\Telematico;

/**
 * Campi posizionali della fornitura del modello Redditi PF ("RPF26",
 * specifiche tecniche del 27 febbraio 2026 aggiornate al 10 giugno 2026):
 * posizione (da 1), lunghezza e tipo dei campi usati dei record A, B e Z.
 * Record di 1.900 caratteri.
 */
class TracciatoRedditi
{

    public const RECORD_A = TracciatoTr::RECORD_A;
    public const RECORD_Z = TracciatoTr::RECORD_Z;
    public const RECORD_B = [
        'tipoRecord' => [1, 1, 'AN'],
        'codiceFiscale' => [2, 16, 'AN'],
        'progressivoModulo' => [18, 8, 'NU'],
        'identificativoProduttore' => [74, 16, 'AN'],
        'flagConferma' => [90, 1, 'NU'],
        'moduloRw' => [94, 1, 'NU'],
        'quadroVo' => [95, 1, 'NU'],
        'quadroAc' => [96, 1, 'NU'],
        'correttiva' => [103, 1, 'NU'],
        'cognome' => [107, 24, 'AN'],
        'nome' => [131, 20, 'AN'],
        'partitaIva' => [211, 11, 'AN'],
        'telefono' => [222, 12, 'AN'],
        'cellulare' => [234, 12, 'AN'],
        'email' => [246, 50, 'AN'],
        'comuneNascita' => [296, 40, 'AN'],
        'provinciaNascita' => [336, 2, 'AN'],
        'dataNascita' => [338, 8, 'NU'],
        'sesso' => [346, 1, 'AN'],
        'comuneResidenza' => [348, 40, 'AN'],
        'provinciaResidenza' => [388, 2, 'AN'],
        'capResidenza' => [390, 5, 'NU'],
        'codiceComuneResidenza' => [395, 4, 'AN'],
        'indirizzoResidenza' => [419, 35, 'AN'],
        'civicoResidenza' => [454, 10, 'AN'],
        'frazioneResidenza' => [464, 35, 'AN'],
        'dataVariazioneResidenza' => [499, 8, 'NU'],
        'domicilioDiverso' => [507, 1, 'NU'],
        'comuneDomicilio' => [515, 40, 'AN'],
        'provinciaDomicilio' => [555, 2, 'AN'],
        'codiceComuneDomicilio' => [557, 4, 'AN'],
        'comuneDomicilioSuccessivo' => [565, 40, 'AN'],
        'provinciaDomicilioSuccessivo' => [605, 2, 'AN'],
        'codiceComuneDomicilioSuccessivo' => [607, 4, 'AN'],
        // scelte dell'otto, cinque e due per mille: caselle per istituzione e finalità (OttoPerMille, CinquePerMille)
        'sceltaStato' => [687, 1, 'NU'],
        'ottoST' => [688, 1, 'NU'],
        'ottoCC' => [689, 1, 'NU'],
        'ottoAV' => [690, 1, 'NU'],
        'ottoAD' => [691, 1, 'NU'],
        'ottoVA' => [692, 1, 'NU'],
        'ottoLU' => [693, 1, 'NU'],
        'ottoEB' => [694, 1, 'NU'],
        'ottoOR' => [695, 1, 'NU'],
        'ottoAP' => [696, 1, 'NU'],
        'ottoBA' => [697, 1, 'NU'],
        'ottoBU' => [698, 1, 'NU'],
        'ottoIN' => [699, 1, 'NU'],
        'ottoSG' => [700, 1, 'NU'],
        'ottoCI' => [701, 1, 'NU'],
        'otto' => [702, 1, 'NU'],
        'cinqueTS' => [704, 1, 'NU'],
        'beneficiarioTS' => [705, 11, 'AN'],
        'cinqueRS' => [716, 1, 'NU'],
        'beneficiarioRS' => [717, 11, 'AN'],
        'cinqueRN' => [728, 1, 'NU'],
        'beneficiarioRN' => [729, 11, 'AN'],
        'cinqueBC' => [740, 1, 'NU'],
        'beneficiarioBC' => [741, 11, 'AN'],
        'cinqueCO' => [752, 1, 'NU'],
        'cinqueSD' => [753, 1, 'NU'],
        'beneficiarioSD' => [754, 11, 'AN'],
        'cinqueAP' => [765, 1, 'NU'],
        'beneficiarioAP' => [766, 11, 'AN'],
        'cinque' => [778, 1, 'NU'],
        'codicePartito' => [780, 3, 'AN'],
        'firmaPartito' => [783, 1, 'NU'],
        'due' => [784, 1, 'NU'],
        'firmaDichiarante' => [1038, 1, 'NU'],
        'codiceFiscaleRappresentante' => [1430, 16, 'AN'],
        'codiceCaricaRappresentante' => [1446, 2, 'NU'],
        'codiceFiscaleIncaricato' => [1674, 16, 'AN'],
        'impegno' => [1690, 1, 'NU'],
        'dataImpegno' => [1693, 8, 'NU'],
        'firmaIncaricato' => [1701, 1, 'NU'],
        'codiceFiscaleResponsabileCaf' => [1703, 16, 'AN'],
        'codiceFiscaleCaf' => [1719, 11, 'NU'],
        'codiceFiscaleProfessionista' => [1730, 16, 'AN'],
        'firmaVisto' => [1746, 1, 'NU'],
    ];

    /** Caselle dei quadri compilati del record B (posizione) */
    public const CASELLE_QUADRI = [
        'RA' => 987, 'RB' => 988, 'RC' => 989, 'RD' => 990, 'RE' => 991, 'RF' => 992, 'RG' => 993, 'RH' => 994, 'RL' => 997, 'RM' => 998,
        'RN' => 999, 'RP' => 1001, 'RQ' => 1002, 'RR' => 1003, 'RS' => 1004, 'RT' => 1005, 'RU' => 1006, 'RV' => 1007, 'RX' => 1008,
        'FA' => 1011, 'CR' => 1012, 'CE' => 1013, 'LM' => 1014, 'TR' => 1015, 'FC' => 1016, 'DI' => 1021, 'LC' => 1023, 'NR' => 1024, 'CP' => 1025,
    ];

    /** Caselle dei quadri in testa al record B */
    public const CASELLE_TESTA = ['RW' => 'moduloRw', 'VO' => 'quadroVo', 'AC' => 'quadroAc'];
}
