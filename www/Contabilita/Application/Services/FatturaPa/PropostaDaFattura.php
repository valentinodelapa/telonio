<?php

namespace Contabilita\Application\Services\FatturaPa;

use Contabilita\Application\Entities\CodiceIva;
use Contabilita\Application\Entities\EsercizioContabile;
use Contabilita\Application\Entities\FatturaElettronica;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Enumerations\DirezioneFattura;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Enumerations\QualificaOperazione;
use Contabilita\Application\Enumerations\TipoDocumentoFattura;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\CodiceIvaModel;
use Contabilita\Application\Models\EsercizioContabileModel;
use Contabilita\Application\Models\RegistroIvaModel;
use Contabilita\Application\Services\PropostaRighe;
use Contabilita\Application\Services\RegimeIva;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Proposta della registrazione di una fattura elettronica importata, da
 * confermare nel form della prima nota: causale dal tipo di documento e
 * dalla direzione, documento, controparte, registro principale, data di
 * competenza (emesse: data del documento; ricevute: data di
 * registrazione), esigibilità e righe IVA dai riepiloghi.
 *
 * Il codice IVA si sceglie per aliquota e natura; per gli acquisti in
 * inversione contabile (fatture ricevute con natura N6, integrazioni TD16,
 * TD17, TD18 e TD19) si propone il codice di integrazione: per le fatture
 * N6 del fornitore l'aliquota non è indicata e si propone il 22%, da
 * verificare. Per le aziende in ventilazione dei corrispettivi gli acquisti
 * si propongono per la rivendita, salvo una contropartita abituale diversa
 * dagli acquisti di merci.
 */
class PropostaDaFattura
{

    public const ALIQUOTA_INTEGRAZIONE = 22.0;

    /** @var string[] */
    public array $avvisi = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return array{0: RegistrazioneContabile, 1: RigaIva[]} registrazione e righe IVA non salvate, con il segno del documento
     * @throws \DomainException se manca l'esercizio o un codice IVA adatto
     */
    public function proponi(FatturaElettronica $fattura): array
    {
        $this->avvisi = [];
        $corpo = (new LetturaFatturaPa())->leggi($fattura->contenuto)->getCorpo($fattura->indiceCorpo)
                ?? throw new \DomainException('Documento non trovato nel file della fattura.');
        $soggetto = $fattura->soggetto;
        $causale = $fattura->tipoDocumento->getCausale($fattura->direzione);
        $registrazione = new RegistrazioneContabile($this->dataMapper);
        $registrazione->studio = $soggetto->studio;
        $registrazione->soggetto = $soggetto;
        $registrazione->causale = $causale;
        $registrazione->dataRegistrazione = $this->getDataRegistrazione($fattura);
        $registrazione->esercizioContabile = $this->getEsercizio($fattura, $registrazione->dataRegistrazione);
        $registrazione->controparte = $fattura->controparte;
        $registrazione->numeroDocumento = $fattura->numero;
        $registrazione->dataDocumento = $fattura->data;
        $registrazione->descrizione = mb_substr($fattura->tipoDocumento->getLabel() . ' ' . $fattura->denominazioneControparte, 0, 255);
        $registrazione->registroIva = (new RegistroIvaModel($this->dataMapper))->getPerTipo($soggetto, $causale->getTipoRegistroIva())[0] ?? null;
        $registrazione->dataCompetenzaIva = $fattura->direzione === DirezioneFattura::emessa ? $fattura->data : $registrazione->dataRegistrazione;
        $registrazione->esigibilita = $corpo->getEsigibilita();
        // bollo virtuale delle fatture emesse, versato per trimestre (Services\ImpostaBolloFatture)
        $registrazione->impostaBollo = $fattura->direzione === DirezioneFattura::emessa ? $corpo->bollo : null;
        $segno = $causale->getSegno();
        $rivendita = ($causale->getTipoRegistroIva() === TipoRegistroIva::acquisti)
                && (new RegimeIva($this->dataMapper))->isVentilazione($soggetto, (int) $fattura->data->format('Y'))
                && (($fattura->controparte?->contoContropartita === null) || ($fattura->controparte->contoContropartita->funzione === FunzioneConto::costi));
        $righe = [];
        foreach ($corpo->riepiloghi as $riepilogo) {
            $riga = new RigaIva($this->dataMapper);
            $riga->codiceIva = $this->getCodiceIva($fattura, $riepilogo);
            $riga->imponibile = round($riepilogo['imponibile'] * $segno, 2);
            $riga->imposta = $riga->codiceIva->inversioneContabile && ($riepilogo['imposta'] == 0)
                    ? $riga->codiceIva->calcolaImposta($riga->imponibile)
                    : round($riepilogo['imposta'] * $segno, 2);
            $riga->detraibilita = $riga->codiceIva->detraibilita;
            $riga->rivendita = $rivendita;
            // cessione di beni ammortizzabili (TD26): fuori dal volume d'affari (VE40) e dal calcolo del pro-rata
            $riga->qualifica = $fattura->tipoDocumento === TipoDocumentoFattura::cessioneBeniAmmortizzabili ? QualificaOperazione::beneAmmortizzabile : null;
            // tipologia dell'inversione contabile dalla natura N6 indicata dal fornitore (rigo del quadro VJ)
            $riga->naturaInversione = $riga->codiceIva->inversioneContabile && ($riepilogo['natura'] !== null) && $riepilogo['natura']->isInversioneContabile() ? $riepilogo['natura'] : null;
            $riga->conto = $fattura->controparte?->contoContropartita;
            $righe[] = $riga;
        }
        $registrazione->totaleDocumento = round(abs(PropostaRighe::totaleDocumento($righe)), 2);
        return [$registrazione, $righe];
    }

    /**
     * Oggi, se cade in un esercizio aperto dell'azienda non anteriore al
     * documento; altrimenti la data del documento.
     */
    private function getDataRegistrazione(FatturaElettronica $fattura): SismaDate
    {
        $oggi = new SismaDate(date('Y-m-d'));
        $esercizio = (new EsercizioContabileModel($this->dataMapper))->getPerData($fattura->soggetto, $oggi);
        $documentoNelloStessoEsercizio = ($esercizio !== null) && $esercizio->comprende($fattura->data);
        return ($esercizio !== null) && ($esercizio->chiuso === false) && $documentoNelloStessoEsercizio ? $oggi : $fattura->data;
    }

    private function getEsercizio(FatturaElettronica $fattura, SismaDate $data): EsercizioContabile
    {
        return (new EsercizioContabileModel($this->dataMapper))->getPerData($fattura->soggetto, $data)
                ?? throw new \DomainException('Nessun esercizio contabile dell\'azienda comprende il ' . $data->format('d/m/Y') . ': aprirlo prima di registrare la fattura.');
    }

    private function getCodiceIva(FatturaElettronica $fattura, array $riepilogo): CodiceIva
    {
        $model = new CodiceIvaModel($this->dataMapper);
        $studio = $fattura->studio;
        $ricevuta = $fattura->direzione === DirezioneFattura::ricevuta;
        $documentoIntegrazione = $fattura->tipoDocumento->isIntegrazione() && ($fattura->tipoDocumento !== TipoDocumentoFattura::autofatturaRegolarizzazione);
        $naturaInversione = ($riepilogo['natura'] !== null) && $riepilogo['natura']->isInversioneContabile();
        $integrazione = $ricevuta && ($documentoIntegrazione || $naturaInversione);
        if ($integrazione) {
            $aliquota = $riepilogo['aliquota'] > 0 ? $riepilogo['aliquota'] : self::ALIQUOTA_INTEGRAZIONE;
            if ($riepilogo['aliquota'] <= 0) {
                $this->avvisi[] = 'Inversione contabile: proposta l\'integrazione al ' . self::ALIQUOTA_INTEGRAZIONE . '%, verificare l\'aliquota dell\'operazione.';
            }
            $prefisso = match ($fattura->tipoDocumento) {
                TipoDocumentoFattura::integrazioneBeniIntracomunitari => 'UE',
                TipoDocumentoFattura::integrazioneServiziEstero, TipoDocumentoFattura::integrazioneBeniArt17 => 'ES',
                default => 'RC',
            };
            $codice = $model->getPerCodice($studio, $prefisso . self::formattaAliquota($aliquota)) ?? $model->trova($studio, $aliquota, null, true);
        } else {
            $codice = $riepilogo['natura'] !== null ? ($model->getPerCodice($studio, $riepilogo['natura']->value) ?? $model->trova($studio, 0.0, $riepilogo['natura'])) : $model->trova($studio, $riepilogo['aliquota'], null);
        }
        return $codice ?? throw new \DomainException('Nessun codice IVA per l\'aliquota ' . self::formattaAliquota($riepilogo['aliquota']) . '%'
                . ($riepilogo['natura'] === null ? '' : ' e la natura ' . $riepilogo['natura']->value) . ': aggiungerlo nei codici IVA dello studio.');
    }

    private static function formattaAliquota(float $aliquota): string
    {
        return rtrim(rtrim(number_format($aliquota, 2, '.', ''), '0'), '.');
    }
}
