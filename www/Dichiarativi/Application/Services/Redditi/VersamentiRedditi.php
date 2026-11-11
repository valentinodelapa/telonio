<?php

namespace Dichiarativi\Application\Services\Redditi;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Models\DatiFiscaliModel;
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
use Deleghe\Application\Services\CalcoloRateazione;
use Deleghe\Application\Services\RateazioneService;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModalitaSaldo;
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
 * - imposta sostitutiva dei forfetari 1792, acconti 1790 e 1791;
 * - IVIE 4041, acconti 4044 e 4045; IVAFE 4043, acconti 4047 e 4048;
 *   imposta sulle cripto-attività 1727, acconti 1728 e 1729 (istruzioni
 *   del quadro RW, fascicolo 2: modalità e termini dell'IRPEF);
 * - imposta sostitutiva sui redditi di capitale di fonte estera (RM31)
 *   1242;
 * - imposte sostitutive del quadro RT: plusvalenze 1100, cripto-attività
 *   in regime dichiarativo 1715 (senza acconti).
 *
 * Saldo e primo acconto si possono differire di 30 giorni con la
 * maggiorazione dello 0,40 per cento (art. 17, comma 2, D.P.R. 435/2001) e
 * versare a rate mensili fino a dicembre con gli interessi (art. 20 D.Lgs.
 * 241/1997): il piano di rateazione si crea in Deleghe.
 *
 * I crediti in compensazione (col. 5 del quadro RX) sono registrati come
 * crediti di Deleghe dal 1° gennaio successivo; oltre 5.000 euro solo
 * alla presentazione e dal decimo giorno successivo (art. 17 D.Lgs.
 * 241/1997).
 */
class VersamentiRedditi
{

    private const LIMITE_CREDITO_LIBERO = 5000.0;

    /** IRPEF, addizionali, IVIE, IVAFE e cripto-attività fino a 12 euro: né versamento né compensazione (istruzioni, fascicolo 1, parte I, e quadro RW) */
    private const IMPORTO_MINIMO = 12.0;
    private const CODICI_SOGLIA = ['4001', '3801', '3844', '3843', '4041', '4043', '1727'];

    /** Codici degli acconti versati per anno di riferimento */
    public const ACCONTI = ['irpef' => ['4033', '4034'], 'cedolare' => ['1840', '1841'], 'comunale' => ['3843'], 'forfetario' => ['1790', '1791'],
        'ivie' => ['4044', '4045'], 'ivafe' => ['4047', '4048'], 'cripto' => ['1728', '1729']];

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
        $differito = $dichiarazione->modalitaSaldo === ModalitaSaldo::differito;
        $giugno = Calendario::termineFiscale(new SismaDate(($anno + 1) . ($differito ? '-07-30' : '-06-30')));
        $novembre = Calendario::termineFiscale(new SismaDate(($anno + 1) . '-11-30'));
        $regione = $this->getCodiceRegione($soggetto, $anno);
        $comune = self::getDomicilio($this->dataMapper, $soggetto, new \DateTimeImmutable($anno . '-01-01'));
        $comuneAcconto = self::getDomicilio($this->dataMapper, $soggetto, new \DateTimeImmutable(($anno + 1) . '-01-01'));
        $forfetario = $q('LM042001');
        $accontoForfetario = $forfetario >= 52 ? $forfetario : 0.0;
        $primaRataForfetario = round($accontoForfetario * 0.4) > 103 ? round($accontoForfetario * 0.4) : 0.0;
        // IVIE, IVAFE e imposta sulle cripto-attività: con le modalità e i termini dell'IRPEF, anche per gli acconti (istruzioni del quadro RW)
        $isa = (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($soggetto, new SismaDate($anno . '-12-31'))?->soggettoIsa ?? false;
        $esteri = [];
        foreach (['ivie' => ['4041', '4044', '4045', 'RX025001', 'IVIE'], 'ivafe' => ['4043', '4047', '4048', 'RX026001', 'IVAFE'], 'cripto' => ['1727', '1728', '1729', 'RX027001', 'imposta sulle cripto-attività']]
                as $tipo => [$saldo, $primo, $secondo, $rigo, $nome]) {
            [$primaRata, $secondaRata] = self::acconti((float) PropostaEsteri::getImposteDovute($quadri)[$tipo], $isa);
            $esteri[] = [SezioneF24::erario, $saldo, $q($rigo), $anno, $giugno, null, 'Saldo ' . $nome];
            $esteri[] = [SezioneF24::erario, $primo, $primaRata, $anno + 1, $giugno, null, 'Primo acconto ' . $nome];
            $esteri[] = [SezioneF24::erario, $secondo, $secondaRata, $anno + 1, $novembre, null, 'Secondo o unico acconto ' . $nome];
        }
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
            ...$esteri,
            [SezioneF24::erario, '1242', $q('RX007001'), $anno, $giugno, null, 'Imposta sostitutiva sui redditi di capitale di fonte estera'],
            [SezioneF24::erario, '1100', $q('RX020001'), $anno, $giugno, null, 'Imposta sostitutiva sulle plusvalenze'],
            [SezioneF24::erario, '1715', $q('RX021001'), $anno, $giugno, null, 'Imposta sostitutiva sulle plusvalenze delle cripto-attività'],
        ];
        $versamenti = [];
        $messaggi = [];
        $rateazione = new RateazioneService($this->dataMapper);
        foreach ((new VersamentoModel($this->dataMapper))->getGenerati($soggetto, Dichiarazione::ORIGINE, $dichiarazione->getRiferimento()) as $precedente) {
            if ($precedente->pianoRateazione !== null) {
                $blocco = $rateazione->getMotivoBlocco($precedente->pianoRateazione);
                if ($blocco !== null) {
                    return ['I versamenti sono già rateizzati e il piano non si può più modificare (' . $blocco . '): nessuna nuova registrazione.'];
                }
                $rateazione->elimina($precedente->pianoRateazione);
            }
        }
        if ($differito) {
            $messaggi[] = 'Saldo e primo acconto differiti al ' . $giugno->format('d/m/Y') . ' con la maggiorazione dello 0,40%.';
        }
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
            $versamento->importoDebito = ($differito && ($scadenza === $giugno)) ? round($importo * 1.004, 2) : $importo;
            $versamento->descrizione = $descrizione . ' ' . $anno;
            $versamenti[] = $versamento;
        }
        $esito = (new ArchivioVersamenti($this->dataMapper))->registra($soggetto, Dichiarazione::ORIGINE, $dichiarazione->getRiferimento(), $versamenti);
        $messaggi[] = count($versamenti) === 0 ? 'Nessun versamento da registrare.'
                : sprintf('Registrati %d versamenti per € %s complessivi.', count($versamenti), number_format(array_sum(array_map(fn(Versamento $versamento) => $versamento->importoDebito, $versamenti)), 2, ',', '.'));
        $numeroRate = (int) $dichiarazione->numeroRate;
        if (($dichiarazione->modalitaSaldo === ModalitaSaldo::rate) || ($differito && ($numeroRate > 1))) {
            $originali = array_values(array_filter($esito->versamenti, fn(Versamento $versamento) => ($versamento->scadenza->format('Y-m-d') === $giugno->format('Y-m-d'))
                            && CalcoloRateazione::isRateizzabile($versamento)));
            if ($numeroRate < 2) {
                $messaggi[] = 'Indicare il numero delle rate (almeno 2): versamenti registrati in unica soluzione.';
            } elseif (count($originali) > 0) {
                $date = CalcoloRateazione::getDateProposte(CalcoloRateazione::getPrimaRata($giugno), $numeroRate);
                $rateazione->crea($soggetto, $originali, $date, 'Saldo e primo acconto Redditi ' . $anno . ' in ' . count($date) . ' rate');
                $messaggi[] = sprintf('Saldo e primo acconto ripartiti in %d rate mensili dal %s, con gli interessi (piano di rateazione in Deleghe).', count($date), $date[0]->format('d/m/Y'));
            }
        }
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
            [SezioneF24::erario, '4041', 'RX025005', null, 'Credito IVIE'],
            [SezioneF24::erario, '4043', 'RX026005', null, 'Credito IVAFE'],
            [SezioneF24::erario, '1727', 'RX027005', null, 'Credito imposta sulle cripto-attività'],
            [SezioneF24::erario, '1100', 'RX020005', null, 'Credito imposta sostitutiva sulle plusvalenze'],
            [SezioneF24::erario, '1715', 'RX021005', null, 'Credito imposta sostitutiva sulle plusvalenze delle cripto-attività'],
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
    /**
     * Acconti sull'imposta dovuta dell'anno, come per l'IRPEF: dovuti da 52
     * euro, primo acconto del 40 per cento (50 per i soggetti ISA) se supera
     * 103 euro, altrimenti tutto con il secondo.
     *
     * @return array{0: float, 1: float}
     */
    public static function acconti(float $base, bool $isa): array
    {
        if ($base < 52) {
            return [0.0, 0.0];
        }
        $primo = round($base * ($isa ? 0.5 : 0.4));
        return $primo > 103 ? [$primo, $base - $primo] : [0.0, $base];
    }

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
