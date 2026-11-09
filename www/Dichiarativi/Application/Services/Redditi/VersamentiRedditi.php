<?php

namespace Dichiarativi\Application\Services\Redditi;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Models\IndirizzoModel;
use Deleghe\Application\Entities\Credito;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Models\CodiceTributoModel;
use Deleghe\Application\Models\CreditoModel;
use Deleghe\Application\Models\VersamentoModel;
use Deleghe\Application\Services\ArchivioVersamenti;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Services\Iva\RegioniVt;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;
use Telonio\Application\HelperClasses\Calendario;

/**
 * Versamenti del modello Redditi PF in Deleghe F24 (saldo e primo acconto
 * entro il 30 giugno, secondo o unico acconto entro il 30 novembre
 * dell'anno successivo, art. 17 D.P.R. 435/2001; termini delle istruzioni,
 * fascicolo 1, righi RN62 e LC2):
 * - IRPEF 4001, acconti 4033 e 4034;
 * - addizionale regionale 3801 (sezione Regioni, codice della regione del
 *   domicilio al 1° gennaio); comunale 3844 e acconto 3843 (sezione IMU e
 *   altri tributi locali, codice catastale del comune);
 * - cedolare secca 1842, acconti 1840 e 1841;
 * - imposta sostitutiva dei forfetari 1792, acconti 1790 e 1791.
 *
 * I crediti in compensazione (col. 5 del quadro RX) sono registrati come
 * crediti di Deleghe dal 1° gennaio successivo; oltre 5.000 euro solo
 * alla presentazione e dal decimo giorno successivo (art. 17 D.Lgs.
 * 241/1997).
 */
class VersamentiRedditi
{

    private const LIMITE_CREDITO_LIBERO = 5000.0;

    /** IRPEF e addizionali fino a 12 euro: né versamento né compensazione (istruzioni, fascicolo 1, parte I) */
    private const IMPORTO_MINIMO = 12.0;
    private const CODICI_SOGLIA = ['4001', '3801', '3844', '3843'];

    /** Codici degli acconti versati per anno di riferimento */
    public const ACCONTI = ['irpef' => ['4033', '4034'], 'cedolare' => ['1840', '1841'], 'comunale' => ['3843'], 'forfetario' => ['1790', '1791']];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Registra (o sostituisce) saldi e acconti da versare.
     *
     * @return string[]
     */
    public function registraVersamenti(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri): array
    {
        $anno = $dichiarazione->anno;
        $soggetto = $dichiarazione->soggetto;
        $q = fn(string $codice) => (float) $quadri->getImporto($codice, 1);
        $giugno = Calendario::termineFiscale(new SismaDate(($anno + 1) . '-06-30'));
        $novembre = Calendario::termineFiscale(new SismaDate(($anno + 1) . '-11-30'));
        $regione = $this->getCodiceRegione($soggetto, $anno);
        $comune = self::getDomicilio($this->dataMapper, $soggetto, new \DateTimeImmutable($anno . '-01-01'));
        $comuneAcconto = self::getDomicilio($this->dataMapper, $soggetto, new \DateTimeImmutable(($anno + 1) . '-01-01'));
        $forfetario = $q('LM042001');
        $accontoForfetario = $forfetario >= 52 ? $forfetario : 0.0;
        $primaRataForfetario = round($accontoForfetario * 0.4) > 103 ? round($accontoForfetario * 0.4) : 0.0;
        $voci = [
            [SezioneF24::erario, '4001', $q('RX001001'), $anno, $giugno, null, 'Saldo IRPEF'],
            [SezioneF24::erario, '4033', $q('RN062001'), $anno + 1, $giugno, null, 'Primo acconto IRPEF'],
            [SezioneF24::erario, '4034', $q('RN062002'), $anno + 1, $novembre, null, 'Secondo o unico acconto IRPEF'],
            [SezioneF24::regioni, '3801', $q('RX002001'), $anno, $giugno, $regione, 'Saldo addizionale regionale'],
            [SezioneF24::tributiLocali, '3844', $q('RX003001'), $anno, $giugno, $comune?->codiceCatastale, 'Saldo addizionale comunale'],
            [SezioneF24::tributiLocali, '3843', $q('RV017008'), $anno + 1, $giugno, $comuneAcconto?->codiceCatastale, 'Acconto addizionale comunale'],
            [SezioneF24::erario, '1842', $q('RX004001'), $anno, $giugno, null, 'Saldo cedolare secca'],
            [SezioneF24::erario, '1840', $q('LC002001'), $anno + 1, $giugno, null, 'Primo acconto cedolare secca'],
            [SezioneF24::erario, '1841', $q('LC002002'), $anno + 1, $novembre, null, 'Secondo o unico acconto cedolare secca'],
            [SezioneF24::erario, '1792', $q('LM046001'), $anno, $giugno, null, 'Saldo imposta sostitutiva forfetari'],
            [SezioneF24::erario, '1790', $primaRataForfetario, $anno + 1, $giugno, null, 'Primo acconto imposta sostitutiva forfetari'],
            [SezioneF24::erario, '1791', $accontoForfetario - $primaRataForfetario, $anno + 1, $novembre, null, 'Secondo o unico acconto imposta sostitutiva forfetari'],
        ];
        $versamenti = [];
        $messaggi = [];
        foreach ($voci as [$sezione, $codice, $importo, $annoRiferimento, $scadenza, $ente, $descrizione]) {
            if (($importo <= 0) || (in_array($codice, self::CODICI_SOGLIA, true) && ($importo <= self::IMPORTO_MINIMO))) {
                continue;
            }
            if (($sezione !== SezioneF24::erario) && ($ente === null)) {
                $messaggi[] = $descrizione . ': domicilio fiscale non trovato nell\'Anagrafica, versamento da inserire a mano in Deleghe.';
                continue;
            }
            $versamento = new Versamento($this->dataMapper);
            $versamento->scadenza = $scadenza;
            $versamento->sezione = $sezione;
            $versamento->codiceTributo = $this->getCodiceTributo($sezione, $codice);
            $versamento->codiceEnte = $ente;
            $versamento->rateazione = $versamento->codiceTributo->formatoRateazione === '0000' ? null : '0101';
            $versamento->annoRiferimento = $annoRiferimento;
            $versamento->acconto = $sezione === SezioneF24::tributiLocali && ($codice === '3843');
            $versamento->saldo = $sezione === SezioneF24::tributiLocali && ($codice === '3844');
            $versamento->importoDebito = $importo;
            $versamento->descrizione = $descrizione . ' ' . $anno;
            $versamenti[] = $versamento;
        }
        $esito = (new ArchivioVersamenti($this->dataMapper))->registra($soggetto, Dichiarazione::ORIGINE, $dichiarazione->getRiferimento(), $versamenti);
        $messaggi[] = count($versamenti) === 0 ? 'Nessun versamento da registrare.'
                : sprintf('Registrati %d versamenti per € %s complessivi.', count($versamenti), number_format(array_sum(array_map(fn(Versamento $versamento) => $versamento->importoDebito, $versamenti)), 2, ',', '.'));
        return array_merge($messaggi, $esito->differenze);
    }

    /**
     * Registra i crediti da compensare della colonna 5 del quadro RX.
     *
     * @return string[]
     */
    public function registraCrediti(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri): array
    {
        $anno = $dichiarazione->anno;
        $soggetto = $dichiarazione->soggetto;
        $comune = self::getDomicilio($this->dataMapper, $soggetto, new \DateTimeImmutable($anno . '-01-01'));
        $voci = [
            [SezioneF24::erario, '4001', 'RX001005', null, 'Credito IRPEF'],
            [SezioneF24::regioni, '3801', 'RX002005', $this->getCodiceRegione($soggetto, $anno), 'Credito addizionale regionale'],
            [SezioneF24::tributiLocali, '3844', 'RX003005', $comune?->codiceCatastale, 'Credito addizionale comunale'],
            [SezioneF24::erario, '1842', 'RX004005', null, 'Credito cedolare secca'],
        ];
        $messaggi = [];
        $creditoModel = new CreditoModel($this->dataMapper);
        foreach ($voci as [$sezione, $codice, $campo, $ente, $descrizione]) {
            $importo = (float) $quadri->getImporto($campo, 1);
            $riferimento = $dichiarazione->getRiferimento() . '-' . $codice;
            $credito = $this->getCredito($dichiarazione, $riferimento);
            if (($credito !== null) && ($creditoModel->getUtilizzato($credito) > 0)) {
                if (abs($credito->importo - $importo) >= 0.005) {
                    $messaggi[] = $descrizione . ' già utilizzato in F24: non aggiornato.';
                }
                continue;
            }
            $daPresentare = ($importo > self::LIMITE_CREDITO_LIBERO) && ($dichiarazione->dataPresentazione === null);
            if (($importo <= 0) || (in_array($codice, self::CODICI_SOGLIA, true) && ($importo <= self::IMPORTO_MINIMO)) || $daPresentare || (($sezione !== SezioneF24::erario) && ($ente === null))) {
                if ($credito !== null) {
                    $this->dataMapper->delete($credito);
                }
                if ($daPresentare) {
                    $messaggi[] = sprintf('%s di € %s oltre 5.000 euro: sarà registrato alla presentazione.', $descrizione, number_format($importo, 2, ',', '.'));
                }
                continue;
            }
            $credito ??= new Credito($this->dataMapper);
            $credito->studio = $dichiarazione->studio;
            $credito->soggetto = $soggetto;
            $credito->sezione = $sezione;
            $credito->codiceTributo = $this->getCodiceTributo($sezione, $codice);
            $credito->codiceEnte = $ente;
            $credito->rateazione = $credito->codiceTributo->formatoRateazione === '0000' ? null : '0101';
            $credito->annoRiferimento = $anno;
            $credito->importo = $importo;
            $credito->utilizzabileDal = $importo > self::LIMITE_CREDITO_LIBERO
                    ? new SismaDate((new \DateTimeImmutable($dichiarazione->dataPresentazione->format('Y-m-d')))->modify('+10 days')->format('Y-m-d'))
                    : new SismaDate(($anno + 1) . '-01-01');
            $credito->descrizione = $descrizione . ' ' . $anno . ' da modello Redditi';
            $credito->origine = Dichiarazione::ORIGINE;
            $credito->riferimentoOrigine = $riferimento;
            $this->dataMapper->save($credito);
            $messaggi[] = sprintf('%s di € %s registrato in Deleghe, compensabile dal %s.', $descrizione, number_format($importo, 2, ',', '.'), $credito->utilizzabileDal->format('d/m/Y'));
        }
        return $messaggi;
    }

    /**
     * Elimina i crediti non ancora utilizzati (dichiarazione riportata in
     * bozza o eliminata).
     *
     * @return string[]
     */
    public function annullaCrediti(Dichiarazione $dichiarazione): array
    {
        $messaggi = [];
        $creditoModel = new CreditoModel($this->dataMapper);
        foreach ((new CreditoModel($this->dataMapper))->getElenco($dichiarazione->soggetto) as $credito) {
            if (($credito->origine === Dichiarazione::ORIGINE) && str_starts_with((string) $credito->riferimentoOrigine, $dichiarazione->getRiferimento() . '-')) {
                if ($creditoModel->getUtilizzato($credito) > 0) {
                    $messaggi[] = $credito->descrizione . ': già utilizzato, resta nell\'archivio.';
                } else {
                    $this->dataMapper->delete($credito);
                }
            }
        }
        return $messaggi;
    }

    private function getCredito(Dichiarazione $dichiarazione, string $riferimento): ?Credito
    {
        foreach ((new CreditoModel($this->dataMapper))->getElenco($dichiarazione->soggetto) as $credito) {
            if (($credito->origine === Dichiarazione::ORIGINE) && ($credito->riferimentoOrigine === $riferimento)) {
                return $credito;
            }
        }
        return null;
    }

    /**
     * Acconti versati per l'anno d'imposta (deleghe non in bozza), per
     * tipo: IRPEF (RN38), cedolare secca (LC1, col. 8), addizionale comunale
     * (RV11, col. 2), imposta sostitutiva dei forfetari (LM45).
     *
     * @return array<string, float>
     */
    public static function getAccontiVersati(DataMapper $dataMapper, Soggetto $soggetto, int $anno): array
    {
        $acconti = array_fill_keys(array_keys(self::ACCONTI), 0.0);
        foreach ((new VersamentoModel($dataMapper))->getPerPeriodo($soggetto->studio, new SismaDate($anno . '-01-01'), new SismaDate($anno . '-12-31'), $soggetto) as $versamento) {
            if (($versamento->delega === null) || ($versamento->delega->stato === StatoDelega::bozza) || ($versamento->annoRiferimento !== $anno)) {
                continue;
            }
            foreach (self::ACCONTI as $tipo => $codici) {
                if (in_array($versamento->codiceTributo?->codice, $codici, true)) {
                    $acconti[$tipo] += $versamento->importoDebito;
                }
            }
        }
        return $acconti;
    }

    /**
     * Domicilio fiscale alla data: indirizzo di domicilio fiscale o, in
     * mancanza, di residenza dell'Anagrafica.
     */
    public static function getDomicilio(DataMapper $dataMapper, Soggetto $soggetto, \DateTimeImmutable $data): ?Comune
    {
        $indirizzi = (new IndirizzoModel($dataMapper))->getVigentiAl($soggetto, $data);
        foreach ([TipoIndirizzo::domicilioFiscale, TipoIndirizzo::residenza] as $tipo) {
            foreach ($indirizzi as $indirizzo) {
                if (($indirizzo->tipoIndirizzo === $tipo) && ($indirizzo->comune !== null)) {
                    return $indirizzo->comune;
                }
            }
        }
        return null;
    }

    /**
     * Codice della regione per l'F24 (lo stesso della tabella delle
     * addizionali regionali), dal domicilio al 1° gennaio dell'anno.
     */
    private function getCodiceRegione(Soggetto $soggetto, int $anno): ?string
    {
        $rigo = RegioniVt::getRigo(self::getDomicilio($this->dataMapper, $soggetto, new \DateTimeImmutable($anno . '-01-01'))?->siglaProvincia);
        return $rigo === null ? null : sprintf('%02d', $rigo - 1);
    }

    private function getCodiceTributo(SezioneF24 $sezione, string $codice): \Deleghe\Application\Entities\CodiceTributo
    {
        $sezioneCodice = match ($sezione) {
            SezioneF24::regioni => SezioneCodiceTributo::regioni,
            SezioneF24::tributiLocali => SezioneCodiceTributo::tributiLocali,
            default => SezioneCodiceTributo::erario,
        };
        return (new CodiceTributoModel($this->dataMapper))->getByCodice($sezioneCodice, $codice)
                ?? throw new \InvalidArgumentException('Codice tributo ' . $codice . ' assente nelle tabelle di Deleghe F24.');
    }
}
