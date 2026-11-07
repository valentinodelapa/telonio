<?php

namespace Dichiarativi\Application\Services\Telematico;

/**
 * Campi posizionali della fornitura del modello IVA TR (specifiche
 * tecniche IVT25, allegato B al provvedimento del 26 marzo 2020,
 * aggiornate al 13 marzo 2026): posizione (da 1), lunghezza e tipo dei
 * campi usati dei record A, B e Z. Record di 1.900 caratteri.
 *
 * @see https://www.agenziaentrate.gov.it/portale/schede/rimborsi/ivacredititrimestrali-ivatr/spectecn-iva-tr
 */
class TracciatoTr
{

    public const RECORD_A = [
        'tipoRecord' => [1, 1, 'AN'],
        'codiceFornitura' => [16, 5, 'AN'],
        'tipoFornitore' => [21, 2, 'NU'],
        'codiceFiscaleFornitore' => [23, 16, 'AN'],
    ];
    public const RECORD_B = [
        'tipoRecord' => [1, 1, 'AN'],
        'codiceFiscale' => [2, 16, 'AN'],
        'progressivoModulo' => [18, 8, 'NU'],
        'identificativoProduttore' => [74, 16, 'AN'],
        'flagConferma' => [90, 1, 'NU'],
        'correttiva' => [91, 1, 'NU'],
        'modificaIstanza' => [95, 1, 'NU'],
        'cognome' => [110, 24, 'AN'],
        'nome' => [134, 20, 'AN'],
        'denominazione' => [154, 60, 'AN'],
        'partitaIva' => [214, 11, 'NU'],
        'regimiParticolari' => [225, 1, 'NU'],
        'contabilitaSeparate' => [226, 1, 'NU'],
        'controllante' => [227, 1, 'NU'],
        'codiceAttivita' => [228, 6, 'AN'],
        'telefono' => [234, 12, 'AN'],
        'email' => [258, 100, 'AN'],
        'anno' => [358, 4, 'NU'],
        'trimestre' => [362, 2, 'NU'],
        'comuneNascita' => [364, 40, 'AN'],
        'provinciaNascita' => [404, 2, 'AN'],
        'dataNascita' => [406, 8, 'NU'],
        'sesso' => [414, 1, 'AN'],
        'naturaGiuridica' => [922, 2, 'NU'],
        'codiceFiscaleSottoscrittore' => [1062, 16, 'AN'],
        // formato CN: zeri se assente, spazi non ammessi
        'codiceFiscaleSocietaDichiarante' => [1078, 11, 'NU'],
        'codiceCaricaSottoscrittore' => [1090, 2, 'NU'],
        'cognomeSottoscrittore' => [1161, 24, 'AN'],
        'nomeSottoscrittore' => [1185, 20, 'AN'],
        'sessoSottoscrittore' => [1205, 1, 'AN'],
        'dataNascitaSottoscrittore' => [1206, 8, 'NU'],
        'comuneNascitaSottoscrittore' => [1214, 40, 'AN'],
        'provinciaNascitaSottoscrittore' => [1254, 2, 'AN'],
        'firmaDichiarante' => [1368, 1, 'NU'],
        'numeroModuli' => [1474, 8, 'NU'],
        'codiceFiscaleIncaricato' => [1482, 16, 'AN'],
        'impegno' => [1503, 1, 'NU'],
        'dataImpegno' => [1505, 8, 'NU'],
        'firmaIncaricato' => [1513, 1, 'NU'],
    ];
    public const RECORD_Z = [
        'tipoRecord' => [1, 1, 'AN'],
        'numeroRecordB' => [16, 9, 'NU'],
        'numeroRecordC' => [25, 9, 'NU'],
    ];
}
