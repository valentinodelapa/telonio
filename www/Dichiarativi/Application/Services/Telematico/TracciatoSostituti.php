<?php

namespace Dichiarativi\Application\Services\Telematico;

/**
 * Campi posizionali delle forniture della Certificazione Unica ("CUR26",
 * specifiche tecniche CU 2026, aggiornate al 24 febbraio 2026) e del
 * modello 770 ("77026", specifiche tecniche 770/2026): posizione (da 1),
 * lunghezza e tipo dei campi usati. Record di 1.900 caratteri; i campi CB,
 * CN, DT e NU sono numerici (zeri se non valorizzati).
 *
 * @see https://www.agenziaentrate.gov.it/portale/web/guest/certificazione-unica-2026
 * @see https://www.agenziaentrate.gov.it/portale/web/guest/770-2026
 */
class TracciatoSostituti
{

    public const RECORD_A = [
        'tipoRecord' => [1, 1, 'AN'],
        'codiceFornitura' => [16, 5, 'AN'],
        'tipoFornitore' => [21, 2, 'NU'],
        'codiceFiscaleFornitore' => [23, 16, 'AN'],
    ];

    /** Modello 770: progressivo e numero degli invii (tipologia di invio 1: un solo invio) */
    public const RECORD_A_770 = self::RECORD_A + [
        'progressivoInvio' => [522, 4, 'NU'],
        'numeroInvii' => [526, 4, 'NU'],
    ];

    /** Frontespizio della comunicazione CU del sostituto */
    public const RECORD_B_CU = [
        'tipoRecord' => [1, 1, 'AN'],
        'codiceFiscale' => [2, 16, 'AN'],
        'progressivoModulo' => [18, 8, 'NU'],
        'identificativoProduttore' => [74, 16, 'AN'],
        'annullamento' => [91, 1, 'NU'],
        'sostituzione' => [92, 1, 'NU'],
        'cognome' => [93, 24, 'AN'],
        'nome' => [117, 20, 'AN'],
        'denominazione' => [137, 60, 'AN'],
        'email' => [197, 100, 'AN'],
        'telefono' => [297, 12, 'AN'],
        'codiceFiscaleRappresentante' => [311, 16, 'AN'],
        'codiceCaricaRappresentante' => [327, 2, 'NU'],
        'cognomeRappresentante' => [329, 24, 'AN'],
        'nomeRappresentante' => [353, 20, 'AN'],
        'codiceFiscaleSocieta' => [373, 11, 'NU'],
        'numeroCertificazioni' => [402, 8, 'NU'],
        'quadroCt' => [410, 1, 'NU'],
        'firma' => [411, 1, 'NU'],
        'codiceFiscaleIntermediario' => [412, 16, 'AN'],
        'impegno' => [428, 1, 'NU'],
        'dataImpegno' => [429, 8, 'NU'],
        'firmaIntermediario' => [437, 1, 'NU'],
        'casiParticolari' => [527, 1, 'NU'],
    ];

    /** Parte posizionale dei record D (dati del percipiente) e H (lavoro autonomo) */
    public const RECORD_CERTIFICAZIONE = [
        'tipoRecord' => [1, 1, 'AN'],
        'codiceFiscale' => [2, 16, 'AN'],
        'progressivoModulo' => [18, 8, 'NU'],
        'codiceFiscalePercipiente' => [26, 16, 'AN'],
        'progressivoCertificazione' => [42, 5, 'NU'],
        'protocolloInvio' => [47, 17, 'NU'],
        'protocolloProgressivo' => [64, 6, 'NU'],
        'spazioUtente' => [70, 14, 'AN'],
        'tipoOperazione' => [84, 1, 'AN'],
        'flagConferma' => [89, 1, 'NU'],
    ];

    public const RECORD_Z_CU = [
        'tipoRecord' => [1, 1, 'AN'],
        'numeroRecordB' => [16, 9, 'NU'],
        'numeroRecordC' => [25, 9, 'NU'],
        'numeroRecordD' => [34, 9, 'NU'],
        'numeroRecordG' => [43, 9, 'NU'],
        'numeroRecordH' => [52, 9, 'NU'],
        'numeroRecordL' => [61, 9, 'NU'],
    ];

    /** Frontespizio del modello 770 */
    public const RECORD_B_770 = [
        'tipoRecord' => [1, 1, 'AN'],
        'codiceFiscale' => [2, 16, 'AN'],
        'progressivoModulo' => [18, 8, 'NU'],
        'identificativoProduttore' => [74, 16, 'AN'],
        'flagConferma' => [90, 1, 'NU'],
        'protocolloInvio' => [91, 17, 'NU'],
        'protocolloProgressivo' => [108, 6, 'NU'],
        'correttiva' => [114, 1, 'NU'],
        'integrativa' => [115, 1, 'NU'],
        'cognome' => [116, 24, 'AN'],
        'nome' => [140, 20, 'AN'],
        'denominazione' => [160, 60, 'AN'],
        'comuneNascita' => [220, 40, 'AN'],
        'provinciaNascita' => [260, 2, 'AN'],
        'dataNascita' => [262, 8, 'NU'],
        'sesso' => [270, 1, 'AN'],
        'casiParticolari' => [278, 1, 'NU'],
        'codiceAttivita' => [279, 6, 'AN'],
        'email' => [285, 100, 'AN'],
        'telefono' => [385, 12, 'AN'],
        'codicePaeseEstero' => [476, 3, 'NU'],
        'stato' => [503, 1, 'NU'],
        'naturaGiuridica' => [504, 2, 'NU'],
        'situazione' => [506, 1, 'NU'],
        'codiceFiscaleDicastero' => [507, 11, 'NU'],
        'codiceFiscaleRappresentante' => [797, 16, 'AN'],
        'codiceCaricaRappresentante' => [813, 2, 'NU'],
        'dataCaricaRappresentante' => [815, 8, 'NU'],
        'cognomeRappresentante' => [894, 24, 'AN'],
        'nomeRappresentante' => [918, 20, 'AN'],
        'sessoRappresentante' => [938, 1, 'AN'],
        'dataNascitaRappresentante' => [939, 8, 'NU'],
        'comuneNascitaRappresentante' => [947, 40, 'AN'],
        'provinciaNascitaRappresentante' => [987, 2, 'AN'],
        'codiceStatoEsteroRappresentante' => [989, 3, 'NU'],
        'dataAperturaFallimento' => [1087, 8, 'NU'],
        'codiceFiscaleSocieta' => [1095, 11, 'NU'],
        'tipologiaInvio' => [1130, 1, 'NU'],
        'quadroSf' => [1131, 1, 'NU'],
        'quadroSg' => [1132, 1, 'NU'],
        'quadroSh' => [1133, 1, 'NU'],
        'quadroSi' => [1134, 1, 'NU'],
        'quadroSk' => [1135, 1, 'NU'],
        'quadroSl' => [1136, 1, 'NU'],
        'quadroSm' => [1137, 1, 'NU'],
        'quadroSo' => [1138, 1, 'NU'],
        'quadroSp' => [1139, 1, 'NU'],
        'quadroSq' => [1140, 1, 'NU'],
        'quadroSt' => [1141, 1, 'NU'],
        'quadroSv' => [1142, 1, 'NU'],
        'quadroSx' => [1143, 1, 'NU'],
        'quadroSy' => [1144, 1, 'NU'],
        'quadroSs' => [1145, 1, 'NU'],
        'quadroDi' => [1146, 1, 'NU'],
        'dipendente' => [1147, 1, 'NU'],
        'autonomo' => [1148, 1, 'NU'],
        'capitali' => [1149, 1, 'NU'],
        'locazioniBrevi' => [1150, 1, 'NU'],
        'altreRitenute' => [1151, 1, 'NU'],
        // gestione separata: due altri incaricati (caselle del sostituto e dei quadri) e casella dell'incaricato
        'separata1Sostituto' => [1168, 1, 'NU'],
        'separata1Dipendente' => [1169, 1, 'NU'],
        'separata1Autonomo' => [1170, 1, 'NU'],
        'separata1Capitali' => [1171, 1, 'NU'],
        'separata1LocazioniBrevi' => [1172, 1, 'NU'],
        'separata1AltreRitenute' => [1173, 1, 'NU'],
        'separata2Sostituto' => [1190, 1, 'NU'],
        'separata2Dipendente' => [1191, 1, 'NU'],
        'separata2Autonomo' => [1192, 1, 'NU'],
        'separata2Capitali' => [1193, 1, 'NU'],
        'separata2LocazioniBrevi' => [1194, 1, 'NU'],
        'separata2AltreRitenute' => [1195, 1, 'NU'],
        'incaricatoSeparata' => [1196, 1, 'NU'],
        'nonTrasmissione' => [1197, 1, 'NU'],
        'firmaDichiarante' => [1198, 1, 'NU'],
        // organo di controllo: cinque firmatari (soggetto e firma)
        'soggettoControllo1' => [1215, 1, 'NU'],
        'firmaControllo1' => [1216, 1, 'NU'],
        'soggettoControllo2' => [1233, 1, 'NU'],
        'firmaControllo2' => [1234, 1, 'NU'],
        'soggettoControllo3' => [1251, 1, 'NU'],
        'firmaControllo3' => [1252, 1, 'NU'],
        'soggettoControllo4' => [1269, 1, 'NU'],
        'firmaControllo4' => [1270, 1, 'NU'],
        'soggettoControllo5' => [1287, 1, 'NU'],
        'firmaControllo5' => [1288, 1, 'NU'],
        'attestazione' => [1289, 1, 'NU'],
        'situazioniParticolari' => [1497, 2, 'NU'],
        'avvisoIntermediario' => [1713, 1, 'NU'],
        'ricezioneAvviso' => [1714, 1, 'NU'],
        'codiceFiscaleIncaricato' => [1715, 16, 'AN'],
        'impegno' => [1736, 1, 'NU'],
        'dataImpegno' => [1737, 8, 'NU'],
        'firmaIncaricato' => [1745, 1, 'NU'],
        'codiceFiscaleCaf' => [1762, 11, 'NU'],
        'firmaVisto' => [1789, 1, 'NU'],
    ];

    public const RECORD_Z_770 = [
        'tipoRecord' => [1, 1, 'AN'],
        'numeroRecordB' => [16, 9, 'NU'],
        'numeroRecordD' => [25, 9, 'NU'],
        'numeroRecordE' => [34, 9, 'NU'],
        'numeroRecordI' => [43, 9, 'NU'],
        'numeroRecordJ' => [52, 9, 'NU'],
    ];
}
