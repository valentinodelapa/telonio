<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Models\LegameModel;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Contabilita\Application\Entities\GruppoIva;
use Contabilita\Application\Entities\LiquidazioneGruppoIva;
use Contabilita\Application\Entities\LiquidazioneIva;
use Contabilita\Application\Entities\PartecipanteGruppoIva;
use Contabilita\Application\Models\AccontoIvaModel;
use Contabilita\Application\Models\GruppoIvaModel;
use Contabilita\Application\Models\LiquidazioneGruppoIvaModel;
use Contabilita\Application\Models\LiquidazioneIvaModel;
use Contabilita\Application\Models\PartecipanteGruppoIvaModel;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Models\CodiceTributoModel;
use Deleghe\Application\Services\ArchivioVersamenti;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Liquidazione periodica dell'IVA di gruppo (art. 73, c. 3, D.P.R.
 * 633/1972; D.M. 13 dicembre 1979): la controllante somma i debiti (con
 * gli interessi dei trimestrali) e i crediti trasferiti dalle società
 * partecipanti, compresa se stessa, nel loro periodo di controllo, riporta
 * il credito e il debito non versato del gruppo, scomputa il credito di
 * gruppo dell'anno precedente indicato e, nell'ultimo periodo, l'acconto
 * del gruppo, e registra il versamento nell'archivio di Deleghe F24
 * (riferimento iva-gruppo-<anno>-<periodo>).
 *
 * Il gruppo liquida mensilmente se almeno una società è mensile: i saldi
 * delle società trimestrali entrano nel mese di fine trimestre, salvo il
 * quarto trimestre, che si chiude con le dichiarazioni annuali (VX7 e
 * VX8 delle società, prospetto IVA 26 PR della controllante).
 *
 * Il requisito del controllo (più del 50% del capitale posseduto
 * ininterrottamente dal 1° luglio dell'anno precedente) si verifica sui
 * legami "socio" dell'Anagrafica, quando la quota è indicata.
 */
class LiquidazioneGruppo
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Gruppo a cui il soggetto trasferisce la liquidazione del periodo:
     * la controllante per tutto l'anno dell'opzione, la controllata nel
     * periodo di controllo.
     */
    public function getGruppoDelPeriodo(Soggetto $soggetto, int $anno, PeriodicitaIva $periodicita, int $periodo): ?GruppoIva
    {
        $gruppo = (new GruppoIvaModel($this->dataMapper))->getPerControllante($soggetto, $anno);
        if ($gruppo !== null) {
            return $gruppo;
        }
        $partecipante = (new PartecipanteGruppoIvaModel($this->dataMapper))->getPerControllata($soggetto, $anno);
        return ($partecipante !== null) && $partecipante->isPeriodoControllato($anno, $periodicita, $periodo) ? $partecipante->gruppoIva : null;
    }

    /**
     * Società partecipanti nell'anno: la controllante (senza partecipazione)
     * e le controllate.
     *
     * @return array<int, array{soggetto: Soggetto, partecipante: ?PartecipanteGruppoIva}>
     */
    public function getSocieta(GruppoIva $gruppo, int $anno): array
    {
        $societa = [['soggetto' => $gruppo->controllante, 'partecipante' => null]];
        foreach ((new PartecipanteGruppoIvaModel($this->dataMapper))->getPartecipantiNel($gruppo, $anno) as $partecipante) {
            $societa[] = ['soggetto' => $partecipante->soggetto, 'partecipante' => $partecipante];
        }
        return $societa;
    }

    /**
     * Periodicità della liquidazione di gruppo: mensile se almeno una
     * società liquida mensilmente.
     */
    public function getPeriodicita(GruppoIva $gruppo, int $anno): PeriodicitaIva
    {
        $regime = new RegimeIva($this->dataMapper);
        foreach ($this->getSocieta($gruppo, $anno) as ['soggetto' => $soggetto]) {
            if ($regime->getPeriodicita($soggetto, $anno) === PeriodicitaIva::mensile) {
                return PeriodicitaIva::mensile;
            }
        }
        return PeriodicitaIva::trimestrale;
    }

    /**
     * Requisiti del controllo non verificati sui legami "socio" con quota
     * dell'Anagrafica (avvisi).
     *
     * @return string[]
     */
    public function verificaControllo(GruppoIva $gruppo, int $anno): array
    {
        $avvisi = [];
        $riferimento = new SismaDate(($anno - 1) . '-07-01');
        $legami = new LegameModel($this->dataMapper);
        foreach ((new PartecipanteGruppoIvaModel($this->dataMapper))->getPartecipantiNel($gruppo, $anno) as $partecipante) {
            $detentore = $partecipante->detentore ?? $gruppo->controllante;
            $quota = null;
            foreach ($legami->getCollegati($partecipante->soggetto) as $legame) {
                if (($legame->ruolo === RuoloLegame::socio) && ($legame->soggettoCollegato->id === $detentore->id) && ($legame->quotaPartecipazione !== null)
                        && ($legame->validoDal->format('Y-m-d') <= $riferimento->format('Y-m-d')) && $legame->isVigenteAl(new SismaDate($anno . '-12-31'))) {
                    $quota = max($quota ?? 0.0, $legame->quotaPartecipazione);
                }
            }
            if ($quota === null) {
                $avvisi[] = sprintf('%s: nell\'Anagrafica manca il legame "socio" con la quota di %s posseduta dal 1° luglio %d: requisito del controllo da verificare.',
                        $partecipante->soggetto->nominativo, $detentore->nominativo, $anno - 1);
            } elseif ($quota <= 50) {
                $avvisi[] = sprintf('%s: %s ne possiede il %s%%, non più del 50%%: la società non può partecipare alla liquidazione di gruppo (art. 73, c. 3).',
                        $partecipante->soggetto->nominativo, $detentore->nominativo, number_format($quota, 2, ',', '.'));
            }
        }
        return $avvisi;
    }

    /**
     * Liquidazione di gruppo del periodo; nel mese di fine trimestre i
     * trimestri delle società trimestrali sono anticipati al mese o, senza
     * anticipazione, liquidati a parte (elaboraTrimestre).
     *
     * @throws \DomainException se il gruppo non è attivo nell'anno o il periodo non è valido
     * @throws \InvalidArgumentException se il versamento non supera i controlli di Deleghe
     */
    public function elabora(GruppoIva $gruppo, int $anno, int $periodo, float $creditoAnnoPrecedente = 0.0, bool $anticipaTrimestre = true): LiquidazioneGruppoIva
    {
        if ($gruppo->isAttivoNel($anno) === false) {
            throw new \DomainException('Il gruppo IVA non è attivo nel ' . $anno . '.');
        }
        $periodicita = $this->getPeriodicita($gruppo, $anno);
        if (($periodo < 1) || ($periodo > PeriodiIva::getNumeroPeriodi($periodicita))) {
            throw new \DomainException('Periodo non valido per la periodicità ' . mb_strtolower($periodicita->getLabel()) . ' del gruppo.');
        }
        if ($anticipaTrimestre && ($periodicita === PeriodicitaIva::mensile) && ($periodo % 3 === 0)
                && ((new LiquidazioneGruppoIvaModel($this->dataMapper))->getPerPeriodo($gruppo, $anno, intdiv($periodo, 3), true) !== null)) {
            throw new \DomainException('Il ' . intdiv($periodo, 3) . '° trimestre delle società trimestrali ha una liquidazione di gruppo separata: eliminarla per anticiparlo al mese.');
        }
        return $this->elaboraPeriodo($gruppo, $anno, $periodicita, $periodo, false, $anticipaTrimestre, $creditoAnnoPrecedente);
    }

    /**
     * Liquidazione di gruppo che chiude il trimestre: vi si annota il
     * credito chiesto dalla controllante con il modello IVA TR del gruppo,
     * che non si riporta al periodo successivo.
     */
    public function getLiquidazioneTrimestre(GruppoIva $gruppo, int $anno, int $trimestre): ?LiquidazioneGruppoIva
    {
        $model = new LiquidazioneGruppoIvaModel($this->dataMapper);
        return $this->getPeriodicita($gruppo, $anno) === PeriodicitaIva::mensile
                ? ($model->getPerPeriodo($gruppo, $anno, $trimestre, true) ?? $model->getPerPeriodo($gruppo, $anno, $trimestre * 3))
                : $model->getPerPeriodo($gruppo, $anno, $trimestre);
    }

    /**
     * Liquidazione di gruppo separata del trimestre delle società
     * trimestrali (gruppo mensile, mese di fine trimestre elaborato senza
     * anticiparlo).
     *
     * @throws \DomainException se il gruppo non ha società trimestrali o il mese non è elaborato senza anticipare il trimestre
     */
    public function elaboraTrimestre(GruppoIva $gruppo, int $anno, int $trimestre, float $creditoAnnoPrecedente = 0.0): LiquidazioneGruppoIva
    {
        if (($trimestre < 1) || ($trimestre > 3)) {
            throw new \DomainException('Trimestre non valido: il quarto trimestre delle società trimestrali si chiude con le dichiarazioni annuali.');
        }
        if (($this->getPeriodicita($gruppo, $anno) !== PeriodicitaIva::mensile) || ($this->hasTrimestrali($gruppo, $anno) === false)) {
            throw new \DomainException('Il gruppo non ha società trimestrali in una liquidazione di gruppo mensile.');
        }
        $mese = (new LiquidazioneGruppoIvaModel($this->dataMapper))->getPerPeriodo($gruppo, $anno, $trimestre * 3);
        if (($mese === null) || ($mese->trimestreAnticipato !== null)) {
            throw new \DomainException('Elaborare prima la liquidazione di gruppo di ' . LiquidazioneIva::descriviPeriodo(PeriodicitaIva::mensile, $anno, $trimestre * 3) . ' senza anticipare il trimestre.');
        }
        return $this->elaboraPeriodo($gruppo, $anno, PeriodicitaIva::trimestrale, $trimestre, true, false, $creditoAnnoPrecedente);
    }

    /**
     * Società trimestrali (o con attività separate trimestrali) in un
     * gruppo che liquida mensilmente.
     */
    public function hasTrimestrali(GruppoIva $gruppo, int $anno): bool
    {
        $regime = new RegimeIva($this->dataMapper);
        $elaborazione = new ElaborazioneLiquidazione($this->dataMapper);
        foreach ($this->getSocieta($gruppo, $anno) as ['soggetto' => $soggetto]) {
            if (($regime->getPeriodicita($soggetto, $anno) === PeriodicitaIva::trimestrale) || ($elaborazione->getAttivitaTrimestrali($soggetto, $anno) !== [])) {
                return true;
            }
        }
        return false;
    }

    private function elaboraPeriodo(GruppoIva $gruppo, int $anno, PeriodicitaIva $periodicita, int $periodo, bool $separato, bool $anticipaTrimestre, float $creditoAnnoPrecedente): LiquidazioneGruppoIva
    {
        $model = new LiquidazioneGruppoIvaModel($this->dataMapper);
        $liquidazione = $model->getPerPeriodo($gruppo, $anno, $periodo, $separato) ?? new LiquidazioneGruppoIva($this->dataMapper);
        $liquidazione->studio = $gruppo->studio;
        $liquidazione->gruppoIva = $gruppo;
        $liquidazione->anno = $anno;
        $liquidazione->periodo = $periodo;
        $liquidazione->periodicita = $periodicita;
        $liquidazione->trimestreSeparato = $separato;
        $liquidazione->dataElaborazione = new SismaDateTime();
        $liquidazione->trimestreAnticipato = null;
        $liquidazione->debitiTrasferiti = $liquidazione->creditiTrasferiti = $liquidazione->interessiTrasferiti = 0.0;
        $avvisi = $this->verificaControllo($gruppo, $anno);
        foreach ($this->getSocieta($gruppo, $anno) as ['soggetto' => $soggetto, 'partecipante' => $partecipante]) {
            foreach ($this->getLiquidazioniDaSommare($soggetto, $anno, $liquidazione, $anticipaTrimestre, $avvisi) as $propria) {
                if (($partecipante !== null) && ($partecipante->isPeriodoControllato($anno, $propria->periodicita, $propria->periodo) === false)) {
                    continue;
                }
                if (($propria->isTrasferita() === false) || ($propria->gruppoIva->id !== $gruppo->id)) {
                    $avvisi[] = $soggetto->nominativo . ': liquidazione di ' . $propria->getDescrizionePeriodo() . ' non trasferita al gruppo (rielaborarla), esclusa.';
                    continue;
                }
                $saldo = $propria->getSaldoTrasferito();
                if ($saldo >= 0) {
                    $liquidazione->debitiTrasferiti += $saldo;
                    $liquidazione->interessiTrasferiti += $propria->interessi;
                } else {
                    $liquidazione->creditiTrasferiti -= $saldo;
                }
            }
        }
        $liquidazione->debitiTrasferiti = round($liquidazione->debitiTrasferiti, 2);
        $liquidazione->creditiTrasferiti = round($liquidazione->creditiTrasferiti, 2);
        $liquidazione->interessiTrasferiti = round($liquidazione->interessiTrasferiti, 2);
        // sequenza dei riporti: mese, mese, mese, trimestre separato, mese successivo
        if ($separato) {
            $precedente = $model->getPerPeriodo($gruppo, $anno, $periodo * 3);
        } else {
            $precedente = $periodo > 1 ? $model->getPerPeriodo($gruppo, $anno, $periodo - 1) : null;
            if (($periodicita === PeriodicitaIva::mensile) && ($periodo > 1) && (($periodo - 1) % 3 === 0)) {
                $precedente = $model->getPerPeriodo($gruppo, $anno, intdiv($periodo - 1, 3), true) ?? $precedente;
            }
        }
        if (($separato || ($periodo > 1)) && ($precedente === null)) {
            $avvisi[] = 'Liquidazione di gruppo del periodo precedente non elaborata: credito e debito da riportare non considerati.';
        }
        $liquidazione->creditoPrecedente = $precedente?->getCreditoRiportato() ?? 0.0;
        $liquidazione->debitoPrecedente = $precedente?->debitoRinviato ?? 0.0;
        $liquidazione->creditoAnnoPrecedente = round(max(0.0, $creditoAnnoPrecedente), 2);
        $liquidazione->acconto = 0.0;
        if (PeriodiIva::isUltimoPeriodo($periodicita, $periodo) && ($separato === false)) {
            $acconto = (new AccontoIvaModel($this->dataMapper))->getPerAnno($gruppo->controllante, $anno, $gruppo);
            $liquidazione->acconto = ($acconto !== null) && $acconto->isDovuto() ? $acconto->importo : 0.0;
        }
        $this->determinaImporti($liquidazione);
        if (($periodicita === PeriodicitaIva::trimestrale) && ($periodo === 4)) {
            $avvisi[] = 'Quarto trimestre: il gruppo lo versa con il saldo del prospetto IVA 26 PR entro il 16 marzo.';
        }
        $liquidazione->scadenza = PeriodiIva::getScadenza($periodicita, $anno, $periodo);
        $primaTransazione = $this->dataMapper->startTransaction();
        $this->dataMapper->save($liquidazione);
        $esito = (new ArchivioVersamenti($this->dataMapper))->registra($gruppo->controllante, GruppoIva::ORIGINE, $liquidazione->getRiferimentoVersamento(), $this->preparaVersamenti($liquidazione));
        $liquidazione->differenze = count($esito->differenze) > 0 ? implode("\n", $esito->differenze) : null;
        $liquidazione->avvisi = count($avvisi) > 0 ? implode("\n", $avvisi) : null;
        $this->dataMapper->save($liquidazione);
        $this->dataMapper->commitTransaction($primaTransazione);
        return $liquidazione;
    }

    /**
     * Liquidazioni della società comprese nella liquidazione di gruppo:
     * quella dello stesso periodo, il trimestre delle società trimestrali
     * nel mese di fine trimestre se anticipato (salvo il quarto), il
     * trimestre separato nella liquidazione separata del gruppo.
     *
     * @param string[] $avvisi
     * @return LiquidazioneIva[]
     */
    private function getLiquidazioniDaSommare(Soggetto $soggetto, int $anno, LiquidazioneGruppoIva $gruppo, bool $anticipaTrimestre, array &$avvisi): array
    {
        $periodicita = (new RegimeIva($this->dataMapper))->getPeriodicita($soggetto, $anno);
        $liquidazioni = new LiquidazioneIvaModel($this->dataMapper);
        $periodo = $gruppo->periodo;
        $richieste = [];
        if ($gruppo->trimestreSeparato) {
            $richieste[] = $periodicita === PeriodicitaIva::trimestrale ? [$periodo, false] : [$periodo, true];
        } elseif ($gruppo->periodicita === $periodicita) {
            $richieste[] = [$periodo, false];
            if (($periodicita === PeriodicitaIva::mensile) && ($periodo % 3 === 0) && ($periodo < 12) && $anticipaTrimestre) {
                // attività separate trimestrali della società liquidate a parte
                $separata = $liquidazioni->getPerPeriodo($soggetto, $anno, intdiv($periodo, 3), true);
                if ($separata !== null) {
                    $gruppo->trimestreAnticipato = intdiv($periodo, 3);
                    $richieste[] = [intdiv($periodo, 3), true];
                }
            }
        } elseif (($periodo % 3 === 0) && ($periodo < 12) && $anticipaTrimestre) {
            $gruppo->trimestreAnticipato = intdiv($periodo, 3);
            $richieste[] = [intdiv($periodo, 3), false];
        }
        $elenco = [];
        foreach ($richieste as [$numero, $separata]) {
            if (($periodicita === PeriodicitaIva::trimestrale) && ($numero === 4)) {
                continue;
            }
            $liquidazione = $liquidazioni->getPerPeriodo($soggetto, $anno, $numero, $separata);
            if ($liquidazione === null) {
                if ($separata === false) {
                    $avvisi[] = $soggetto->nominativo . ': liquidazione di ' . LiquidazioneIva::descriviPeriodo($separata ? PeriodicitaIva::trimestrale : $periodicita, $anno, $numero) . ' non elaborata, esclusa.';
                }
                continue;
            }
            $elenco[] = $liquidazione;
        }
        return $elenco;
    }

    /**
     * Importo dovuto o credito del gruppo; il debito non superiore alla
     * soglia si rinvia (gli interessi dei trimestrali sono già nei debiti
     * trasferiti).
     */
    public function determinaImporti(LiquidazioneGruppoIva $liquidazione): void
    {
        $saldo = round($liquidazione->getSaldoTrasferito() + $liquidazione->debitoPrecedente - $liquidazione->creditoPrecedente - $liquidazione->creditoAnnoPrecedente - $liquidazione->acconto, 2);
        $liquidazione->importoDovuto = $liquidazione->creditoFinale = $liquidazione->debitoRinviato = 0.0;
        if ($saldo <= 0) {
            $liquidazione->creditoFinale = -$saldo;
        } elseif (PeriodiIva::isRinviabile($liquidazione->periodicita, $liquidazione->periodo, $saldo, $liquidazione->anno)) {
            $liquidazione->debitoRinviato = $saldo;
        } else {
            $liquidazione->importoDovuto = $saldo;
        }
    }

    /**
     * @return Versamento[]
     */
    private function preparaVersamenti(LiquidazioneGruppoIva $liquidazione): array
    {
        if ($liquidazione->importoDovuto <= 0) {
            return [];
        }
        $codice = PeriodiIva::getCodiceTributo($liquidazione->periodicita, $liquidazione->periodo);
        $versamento = new Versamento($this->dataMapper);
        $versamento->scadenza = $liquidazione->scadenza;
        $versamento->sezione = SezioneF24::erario;
        $versamento->codiceTributo = (new CodiceTributoModel($this->dataMapper))->getByCodice(SezioneCodiceTributo::erario, $codice)
                ?? throw new \InvalidArgumentException('Codice tributo ' . $codice . ' assente nelle tabelle di Deleghe F24.');
        $versamento->annoRiferimento = $liquidazione->anno;
        if ($codice === '6099') {
            $versamento->rateazione = '0101';
        }
        $versamento->importoDebito = $liquidazione->importoDovuto;
        $versamento->descrizione = 'IVA di gruppo ' . $liquidazione->getDescrizionePeriodo();
        return [$versamento];
    }

    /**
     * @return string[] differenze con i versamenti in deleghe non modificabili
     */
    public function elimina(LiquidazioneGruppoIva $liquidazione): array
    {
        $primaTransazione = $this->dataMapper->startTransaction();
        $esito = (new ArchivioVersamenti($this->dataMapper))->registra($liquidazione->gruppoIva->controllante, GruppoIva::ORIGINE, $liquidazione->getRiferimentoVersamento(), []);
        $this->dataMapper->delete($liquidazione);
        $this->dataMapper->commitTransaction($primaTransazione);
        return $esito->differenze;
    }
}
