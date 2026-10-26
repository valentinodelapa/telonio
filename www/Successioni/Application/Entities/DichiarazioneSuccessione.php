<?php

namespace Successioni\Application\Entities;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\ContoCorrente;
use Anagrafica\Application\Entities\Soggetto;
use Telonio\Application\Entities\Studio;
use Deleghe\Application\Entities\Intermediario;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;
use Successioni\Application\Enumerations\CaricaDichiarante;
use Successioni\Application\Enumerations\ImpegnoTrasmissione;
use Successioni\Application\Enumerations\ResidenzaEsteraDefunto;
use Successioni\Application\Enumerations\StatoCivileDefunto;
use Successioni\Application\Enumerations\StatoDichiarazioneSuccessione;
use Successioni\Application\Enumerations\TempisticaPagamento;
use Successioni\Application\Enumerations\TipoDichiarazioneSuccessione;
use Successioni\Application\Enumerations\TipoImpostaCredito;

/**
 * Dichiarazione di successione e domanda di volture catastali del defunto
 * (art. 28 e seguenti, D.Lgs. 346/1990), da presentare in via telematica
 * entro dodici mesi dall'apertura della successione (data del decesso o
 * decorrenza diversa).
 *
 * Contiene il frontespizio, i dati del quadro EH (dichiarazioni
 * sostitutive) e le scelte del quadro EF; beneficiari (EA), beni con le
 * devoluzioni (EB, EC, EO, ER), passività (ED) e allegati (EG) sono in
 * entità proprie. Le imposte sono liquidate da Services\Liquidazione.
 *
 * Per le successioni aperte dal 1° gennaio 2025 l'imposta di successione
 * è autoliquidata (art. 33 TUS, D.Lgs. 139/2024): con pagamento non
 * contestuale si versa con F24 entro 90 giorni dal termine di
 * presentazione, anche a rate (art. 38).
 *
 * Importi in euro interi, come nel modello.
 */
class DichiarazioneSuccessione extends ReferencedEntity
{

    public const INIZIO_AUTOLIQUIDAZIONE = '2025-01-01';
    public const MESI_PRESENTAZIONE = 12;
    public const GIORNI_PAGAMENTO = 90;
    public const OPZIONI_PRIMA_CASA = ['R' => 'Immobile nel comune di residenza (o vi trasferirà la residenza entro 18 mesi)', 'A' => 'Immobile nel comune in cui svolge la propria attività',
        'C' => 'Trasferirà la residenza nel comune entro 18 mesi', 'E' => 'Trasferito all\'estero per lavoro (o emigrato) nel comune di nascita o di ultima residenza',
        'F' => 'Appartenente alle Forze armate o di polizia'];
    public const ALTRA_CASA = ['N' => 'Non titolare di altra casa acquistata con le agevolazioni', 'V' => 'Venderà entro un anno la casa acquistata con le agevolazioni'];

    protected int $id;
    protected Studio $studio;
    protected Soggetto $defunto;
    protected TipoDichiarazioneSuccessione $tipoDichiarazione = TipoDichiarazioneSuccessione::prima;
    protected ?int $annoPrima = null;
    protected ?string $volumePrima = null;
    protected ?string $numeroPrima = null;
    protected bool $devoluzioneLegge = true;
    protected bool $devoluzioneTestamento = false;
    protected ?string $testamentoPubblicoUfficiale = null;
    protected ?SismaDate $testamentoDataPubblicazione = null;
    protected ?UfficioRegistrazione $testamentoUfficio = null;
    protected ?string $testamentoSerie = null;
    protected ?string $testamentoNumero = null;
    protected ?string $testamentoSottonumero = null;
    protected ?SismaDate $testamentoDataRegistrazione = null;
    protected bool $testamentoEstero = false;
    protected bool $leggeEstera = false;
    protected bool $eventiEccezionali = false;
    protected bool $beneficioInventario = false;
    protected ?Comune $comuneDecesso = null;
    protected ?string $statoDecesso = null;
    protected ?StatoCivileDefunto $statoCivile = null;
    protected ?ResidenzaEsteraDefunto $residenzaEstera = null;
    protected bool $separazioneColpa = false;
    protected ?Soggetto $presentatore = null;
    protected ?CaricaDichiarante $codiceCarica = null;
    protected ?Soggetto $rappresentato = null;
    protected ?SismaDate $decorrenzaTermine = null;
    protected ?string $telefono = null;
    protected ?string $email = null;
    protected bool $senzaVolture = false;
    protected bool $copiaConforme = false;
    protected ?Intermediario $intermediario = null;
    protected ?ImpegnoTrasmissione $impegno = null;
    protected ?SismaDate $dataImpegno = null;
    protected ?ContoCorrente $contoAddebito = null;
    protected ?TempisticaPagamento $tempisticaPagamento = null;
    protected ?int $numeroRate = null;
    protected bool $pagamentoAnticipatoTrust = false;
    protected ?int $acconto = null;
    protected ?SismaDate $dataVersamento = null;
    protected ?int $ipotecariaVersata = null;
    protected ?int $catastaleVersata = null;
    protected ?int $successioneVersata = null;
    protected ?int $creditoIpotecaria = null;
    protected ?int $creditoCatastale = null;
    protected ?int $creditoSuccessione = null;
    protected ?TipoImpostaCredito $creditoTipo = null;
    protected ?int $creditoImporto = null;
    protected ?UfficioRegistrazione $creditoAcquistoUfficio = null;
    protected ?string $creditoAcquistoSerie = null;
    protected ?string $creditoAcquistoNumero = null;
    protected ?string $creditoAcquistoSottonumero = null;
    protected ?SismaDate $creditoAcquistoData = null;
    protected ?UfficioRegistrazione $creditoVenditaUfficio = null;
    protected ?string $creditoVenditaSerie = null;
    protected ?string $creditoVenditaNumero = null;
    protected ?string $creditoVenditaSottonumero = null;
    protected ?SismaDate $creditoVenditaData = null;
    protected ?int $numeroCircoscrizioni = null;
    protected ?string $primaCasaOpzione = null;
    protected bool $primaCasaTrasferimentoPrima = false;
    protected bool $primaCasaNessunaAltraComune = true;
    protected ?string $primaCasaAltraCasa = null;
    protected ?string $vincoloProtocollo = null;
    protected ?SismaDate $vincoloData = null;
    protected ?string $precedenteUfficio = null;
    protected ?int $precedenteAnno = null;
    protected ?string $precedenteVolume = null;
    protected ?string $precedenteNumero = null;
    protected StatoDichiarazioneSuccessione $stato = StatoDichiarazioneSuccessione::bozza;
    protected ?SismaDate $dataDichiarazione = null;
    protected ?SismaDate $dataPresentazione = null;
    protected ?string $protocollo = null;
    protected ?string $registrazioneVolume = null;
    protected ?string $registrazioneNumero = null;
    protected ?DichiarazioneSuccessione $sostituita = null;
    protected ?SismaDateTime $voltureApplicate = null;
    protected ?string $annotazioni = null;
    protected SismaDateTime $dataInserimento;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataInserimento = new SismaDateTime();
    }

    public function isModificabile(): bool
    {
        return $this->stato === StatoDichiarazioneSuccessione::bozza;
    }

    public function getDataApertura(): SismaDate
    {
        return $this->defunto->dataDecesso;
    }

    /**
     * Successione aperta dal 1° gennaio 2025: imposta di successione
     * autoliquidata (quadro EF, sezione V-bis).
     */
    public function isAutoliquidazione(): bool
    {
        return $this->getDataApertura()->format('Y-m-d') >= self::INIZIO_AUTOLIQUIDAZIONE;
    }

    /**
     * Termine di presentazione: dodici mesi dalla data di apertura o dalla
     * decorrenza indicata (art. 31 TUS).
     */
    public function getScadenza(): SismaDate
    {
        $decorrenza = $this->decorrenzaTermine ?? $this->getDataApertura();
        return new SismaDate((new \DateTimeImmutable($decorrenza->format('Y-m-d')))->modify('+' . self::MESI_PRESENTAZIONE . ' months')->format('Y-m-d'));
    }

    /**
     * Termine di pagamento dell'imposta autoliquidata (o dell'acconto):
     * novanta giorni dal termine di presentazione (art. 37 TUS).
     */
    public function getScadenzaPagamento(): SismaDate
    {
        return new SismaDate((new \DateTimeImmutable($this->getScadenza()->format('Y-m-d')))->modify('+' . self::GIORNI_PAGAMENTO . ' days')->format('Y-m-d'));
    }

    public function getRiferimentoVersamenti(): string
    {
        return 'dichiarazione-' . $this->id;
    }

    public function getTitolo(): string
    {
        return 'Successione di ' . $this->defunto->nominativo . ' (' . $this->getDataApertura()->format('d/m/Y') . ')';
    }
}
