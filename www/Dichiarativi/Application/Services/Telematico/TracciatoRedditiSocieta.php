<?php

namespace Dichiarativi\Application\Services\Telematico;

/**
 * Tracciato del record B (frontespizio) dei modelli Redditi Società di
 * persone ("RSP26") e Società di capitali ("RSC26") 2026 e del modello
 * Consolidato nazionale e mondiale ("CNM26"), dalle specifiche
 * tecniche (SP aggiornate al 13 maggio 2026, SC al 15 settembre 2026). I
 * record A e Z e i record a struttura variabile C sono quelli di Redditi PF
 * (TracciatoRedditi, RecordQuadri).
 *
 * I campi del record B differiscono tra i due modelli in alcune posizioni
 * (impegno alla trasmissione, organo di controllo e caselle proprie di SC);
 * i campi numerici (formati NU, CB, DT, DN, PI, CN, N1-N3) vanno a zero se
 * non compilati.
 */
class TracciatoRedditiSocieta
{

    /** Campi comuni del record B: nome => [posizione, lunghezza, formato] */
    public const RECORD_B = [
        'tipoRecord' => [1, 1, 'AN'],
        'codiceFiscale' => [2, 16, 'AN'],
        'progressivoModulo' => [18, 8, 'NU'],
        'identificativoProduttore' => [74, 16, 'AN'],
        'flagConferma' => [90, 1, 'NU'],
        'flagConfermaIsa' => [91, 1, 'NU'],
        'comunicazioneCpb' => [93, 1, 'NU'],
        'quadroVo' => [95, 1, 'NU'],
        'quadroAc' => [96, 1, 'NU'],
        'isa' => [97, 1, 'NU'],
        'correttiva' => [103, 1, 'NU'],
        'integrativa' => [104, 1, 'NU'],
        'eventiEccezionali' => [107, 2, 'NU'],
        'denominazione' => [151, 60, 'AN'],
        'partitaIva' => [211, 11, 'NU'],
        'telefono' => [222, 12, 'AN'],
        'email' => [246, 50, 'AN'],
        'dataInizioPeriodo' => [709, 8, 'NU'],
        'dataFinePeriodo' => [717, 8, 'NU'],
        'stato' => [725, 1, 'NU'],
        'naturaGiuridica' => [726, 2, 'NU'],
        'situazione' => [728, 1, 'NU'],
        'canoneRai' => [741, 1, 'NU'],
        'firmaDichiarante' => [1040, 1, 'NU'],
        'assenzaIsaQuadroF' => [1429, 1, 'NU'],
        'codiceFiscaleRappresentante' => [1430, 16, 'AN'],
        'codiceCaricaRappresentante' => [1446, 2, 'NU'],
        'codiceFiscaleSocietaRappresentante' => [1456, 11, 'NU'],
        'cognomeRappresentante' => [1467, 24, 'AN'],
        'nomeRappresentante' => [1491, 20, 'AN'],
        'sessoRappresentante' => [1511, 1, 'AN'],
        'dataNascitaRappresentante' => [1512, 8, 'NU'],
        'comuneNascitaRappresentante' => [1520, 40, 'AN'],
        'provinciaNascitaRappresentante' => [1560, 2, 'AN'],
        'dataImpegno' => [1693, 8, 'NU'],
        'firmaIncaricato' => [1701, 1, 'NU'],
        'codiceFiscaleResponsabileCaf' => [1703, 16, 'AN'],
        'codiceFiscaleCaf' => [1719, 11, 'NU'],
        'codiceFiscaleProfessionista' => [1730, 16, 'AN'],
        'firmaVisto' => [1746, 1, 'NU'],
    ];

    /** Campi propri di Redditi SP (campi 12, 179, 200 e 201) */
    public const RECORD_B_SP = [
        'quadroRw' => [94, 1, 'NU'],
        'assenzaIsaQuadroH' => [1428, 1, 'NU'],
        'codiceFiscaleIncaricato' => [1674, 16, 'AN'],
        'impegno' => [1690, 1, 'NU'],
    ];

    /** Campi propri di Redditi SC (trust, IRES premiale, consolidato, trasparenza, organo di controllo, B201 e B202) */
    public const RECORD_B_SC = [
        'trust' => [94, 1, 'NU'],
        'iresPremiale' => [98, 1, 'NU'],
        'consolidato' => [100, 1, 'NU'],
        'trasparenza' => [101, 1, 'NU'],
        'addizionaliIres' => [102, 1, 'NU'],
        'dataApprovazioneBilancio' => [693, 8, 'NU'],
        'codiceFiscaleIncaricato' => [1675, 16, 'AN'],
        'impegno' => [1691, 1, 'NU'],
    ];

    /** Organo di controllo di Redditi SC (campi B86-B105): cinque gruppi di soggetto, codice fiscale, firme */
    public const ORGANO_CONTROLLO_SC = [890, 909, 928, 947, 966];

    /** Caselle dei quadri compilati */
    public const CASELLE_QUADRI_SP = ['RA' => 987, 'RB' => 988, 'RD' => 990, 'RE' => 991, 'RF' => 992, 'RG' => 993, 'RH' => 994, 'RL' => 997, 'RM' => 998, 'RN' => 999, 'RO' => 1000, 'RP' => 1001, 'RQ' => 1002, 'RS' => 1004, 'RT' => 1005, 'RU' => 1006, 'RV' => 1007, 'RX' => 1008, 'RJ' => 1009, 'TR' => 1015, 'FC' => 1016, 'OP' => 1020, 'DI' => 1021, 'CP' => 1022];
    public const CASELLE_QUADRI_SC = ['RA' => 987, 'RB' => 988, 'RF' => 992, 'RH' => 994, 'RI' => 995, 'RK' => 996, 'RL' => 997, 'RM' => 998, 'RN' => 999, 'RO' => 1000, 'RQ' => 1002, 'RS' => 1004, 'RT' => 1005, 'RU' => 1006, 'RV' => 1007, 'RX' => 1008, 'RJ' => 1009, 'RZ' => 1010, 'CE' => 1013, 'TR' => 1015, 'FC' => 1016, 'TN' => 1017, 'GN' => 1018, 'GC' => 1019, 'OP' => 1020, 'DI' => 1021, 'NI' => 1022, 'CP' => 1023, 'PN' => 1035];

    /**
     * Record B del modello Consolidato nazionale e mondiale ("CNM26",
     * specifiche aggiornate al 25 giugno 2026): frontespizio della
     * consolidante, con posizioni proprie per firma e impegno e senza
     * partita IVA; organo di controllo come Redditi SC.
     */
    public const RECORD_B_CNM = [
        'tipoRecord' => [1, 1, 'AN'],
        'codiceFiscale' => [2, 16, 'AN'],
        'progressivoModulo' => [18, 8, 'NU'],
        'identificativoProduttore' => [74, 16, 'AN'],
        'flagConferma' => [90, 1, 'NU'],
        'consolidatoNazionale' => [101, 1, 'NU'],
        'consolidatoMondiale' => [102, 1, 'NU'],
        'correttiva' => [103, 1, 'NU'],
        'integrativa' => [104, 1, 'NU'],
        'denominazione' => [151, 60, 'AN'],
        'telefono' => [222, 12, 'AN'],
        'email' => [246, 50, 'AN'],
        'dataInizioPeriodo' => [709, 8, 'NU'],
        'dataFinePeriodo' => [717, 8, 'NU'],
        'naturaGiuridica' => [726, 2, 'NU'],
        'situazione' => [728, 1, 'NU'],
        'firmaDichiarante' => [1038, 1, 'NU'],
        'codiceFiscaleRappresentante' => [1430, 16, 'AN'],
        'codiceCaricaRappresentante' => [1446, 2, 'NU'],
        'codiceFiscaleSocietaRappresentante' => [1456, 11, 'NU'],
        'cognomeRappresentante' => [1467, 24, 'AN'],
        'nomeRappresentante' => [1491, 20, 'AN'],
        'sessoRappresentante' => [1511, 1, 'AN'],
        'dataNascitaRappresentante' => [1512, 8, 'NU'],
        'comuneNascitaRappresentante' => [1520, 40, 'AN'],
        'provinciaNascitaRappresentante' => [1560, 2, 'AN'],
        'codiceFiscaleIncaricato' => [1675, 16, 'AN'],
        'impegno' => [1691, 1, 'NU'],
        'dataImpegno' => [1694, 8, 'NU'],
        'firmaIncaricato' => [1702, 1, 'NU'],
        'codiceFiscaleResponsabileCaf' => [1703, 16, 'AN'],
        'codiceFiscaleCaf' => [1719, 11, 'NU'],
        'codiceFiscaleProfessionista' => [1730, 16, 'AN'],
        'firmaVisto' => [1746, 1, 'NU'],
    ];
    public const CASELLE_QUADRI_CNM = ['NF' => 1020, 'NX' => 1021, 'NI' => 1022, 'NR' => 1023, 'NE' => 1024, 'NC' => 1025, 'MF' => 1026, 'MX' => 1027, 'MR' => 1028, 'ME' => 1029, 'MC' => 1030, 'CC' => 1031, 'CN' => 1032, 'CK' => 1033, 'CS' => 1034];
    public const NUMERICI_B_CNM = [
        2 => 16, 18 => 8, 90 => 1, 101 => 1, 102 => 1, 103 => 1, 104 => 1, 105 => 1, 106 => 1, 693 => 8, 701 => 8, 709 => 8,
        717 => 8, 726 => 2, 728 => 1, 729 => 11, 890 => 1, 907 => 1, 908 => 1, 909 => 1, 926 => 1, 927 => 1, 928 => 1, 945 => 1,
        946 => 1, 947 => 1, 964 => 1, 965 => 1, 966 => 1, 983 => 1, 984 => 1, 1020 => 1, 1021 => 1, 1022 => 1, 1023 => 1,
        1024 => 1, 1025 => 1, 1026 => 1, 1027 => 1, 1028 => 1, 1029 => 1, 1030 => 1, 1031 => 1, 1032 => 1, 1033 => 1, 1034 => 1,
        1036 => 1, 1037 => 1, 1038 => 1, 1209 => 2, 1446 => 2, 1456 => 11, 1512 => 8, 1691 => 1, 1692 => 1, 1693 => 1, 1694 => 8,
        1702 => 1, 1719 => 11, 1746 => 1
    ];

    /** Campi numerici del record B (posizione => lunghezza), a zero se non compilati */
    public const NUMERICI_B_SP = [
        18 => 8, 90 => 1, 91 => 1, 93 => 1, 94 => 1, 95 => 1, 96 => 1, 97 => 1, 103 => 1, 104 => 1, 105 => 1, 106 => 1, 107 => 2,
        211 => 11, 693 => 8, 709 => 8, 717 => 8, 725 => 1, 726 => 2, 728 => 1, 729 => 11, 740 => 1, 741 => 1, 745 => 1, 746 => 1,
        747 => 8, 755 => 1, 987 => 1, 988 => 1, 990 => 1, 991 => 1, 992 => 1, 993 => 1, 994 => 1, 997 => 1, 998 => 1, 999 => 1,
        1000 => 1, 1001 => 1, 1002 => 1, 1004 => 1, 1005 => 1, 1006 => 1, 1007 => 1, 1008 => 1, 1009 => 1, 1015 => 1, 1016 => 1,
        1020 => 1, 1021 => 1, 1022 => 1, 1038 => 1, 1039 => 1, 1040 => 1, 1063 => 1, 1209 => 2, 1213 => 1, 1428 => 1, 1429 => 1,
        1446 => 2, 1448 => 8, 1456 => 11, 1512 => 8, 1690 => 1, 1691 => 1, 1692 => 1, 1693 => 8, 1701 => 1, 1719 => 11,
        1746 => 1, 1779 => 1
    ];
    public const NUMERICI_B_SC = [
        18 => 8, 90 => 1, 91 => 1, 93 => 1, 94 => 1, 95 => 1, 96 => 1, 97 => 1, 98 => 1, 100 => 1, 101 => 1, 102 => 1, 103 => 1,
        104 => 1, 105 => 1, 106 => 1, 107 => 2, 211 => 11, 693 => 8, 701 => 8, 709 => 8, 717 => 8, 725 => 1, 726 => 2, 728 => 1,
        729 => 11, 740 => 1, 741 => 1, 742 => 1, 743 => 2, 745 => 1, 773 => 8, 781 => 1, 890 => 1, 907 => 1, 908 => 1, 909 => 1,
        926 => 1, 927 => 1, 928 => 1, 945 => 1, 946 => 1, 947 => 1, 964 => 1, 965 => 1, 966 => 1, 983 => 1, 984 => 1, 987 => 1,
        988 => 1, 992 => 1, 994 => 1, 995 => 1, 996 => 1, 997 => 1, 998 => 1, 999 => 1, 1000 => 1, 1002 => 1, 1004 => 1,
        1005 => 1, 1006 => 1, 1007 => 1, 1008 => 1, 1009 => 1, 1010 => 1, 1013 => 1, 1015 => 1, 1016 => 1, 1017 => 1, 1018 => 1,
        1019 => 1, 1020 => 1, 1021 => 1, 1022 => 1, 1023 => 1, 1035 => 1, 1039 => 1, 1040 => 1, 1063 => 1, 1209 => 2, 1211 => 1,
        1212 => 1, 1213 => 1, 1428 => 1, 1429 => 1, 1446 => 2, 1448 => 8, 1456 => 11, 1512 => 8, 1691 => 1, 1692 => 1, 1693 => 8,
        1701 => 1, 1719 => 11, 1746 => 1, 1779 => 1
    ];
}
