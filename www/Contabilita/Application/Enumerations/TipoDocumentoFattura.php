<?php

namespace Contabilita\Application\Enumerations;

/**
 * Tipo di documento della fattura elettronica (campo TipoDocumento delle
 * specifiche tecniche, versione 1.9).
 */
enum TipoDocumentoFattura: string
{

    case fattura = 'TD01';
    case accontoFattura = 'TD02';
    case accontoParcella = 'TD03';
    case notaCredito = 'TD04';
    case notaDebito = 'TD05';
    case parcella = 'TD06';
    case integrazioneInversioneInterna = 'TD16';
    case integrazioneServiziEstero = 'TD17';
    case integrazioneBeniIntracomunitari = 'TD18';
    case integrazioneBeniArt17 = 'TD19';
    case autofatturaRegolarizzazione = 'TD20';
    case autofatturaSplafonamento = 'TD21';
    case estrazioneDepositoIva = 'TD22';
    case estrazioneDepositoIvaVersamento = 'TD23';
    case fatturaDifferita = 'TD24';
    case fatturaDifferitaTriangolare = 'TD25';
    case cessioneBeniAmmortizzabili = 'TD26';
    case autoconsumo = 'TD27';
    case acquistiSanMarino = 'TD28';
    case comunicazioneOmessaFatturazione = 'TD29';

    public function getLabel(): string
    {
        return match ($this) {
            self::fattura => 'Fattura',
            self::accontoFattura => 'Acconto/anticipo su fattura',
            self::accontoParcella => 'Acconto/anticipo su parcella',
            self::notaCredito => 'Nota di credito',
            self::notaDebito => 'Nota di debito',
            self::parcella => 'Parcella',
            self::integrazioneInversioneInterna => 'Integrazione fattura reverse charge interno',
            self::integrazioneServiziEstero => 'Integrazione/autofattura per acquisto servizi dall\'estero',
            self::integrazioneBeniIntracomunitari => 'Integrazione per acquisto di beni intracomunitari',
            self::integrazioneBeniArt17 => 'Integrazione/autofattura per acquisto di beni ex art. 17, c. 2, D.P.R. 633/1972',
            self::autofatturaRegolarizzazione => 'Autofattura per regolarizzazione e integrazione delle fatture',
            self::autofatturaSplafonamento => 'Autofattura per splafonamento',
            self::estrazioneDepositoIva => 'Estrazione beni da deposito IVA',
            self::estrazioneDepositoIvaVersamento => 'Estrazione beni da deposito IVA con versamento dell\'IVA',
            self::fatturaDifferita => 'Fattura differita (art. 21, c. 4, lett. a)',
            self::fatturaDifferitaTriangolare => 'Fattura differita (art. 21, c. 4, terzo periodo, lett. b)',
            self::cessioneBeniAmmortizzabili => 'Cessione di beni ammortizzabili e passaggi interni',
            self::autoconsumo => 'Fattura per autoconsumo o cessioni gratuite senza rivalsa',
            self::acquistiSanMarino => 'Acquisti da San Marino con IVA (fattura cartacea)',
            self::comunicazioneOmessaFatturazione => 'Comunicazione per omessa o irregolare fatturazione',
        };
    }

    /**
     * Nota di variazione in diminuzione.
     */
    public function isNotaCredito(): bool
    {
        return $this === self::notaCredito;
    }

    /**
     * Integrazione dell'imposta da parte del cessionario (acquisti in
     * inversione contabile, intracomunitari, dall'estero): il documento
     * è emesso dall'azienda come cessionario e si annota negli acquisti
     * con l'imposta a debito e a credito.
     */
    public function isIntegrazione(): bool
    {
        return in_array($this, [self::integrazioneInversioneInterna, self::integrazioneServiziEstero, self::integrazioneBeniIntracomunitari,
            self::integrazioneBeniArt17, self::autofatturaRegolarizzazione], true);
    }

    /**
     * Causale della registrazione proposta per la direzione del documento.
     */
    public function getCausale(DirezioneFattura $direzione): CausaleContabile
    {
        if ($direzione === DirezioneFattura::emessa) {
            return $this->isNotaCredito() ? CausaleContabile::notaCreditoEmessa : CausaleContabile::fatturaVendita;
        }
        return $this->isNotaCredito() ? CausaleContabile::notaCreditoRicevuta : CausaleContabile::fatturaAcquisto;
    }
}
