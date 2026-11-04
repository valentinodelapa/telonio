<?php

namespace Dichiarativi\Application\Services\Telematico;

/**
 * Campi posizionali della fornitura della dichiarazione IVA (specifiche
 * tecniche del modello IVA 2026, allegato A al provvedimento n. 50925
 * dell'11 febbraio 2026): posizione (da 1), lunghezza e tipo dei campi
 * usati dei record A, B e Z. Record di 1.900 caratteri; formati AN
 * (a sinistra con spazi; anche CF e PN) e NU (a destra con zeri; anche
 * CB 0/1, DT GGMMAAAA, PI e CN).
 *
 * @see https://www.agenziaentrate.gov.it/portale/it/iva_2026specifichetecniche
 */
class TracciatoIva
{

    public const RECORD_A = [
        'tipoRecord' => [1, 1, 'AN'],
        'codiceFornitura' => [16, 5, 'AN'],
        'tipoFornitore' => [21, 2, 'NU'],
        'codiceFiscaleFornitore' => [23, 16, 'AN'],
        'progressivoInvio' => [522, 4, 'NU'],
        'totaleInvii' => [526, 4, 'NU'],
    ];

    public const RECORD_B = [
        'tipoRecord' => [1, 1, 'AN'],
        'codiceFiscale' => [2, 16, 'AN'],
        'progressivoModulo' => [18, 8, 'NU'],
        'identificativoDichiarazione' => [54, 20, 'AN'],
        'flagConferma' => [90, 1, 'NU'],
        'correttiva' => [91, 1, 'NU'],
        'integrativa' => [93, 1, 'NU'],
        'cognome' => [94, 24, 'AN'],
        'nome' => [118, 20, 'AN'],
        'denominazione' => [138, 60, 'AN'],
        'partitaIva' => [198, 11, 'NU'],
        'impresaArtigiana' => [209, 1, 'NU'],
        'amministrazioneStraordinaria' => [210, 1, 'NU'],
        'email' => [211, 100, 'AN'],
        'telefono' => [311, 12, 'AN'],
        'comuneNascita' => [335, 40, 'AN'],
        'provinciaNascita' => [375, 2, 'AN'],
        'dataNascita' => [377, 8, 'NU'],
        'sesso' => [385, 1, 'AN'],
        'naturaGiuridica' => [386, 2, 'NU'],
        'numeroModuli' => [439, 8, 'NU'],
        'firmaDichiarante' => [447, 1, 'NU'],
        'esoneroVisto' => [448, 1, 'NU'],
        // sottoscrizione dell'organo di controllo: soggetto (NU) e firma (CB) dei cinque componenti
        'organoControllo1' => [450, 1, 'NU'],
        'codiceFiscaleOrganoControllo1' => [451, 16, 'AN'],
        'firmaOrganoControllo1' => [467, 1, 'NU'],
        'organoControllo2' => [468, 1, 'NU'],
        'codiceFiscaleOrganoControllo2' => [469, 16, 'AN'],
        'firmaOrganoControllo2' => [485, 1, 'NU'],
        'organoControllo3' => [486, 1, 'NU'],
        'codiceFiscaleOrganoControllo3' => [487, 16, 'AN'],
        'firmaOrganoControllo3' => [503, 1, 'NU'],
        'organoControllo4' => [504, 1, 'NU'],
        'codiceFiscaleOrganoControllo4' => [505, 16, 'AN'],
        'firmaOrganoControllo4' => [521, 1, 'NU'],
        'organoControllo5' => [522, 1, 'NU'],
        'codiceFiscaleOrganoControllo5' => [523, 16, 'AN'],
        'firmaOrganoControllo5' => [539, 1, 'NU'],
        'ivaBase' => [540, 1, 'NU'],
        'situazioniParticolari' => [1210, 2, 'NU'],
        'codiceFiscaleSottoscrittore' => [1431, 16, 'AN'],
        'codiceCaricaSottoscrittore' => [1447, 2, 'NU'],
        'dataCaricaSottoscrittore' => [1449, 8, 'NU'],
        'codiceFiscaleSocietaDichiarante' => [1457, 11, 'NU'],
        'cognomeSottoscrittore' => [1468, 24, 'AN'],
        'nomeSottoscrittore' => [1492, 20, 'AN'],
        'sessoSottoscrittore' => [1512, 1, 'AN'],
        'dataNascitaSottoscrittore' => [1513, 8, 'NU'],
        'comuneNascitaSottoscrittore' => [1521, 40, 'AN'],
        'provinciaNascitaSottoscrittore' => [1561, 2, 'AN'],
        'dataInizioProcedura' => [1661, 8, 'NU'],
        'proceduraNonTerminata' => [1669, 1, 'NU'],
        'dataFineProcedura' => [1670, 8, 'NU'],
        'art74Bis' => [1678, 1, 'NU'],
        'invioAvvisoIntermediario' => [1719, 1, 'NU'],
        'ricezioneAvviso' => [1720, 1, 'NU'],
        'codiceFiscaleIncaricato' => [1724, 16, 'AN'],
        'impegno' => [1745, 1, 'NU'],
        'dataImpegno' => [1746, 8, 'NU'],
        'firmaIncaricato' => [1754, 1, 'NU'],
        'codiceFiscaleResponsabileCaf' => [1755, 16, 'AN'],
        'codiceFiscaleCaf' => [1771, 11, 'NU'],
        'codiceFiscaleProfessionista' => [1782, 16, 'AN'],
        'firmaVisto' => [1798, 1, 'NU'],
        'invioComunicazioniIntermediario' => [1799, 1, 'NU'],
        'ricezioneComunicazioni' => [1800, 1, 'NU'],
    ];

    public const RECORD_Z = [
        'tipoRecord' => [1, 1, 'AN'],
        'numeroRecordB' => [16, 9, 'NU'],
        'numeroRecordD' => [25, 9, 'NU'],
    ];
}
