<?php

namespace Dichiarativi\Application\Services\Iva;

use Contabilita\Application\Entities\AttivitaIva;
use Contabilita\Application\Entities\LiquidazioneIva;
use Contabilita\Application\Entities\ProrataIva;
use Contabilita\Application\Models\ProrataIvaModel;
use Contabilita\Application\Services\AttivitaSeparate;
use Deleghe\Application\Entities\Credito;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Models\CodiceTributoModel;
use Deleghe\Application\Models\CreditoModel;
use Deleghe\Application\Models\VersamentoModel;
use Deleghe\Application\Services\ArchivioVersamenti;
use Deleghe\Application\Services\CalcoloRateazione;
use Deleghe\Application\Services\EsitoRegistrazione;
use Deleghe\Application\Services\RateazioneService;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModalitaSaldo;
use Dichiarativi\Application\Models\ModuloDichiarazioneModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;
use Telonio\Application\HelperClasses\Calendario;

/**
 * Risultato della dichiarazione IVA nell'archivio di Deleghe F24:
 *
 * - saldo (rigo VX1) con il codice tributo 6099 entro il 16 marzo
 *   dell'anno successivo (art. 6, c. 1, D.P.R. 542/1999), non dovuto fino
 *   a 10,33 euro, nella modalità scelta nella dichiarazione: unica
 *   soluzione, rate mensili (piano di rateazione di Deleghe, art. 20
 *   D.Lgs. 241/1997) o differimento al termine del saldo delle imposte sui
 *   redditi con la maggiorazione dello 0,40% per mese o frazione dal 16
 *   marzo al 30 giugno e, nei 30 giorni successivi, un ulteriore 0,40% (art.
 *   17, c. 1 e 2, D.P.R. 435/2001), anche a rate; per i trimestrali comprende l'IVA del quarto trimestre,
 *   quindi il versamento 6099 registrato dalla liquidazione del quarto
 *   trimestre in Contabilità è sostituito (o, se già in una delega
 *   confermata, scalato dal saldo);
 * - credito da compensare (rigo VX5) nell'archivio dei crediti, alla
 *   presentazione: codice 6099, anno d'imposta, utilizzabile dal 1°
 *   gennaio dell'anno successivo fino a 5.000 euro, oltre dal decimo giorno
 *   successivo alla presentazione (art. 17, c. 1, D.Lgs. 241/1997). Il
 *   credito già utilizzato non si modifica;
 * - percentuale di detrazione (pro-rata) dell'anno come provvisoria per
 *   l'anno successivo nelle liquidazioni di Contabilità.
 */
class VersamentiIva
{

    public const IMPORTO_MINIMO_SALDO = 10.33;
    public const LIMITE_CREDITO_LIBERO = 5000.0;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Registra (o sostituisce) il versamento del saldo.
     *
     * @return string[] messaggi per l'utente
     */
    public function registraSaldo(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri): array
    {
        $messaggi = [];
        // controllante dell'IVA di gruppo: anche il saldo del gruppo (VY1 del prospetto IVA 26 PR)
        $saldo = (float) ($quadri->getImporto('VX001001', 1) + $quadri->getImporto('VY001001', 1));
        $archivio = new ArchivioVersamenti($this->dataMapper);
        $riferimentoQuarto = LiquidazioneIva::getRiferimento($dichiarazione->anno, 4);
        $quarto = array_filter(iterator_to_array((new VersamentoModel($this->dataMapper))->getGenerati($dichiarazione->soggetto, LiquidazioneIva::ORIGINE, $riferimentoQuarto), false),
                fn(Versamento $versamento) => $versamento->codiceTributo?->codice === '6099');
        if (count($quarto) > 0) {
            $bloccati = array_filter($quarto, fn(Versamento $versamento) => ($versamento->delega !== null) && ($versamento->delega->isModificabile() === false));
            if (count($bloccati) > 0) {
                $giaInDelega = array_sum(array_map(fn(Versamento $versamento) => $versamento->importoDebito, $bloccati));
                $saldo = max(0.0, $saldo - $giaInDelega);
                $messaggi[] = sprintf('Il versamento 6099 del quarto trimestre (€ %s) è già in una delega: registrata solo la differenza del saldo.', number_format($giaInDelega, 2, ',', '.'));
            } else {
                $archivio->registra($dichiarazione->soggetto, LiquidazioneIva::ORIGINE, $riferimentoQuarto, []);
                $messaggi[] = 'Il versamento 6099 della liquidazione del quarto trimestre è sostituito dal saldo della dichiarazione.';
            }
        }
        $versamenti = [];
        $rateazione = new RateazioneService($this->dataMapper);
        foreach ((new VersamentoModel($this->dataMapper))->getGenerati($dichiarazione->soggetto, Dichiarazione::ORIGINE, $dichiarazione->getRiferimento()) as $precedente) {
            if ($precedente->pianoRateazione !== null) {
                $blocco = $rateazione->getMotivoBlocco($precedente->pianoRateazione);
                if ($blocco !== null) {
                    return [...$messaggi, 'Il saldo è già rateizzato e il piano non si può più modificare (' . $blocco . '): nessuna nuova registrazione.'];
                }
                $rateazione->elimina($precedente->pianoRateazione);
            }
        }
        $differito = $dichiarazione->modalitaSaldo === ModalitaSaldo::differito;
        if ($saldo > self::IMPORTO_MINIMO_SALDO) {
            $versamento = new Versamento($this->dataMapper);
            $versamento->scadenza = Calendario::termineFiscale($differito ? self::getDataDifferimento($dichiarazione) : new SismaDate(($dichiarazione->anno + 1) . '-03-16'));
            if ($differito) {
                $maggiorazione = self::getMaggiorazione($dichiarazione);
                $messaggi[] = sprintf('Saldo differito con la maggiorazione del %s%%.', rtrim(rtrim(number_format(($maggiorazione - 1) * 100, 4, ',', '.'), '0'), ','));
                $saldo = round($saldo * $maggiorazione, 2);
            }
            $versamento->sezione = SezioneF24::erario;
            $versamento->codiceTributo = (new CodiceTributoModel($this->dataMapper))->getByCodice(SezioneCodiceTributo::erario, '6099')
                    ?? throw new \InvalidArgumentException('Codice tributo 6099 assente nelle tabelle di Deleghe F24.');
            $versamento->rateazione = '0101';
            $versamento->annoRiferimento = $dichiarazione->anno;
            $versamento->importoDebito = $saldo;
            $versamento->descrizione = 'Saldo IVA ' . $dichiarazione->anno;
            $versamenti[] = $versamento;
        }
        $esito = $archivio->registra($dichiarazione->soggetto, Dichiarazione::ORIGINE, $dichiarazione->getRiferimento(), $versamenti);
        $messaggi[] = count($versamenti) === 0 ? 'Nessun saldo IVA da versare.'
                : sprintf('Saldo IVA %d di € %s registrato per il %s.', $dichiarazione->anno, number_format($saldo, 2, ',', '.'), $versamenti[0]->scadenza->format('d/m/Y'));
        $numeroRate = (int) $dichiarazione->numeroRate;
        $aRate = ($dichiarazione->modalitaSaldo === ModalitaSaldo::rate) || ($differito && ($numeroRate > 1));
        if ($aRate && (count($esito->versamenti) > 0)) {
            if ($numeroRate < 2) {
                return [...$messaggi, ...$esito->differenze, 'Indicare il numero delle rate (almeno 2): saldo registrato in unica soluzione.'];
            }
            $originale = $esito->versamenti[0];
            $date = CalcoloRateazione::getDateProposte(CalcoloRateazione::getPrimaRata($originale->scadenza), $numeroRate);
            $rateazione->crea($dichiarazione->soggetto, [$originale], $date, 'Saldo IVA ' . $dichiarazione->anno . ' in ' . $numeroRate . ' rate');
            $messaggi[] = sprintf('Saldo ripartito in %d rate mensili dal %s, con gli interessi (piano di rateazione in Deleghe).', $numeroRate, $date[0]->format('d/m/Y'));
        }
        return array_merge($messaggi, $this->getDifferenze($esito));
    }

    /**
     * Registra (o aggiorna, se non ancora utilizzato) il credito da
     * compensare alla presentazione.
     *
     * @return string[] messaggi per l'utente
     */
    public function registraCredito(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri): array
    {
        // controllante dell'IVA di gruppo: anche il credito del gruppo da compensare (VY5)
        $importo = (float) ($quadri->getImporto('VX005001', 1) + $quadri->getImporto('VY005001', 1));
        $creditoModel = new CreditoModel($this->dataMapper);
        $credito = $this->getCredito($dichiarazione);
        if (($credito !== null) && ($creditoModel->getUtilizzato($credito) > 0)) {
            return abs($credito->importo - $importo) < 0.005 ? [] : [sprintf('Il credito IVA %d già registrato (€ %s) è stato utilizzato in F24: non è aggiornato all\'importo della dichiarazione (€ %s).',
                        $dichiarazione->anno, number_format($credito->importo, 2, ',', '.'), number_format($importo, 2, ',', '.'))];
        }
        $daPresentare = ($importo > self::LIMITE_CREDITO_LIBERO) && ($dichiarazione->dataPresentazione === null);
        if (($importo <= 0) || $daPresentare) {
            if (($credito !== null) && ($credito->origine === Dichiarazione::ORIGINE)) {
                $this->dataMapper->delete($credito);
            }
            return $daPresentare ? [sprintf('Credito IVA %d di € %s oltre 5.000 euro: sarà registrato in Deleghe alla presentazione (compensabile dal decimo giorno successivo).',
                                $dichiarazione->anno, number_format($importo, 2, ',', '.'))] : [];
        }
        $credito ??= new Credito($this->dataMapper);
        $credito->studio = $dichiarazione->studio;
        $credito->soggetto = $dichiarazione->soggetto;
        $credito->sezione = SezioneF24::erario;
        $credito->codiceTributo = (new CodiceTributoModel($this->dataMapper))->getByCodice(SezioneCodiceTributo::erario, '6099')
                ?? throw new \InvalidArgumentException('Codice tributo 6099 assente nelle tabelle di Deleghe F24.');
        $credito->rateazione = '0101';
        $credito->annoRiferimento = $dichiarazione->anno;
        $credito->importo = $importo;
        $credito->utilizzabileDal = self::getUtilizzabileDal($dichiarazione, $importo);
        $credito->descrizione = 'Credito IVA ' . $dichiarazione->anno . ' da dichiarazione annuale';
        $credito->origine = Dichiarazione::ORIGINE;
        $credito->riferimentoOrigine = $dichiarazione->getRiferimento();
        $this->dataMapper->save($credito);
        return [sprintf('Credito IVA %d di € %s registrato in Deleghe, compensabile dal %s.', $dichiarazione->anno, number_format($importo, 2, ',', '.'), $credito->utilizzabileDal->format('d/m/Y'))];
    }

    /**
     * Elimina il credito registrato se non ancora utilizzato (dichiarazione
     * riportata in bozza).
     *
     * @return string[] messaggi per l'utente
     */
    public function annullaCredito(Dichiarazione $dichiarazione): array
    {
        $credito = $this->getCredito($dichiarazione);
        if (($credito === null) || ($credito->origine !== Dichiarazione::ORIGINE)) {
            return [];
        }
        if ((new CreditoModel($this->dataMapper))->getUtilizzato($credito) > 0) {
            return ['Il credito IVA registrato in Deleghe è già stato utilizzato e resta nell\'archivio.'];
        }
        $this->dataMapper->delete($credito);
        return ['Credito IVA eliminato dall\'archivio di Deleghe.'];
    }

    /**
     * Credito registrato dalla dichiarazione o, in mancanza, il credito IVA
     * dell'anno (6099) inserito a mano in Deleghe, che la dichiarazione
     * adotta invece di duplicarlo.
     */
    public function getCredito(Dichiarazione $dichiarazione): ?Credito
    {
        $manuale = null;
        foreach ((new CreditoModel($this->dataMapper))->getElenco($dichiarazione->soggetto) as $credito) {
            if (($credito->origine === Dichiarazione::ORIGINE) && ($credito->riferimentoOrigine === $dichiarazione->getRiferimento())) {
                return $credito;
            }
            if (($credito->origine === null) && ($credito->codiceTributo?->codice === '6099') && ($credito->annoRiferimento === $dichiarazione->anno)) {
                $manuale ??= $credito;
            }
        }
        return $manuale;
    }

    /**
     * Data del versamento differito: quella indicata o il 30 giugno
     * dell'anno successivo (termine del saldo delle imposte sui redditi,
     * art. 17, c. 1, D.P.R. 435/2001).
     */
    public static function getDataDifferimento(Dichiarazione $dichiarazione): SismaDate
    {
        return $dichiarazione->dataDifferimento ?? new SismaDate(($dichiarazione->anno + 1) . '-06-30');
    }

    /**
     * Coefficiente del saldo differito: 0,40% per mese o frazione di mese
     * dal 16 marzo alla data del versamento, non oltre il 30 giugno; con il
     * versamento nei 30 giorni successivi al termine un ulteriore 0,40%
     * sull'importo maggiorato.
     */
    public static function getMaggiorazione(Dichiarazione $dichiarazione): float
    {
        $inizio = new \DateTimeImmutable(($dichiarazione->anno + 1) . '-03-16');
        $data = new \DateTimeImmutable(min(self::getDataDifferimento($dichiarazione)->format('Y-m-d'), ($dichiarazione->anno + 1) . '-06-30'));
        $mesi = 0;
        for ($giorno = $inizio; $giorno < $data; $giorno = $giorno->modify('+1 month')) {
            $mesi++;
        }
        $coefficiente = 1 + 0.004 * $mesi;
        return round($dichiarazione->maggiorazioneUlteriore ? $coefficiente * 1.004 : $coefficiente, 6);
    }

    /**
     * Percentuale di detrazione dell'anno come pro-rata provvisorio
     * dell'anno successivo in Contabilità (non sostituisce quello inserito
     * a mano).
     *
     * @return string[] messaggi per l'utente
     */
    public function registraProrata(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri): array
    {
        // attività separate: un pro-rata per attività, dal modulo dell'attività
        $attivita = (new AttivitaSeparate($this->dataMapper))->getAttivita($dichiarazione->soggetto, $dichiarazione->anno);
        if (count($attivita) === 0) {
            return $this->registraProrataModulo($dichiarazione, $quadri, 1, null);
        }
        $avvisi = $this->registraProrataModulo($dichiarazione, $quadri, 1, reset($attivita));
        foreach ((new ModuloDichiarazioneModel($this->dataMapper))->getModuli($dichiarazione) as $modulo) {
            if ($modulo->attivitaIva !== null) {
                array_push($avvisi, ...$this->registraProrataModulo($dichiarazione, $quadri, $modulo->numero, $modulo->attivitaIva));
            }
        }
        return $avvisi;
    }

    /**
     * @return string[]
     */
    private function registraProrataModulo(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri, int $numero, ?AttivitaIva $attivita): array
    {
        $model = new ProrataIvaModel($this->dataMapper);
        $anno = $dichiarazione->anno + 1;
        $prorata = $model->getPerAnno($dichiarazione->soggetto, $anno, $attivita);
        $etichetta = $attivita === null ? '' : ' dell\'attività ' . $attivita->getEtichetta();
        if ($quadri->get('VF030003', $numero) === null) {
            if (($prorata !== null) && ($prorata->origine === Dichiarazione::ORIGINE)) {
                $this->dataMapper->delete($prorata);
                return ['Pro-rata provvisorio ' . $anno . $etichetta . ' tolto da Contabilità (la dichiarazione non ha più operazioni esenti).'];
            }
            return [];
        }
        $percentuale = $quadri->getImporto('VF034009', $numero);
        if (($prorata !== null) && ($prorata->origine === null)) {
            return $prorata->percentuale === $percentuale ? []
                    : [sprintf('Pro-rata provvisorio %d%s inserito a mano in Contabilità (%d%%) diverso da quello della dichiarazione (%d%%): non modificato.', $anno, $etichetta, $prorata->percentuale, $percentuale)];
        }
        $prorata ??= new ProrataIva($this->dataMapper);
        $prorata->studio = $dichiarazione->studio;
        $prorata->soggetto = $dichiarazione->soggetto;
        $prorata->anno = $anno;
        $prorata->attivitaIva = $attivita;
        $prorata->percentuale = $percentuale;
        $prorata->origine = Dichiarazione::ORIGINE;
        $prorata->note = 'Percentuale di detrazione della dichiarazione IVA ' . $dichiarazione->getAnnoModello();
        $this->dataMapper->save($prorata);
        return [sprintf('Pro-rata provvisorio %d%s del %d%% registrato in Contabilità per le liquidazioni periodiche.', $anno, $etichetta, $percentuale)];
    }

    public static function getUtilizzabileDal(Dichiarazione $dichiarazione, float $importo): SismaDate
    {
        if (($importo <= self::LIMITE_CREDITO_LIBERO) || ($dichiarazione->dataPresentazione === null)) {
            return new SismaDate(($dichiarazione->anno + 1) . '-01-01');
        }
        return new SismaDate((new \DateTimeImmutable($dichiarazione->dataPresentazione->format('Y-m-d')))->modify('+10 days')->format('Y-m-d'));
    }

    /**
     * @return string[]
     */
    private function getDifferenze(EsitoRegistrazione $esito): array
    {
        return $esito->differenze;
    }
}
