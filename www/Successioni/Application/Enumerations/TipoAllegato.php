<?php

namespace Successioni\Application\Enumerations;

/**
 * Documenti allegati alla dichiarazione (quadro EG), nell'ordine del quadro
 * e del file telematico. Formati ammessi: PDF/A-1a o PDF/A-1b, TIFF in
 * bianco e nero con compressione CCITT Group IV fino a 300 DPI; massimo 5
 * MB per file (specifiche SUC13, paragrafo 2.3).
 */
enum TipoAllegato: string
{

    case dichiarazioniSostitutive = 'DS';
    case testamento = 'TE';
    case inventario = 'IN';
    case certificazioneImpostaEstero = 'CE';
    case documentiPassivita = 'DP';
    case alberoGenealogico = 'AG';
    case documentiIdentita = 'DI';
    case altro = 'AL';
    case primaCasa = 'PC';
    case agevolazioniIpocatastali = 'AI';
    case riduzioniSuccessione = 'RS';

    public function getLabel(): string
    {
        return match ($this) {
            self::dichiarazioniSostitutive => 'Dichiarazioni sostitutive (curatore, amministratore, esecutore)',
            self::testamento => 'Copia del testamento',
            self::inventario => 'Inventario',
            self::certificazioneImpostaEstero => 'Certificazione dell\'imposta versata all\'estero',
            self::documentiPassivita => 'Documenti delle passività',
            self::alberoGenealogico => 'Prospetto dell\'albero genealogico',
            self::documentiIdentita => 'Documenti d\'identità',
            self::altro => 'Altri documenti',
            self::primaCasa => 'Dichiarazioni per l\'agevolazione prima casa',
            self::agevolazioniIpocatastali => 'Documenti per le agevolazioni sulle imposte ipotecaria e catastale',
            self::riduzioniSuccessione => 'Documenti per le riduzioni dell\'imposta di successione',
        };
    }

    /**
     * Nome dell'elemento del quadro EG nel file telematico.
     */
    public function getElemento(): string
    {
        return match ($this) {
            self::dichiarazioniSostitutive => 'DichSost',
            self::testamento => 'Testamento',
            self::inventario => 'Inventario',
            self::certificazioneImpostaEstero => 'CertificazioneImpostaVersataEstero',
            self::documentiPassivita => 'DocumentiPassivita',
            self::alberoGenealogico => 'AlberoGenealogico',
            self::documentiIdentita => 'DocumentiIdentita',
            self::altro => 'Altro',
            self::primaCasa => 'PrimaCasa',
            self::agevolazioniIpocatastali => 'AgevolazioniImposteIpocatastali',
            self::riduzioniSuccessione => 'RiduzioniImposteSuccessione',
        };
    }
}
