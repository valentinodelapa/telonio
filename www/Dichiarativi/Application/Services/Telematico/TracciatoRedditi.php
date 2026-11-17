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

    public const RECORD_A = [
        'tipoRecord' => [1, 1, 'AN'],
        'codiceFornitura' => [16, 5, 'AN'],
        'tipoFornitore' => [21, 2, 'NU'],
        'codiceFiscaleFornitore' => [23, 16, 'AN'],
        // dichiarazione su più invii: zeri per la dichiarazione in un solo invio
        'progressivoInvio' => [522, 4, 'NU'],
        'totaleInvii' => [526, 4, 'NU'],
    ];
    public const RECORD_Z = [
        'tipoRecord' => [1, 1, 'AN'],
        'numeroRecordB' => [16, 9, 'NU'],
        'numeroRecordC' => [25, 9, 'NU'],
        'numeroRecordP' => [34, 9, 'NU'],
        'numeroRecordQ' => [43, 9, 'NU'],
        'numeroRecordR' => [52, 9, 'NU'],
    ];
    public const RECORD_B = [
        'tipoRecord' => [1, 1, 'AN'],
        'codiceFiscale' => [2, 16, 'AN'],
        'progressivoModulo' => [18, 8, 'NU'],
        'identificativoProduttore' => [74, 16, 'AN'],
        'flagConferma' => [90, 1, 'NU'],
        // campo 9 "Flag conferma Studi di settore/parametri": conferma delle anomalie dei modelli ISA allegati
        'flagConfermaIsa' => [91, 1, 'NU'],
        'moduloRw' => [94, 1, 'NU'],
        'quadroVo' => [95, 1, 'NU'],
        'quadroAc' => [96, 1, 'NU'],
        // campo 15: modello ISA allegato (record P, Q ed R)
        'isa' => [97, 1, 'NU'],
        'correttiva' => [103, 1, 'NU'],
        'cognome' => [107, 24, 'AN'],
        'nome' => [131, 20, 'AN'],
        'partitaIva' => [211, 11, 'NU'],
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
        'beneficiarioTS' => [705, 11, 'NU'],
        'cinqueRS' => [716, 1, 'NU'],
        'beneficiarioRS' => [717, 11, 'NU'],
        'cinqueRN' => [728, 1, 'NU'],
        'beneficiarioRN' => [729, 11, 'NU'],
        'cinqueBC' => [740, 1, 'NU'],
        'beneficiarioBC' => [741, 11, 'NU'],
        'cinqueCO' => [752, 1, 'NU'],
        'cinqueSD' => [753, 1, 'NU'],
        'beneficiarioSD' => [754, 11, 'NU'],
        'cinqueAP' => [765, 1, 'NU'],
        'beneficiarioAP' => [766, 11, 'NU'],
        'cinque' => [778, 1, 'NU'],
        'codicePartito' => [780, 3, 'AN'],
        'firmaPartito' => [783, 1, 'NU'],
        'due' => [784, 1, 'NU'],
        'firmaDichiarante' => [1038, 1, 'NU'],
        // omessa presentazione del modello ISA con i quadri RE (campo 179) e RF o RG (campo 180)
        'assenzaIsaQuadroH' => [1428, 1, 'NU'],
        'assenzaIsaQuadroF' => [1429, 1, 'NU'],
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

    /**
     * Campi numerici del record B (formati NU, CB, DT, PI e CN delle
     * specifiche, posizione => lunghezza): quelli non compilati valgono
     * zero, non spazi (paragrafo 2.4, campi posizionali).
     */
    public const NUMERICI_B = [
        18 => 8, 90 => 1, 91 => 1, 93 => 1, 94 => 1, 95 => 1, 96 => 1, 97 => 1, 98 => 1, 99 => 1, 103 => 1, 104 => 1,
        105 => 1, 106 => 1, 211 => 11, 338 => 8, 347 => 1, 390 => 5, 499 => 8, 507 => 1, 508 => 1, 615 => 8, 623 => 8, 631 => 1,
        632 => 1, 633 => 1, 634 => 1, 635 => 1, 636 => 1, 687 => 1, 688 => 1, 689 => 1, 690 => 1, 691 => 1, 692 => 1, 693 => 1,
        694 => 1, 695 => 1, 696 => 1, 697 => 1, 698 => 1, 699 => 1, 700 => 1, 701 => 1, 702 => 1, 703 => 1, 704 => 1, 705 => 11,
        716 => 1, 717 => 11, 728 => 1, 729 => 11, 740 => 1, 741 => 11, 752 => 1, 753 => 1, 754 => 11, 765 => 1, 766 => 11, 777 => 1,
        778 => 1, 779 => 1, 783 => 1, 784 => 1, 785 => 1, 786 => 1, 787 => 8, 987 => 1, 988 => 1, 989 => 1, 990 => 1, 991 => 1, 992 => 1, 993 => 1, 994 => 1,
        997 => 1, 998 => 1, 999 => 1, 1001 => 1, 1002 => 1, 1003 => 1, 1004 => 1, 1005 => 1, 1006 => 1, 1007 => 1, 1008 => 1, 1011 => 1,
        1012 => 1, 1013 => 1, 1014 => 1, 1015 => 1, 1016 => 1, 1021 => 1, 1023 => 1, 1024 => 1, 1025 => 1, 1036 => 1, 1037 => 1, 1038 => 1,
        1095 => 3, 1181 => 1, 1182 => 1, 1183 => 1, 1184 => 3, 1187 => 2, 1209 => 2, 1213 => 1, 1428 => 1, 1429 => 1, 1446 => 2, 1448 => 8,
        1456 => 11, 1512 => 8, 1604 => 5, 1656 => 8, 1664 => 1, 1665 => 8, 1673 => 1, 1690 => 1, 1691 => 1, 1692 => 1, 1693 => 8, 1701 => 1,
        1719 => 11, 1746 => 1, 1779 => 1, 1864 => 7, 1871 => 3, 1874 => 5, 1880 => 1, 1881 => 1, 1882 => 1, 1883 => 1, 1884 => 1,
    ];

    /** Campi con il codice attività ATECO, da scrivere senza i punti */
    public const CODICI_ATTIVITA = ['LM001001', 'LM021004', 'LM022001', 'LM023001', 'LM024001', 'LM025001', 'LM026001', 'LM027001',
        'RD001001', 'RE001001', 'RF001001', 'RG001001', 'RS401014'];

    /** Caselle dei quadri compilati del record B (posizione) */
    public const CASELLE_QUADRI = [
        'RA' => 987, 'RB' => 988, 'RC' => 989, 'RD' => 990, 'RE' => 991, 'RF' => 992, 'RG' => 993, 'RH' => 994, 'RL' => 997, 'RM' => 998,
        'RN' => 999, 'RP' => 1001, 'RQ' => 1002, 'RR' => 1003, 'RS' => 1004, 'RT' => 1005, 'RU' => 1006, 'RV' => 1007, 'RX' => 1008,
        'FA' => 1011, 'CR' => 1012, 'CE' => 1013, 'LM' => 1014, 'TR' => 1015, 'FC' => 1016, 'DI' => 1021, 'LC' => 1023, 'NR' => 1024, 'CP' => 1025,
    ];

    /** Caselle dei quadri in testa al record B */
    public const CASELLE_TESTA = ['RW' => 'moduloRw', 'VO' => 'quadroVo', 'AC' => 'quadroAc'];
}
