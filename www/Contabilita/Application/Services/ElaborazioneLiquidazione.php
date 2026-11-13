<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Contabilita\Application\Entities\AttivitaIva;
use Contabilita\Application\Entities\LiquidazioneAttivita;
use Contabilita\Application\Entities\LiquidazioneIva;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Models\AccontoIvaModel;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\EsercizioContabileModel;
use Contabilita\Application\Models\LiquidazioneAttivitaModel;
use Contabilita\Application\Models\LiquidazioneIvaModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Models\RegistrazioneContabileModel;
use Contabilita\Application\Services\StampaDefinitiva\StampeDefinitive;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Models\CodiceTributoModel;
use Deleghe\Application\Services\ArchivioVersamenti;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Elaborazione della liquidazione periodica: calcolo dell'IVA del periodo
 * dai registri, riporto del credito e del debito non versato del periodo
 * precedente, compensazione del credito annuale indicato, scomputo
 * dell'acconto nell'ultimo periodo, interessi dei trimestrali (vedi
 * PeriodiIva), registrazione del versamento nell'archivio di Deleghe F24
 * (origine "Contabilita", riferimento iva-<anno>-<periodo>, sostituito a
 * ogni elaborazione) e, a richiesta, del giroconto in prima nota (IVA
 * delle vendite e degli acquisti a Erario c/IVA, con l'acconto e, per i
 * corrispettivi ventilati, l'imposta stornata dai ricavi). L'IVA esigibile
 * comprende quella dei corrispettivi ventilati (VentilazioneCorrispettivi).
 *
 * Il credito risultante dalla dichiarazione annuale dell'anno precedente
 * non si riporta da solo: si indica l'importo compensato nel periodo
 * (compensazione "verticale"). Allo stesso modo si indicano l'IVA già
 * versata con F24 elementi identificativi sulle prime cessioni interne di
 * auto di provenienza UE (D.L. 262/2006) e i crediti d'imposta particolari
 * scomputati dal versamento (non quelli compensati nel modello F24): righi
 * VP10 e VP11 della comunicazione delle liquidazioni periodiche. I crediti
 * d'imposta non superano l'IVA da versare e non si usano nel quarto
 * trimestre dei trimestrali, che si chiude con la dichiarazione annuale.
 *
 * Con le attività separate (art. 36) la liquidazione somma quelle delle
 * attività (LiquidazioneAttivita), ciascuna con le sue righe, le quote
 * degli acquisti promiscui e il suo pro-rata; le attività trimestrali di
 * un'azienda che liquida mensilmente entrano nel mese di fine trimestre
 * (liquidazione anticipata, scelta trimestre per trimestre) con gli
 * interessi dell'1% sull'IVA dovuta del trimestre, oppure hanno una
 * liquidazione del trimestre separata (elaboraTrimestre: codici
 * 6031-6033, termine dei trimestrali), che segue il mese di fine
 * trimestre nella sequenza dei riporti (mese, mese, mese, trimestre).
 *
 * Per le società che partecipano alla liquidazione IVA di gruppo (art.
 * 73, c. 3) nel periodo di controllo il risultato del periodo è
 * trasferito alla controllante (LiquidazioneGruppo): niente riporto del
 * credito o del debito, credito dell'anno precedente, acconto né
 * versamento in Deleghe; il giroconto va al conto dell'IVA di gruppo.
 */
class ElaborazioneLiquidazione
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @throws \DomainException se l'azienda non liquida l'IVA nell'anno o i crediti d'imposta non sono scomputabili
     * @throws \InvalidArgumentException se il versamento non supera i controlli di Deleghe
     */
    public function elabora(Soggetto $soggetto, int $anno, int $periodo, float $creditoAnnoPrecedente = 0.0, bool $giroconto = true, float $versamentiAuto = 0.0, float $creditiImposta = 0.0,
            bool $anticipaTrimestre = true): LiquidazioneIva
    {
        $periodicita = (new RegimeIva($this->dataMapper))->getPeriodicita($soggetto, $anno);
        if (($periodo < 1) || ($periodo > PeriodiIva::getNumeroPeriodi($periodicita))) {
            throw new \DomainException('Periodo non valido per la periodicità ' . mb_strtolower($periodicita->getLabel()) . '.');
        }
        $separata = (new LiquidazioneIvaModel($this->dataMapper))->getPerPeriodo($soggetto, $anno, intdiv($periodo, 3), true);
        if ($anticipaTrimestre && ($periodicita === PeriodicitaIva::mensile) && ($periodo % 3 === 0) && ($separata !== null)) {
            throw new \DomainException('Il ' . intdiv($periodo, 3) . '° trimestre delle attività trimestrali ha una liquidazione separata: eliminarla per anticiparlo al mese.');
        }
        return $this->elaboraPeriodo($soggetto, $anno, $periodicita, $periodo, false, $anticipaTrimestre, $creditoAnnoPrecedente, $giroconto, $versamentiAuto, $creditiImposta);
    }

    /**
     * Liquidazione separata del trimestre delle attività trimestrali di
     * un'azienda che liquida mensilmente, dopo quella del mese di fine
     * trimestre elaborata senza anticiparlo.
     *
     * @throws \DomainException se l'azienda non ha attività trimestrali o il mese non è elaborato senza anticipare il trimestre
     */
    public function elaboraTrimestre(Soggetto $soggetto, int $anno, int $trimestre, float $creditoAnnoPrecedente = 0.0, bool $giroconto = true, float $versamentiAuto = 0.0,
            float $creditiImposta = 0.0): LiquidazioneIva
    {
        if (($trimestre < 1) || ($trimestre > 3)) {
            throw new \DomainException('Trimestre non valido: il quarto trimestre delle attività trimestrali si liquida con la dichiarazione annuale.');
        }
        if ($this->getAttivitaTrimestrali($soggetto, $anno) === []) {
            throw new \DomainException('L\'azienda non ha attività separate trimestrali in un\'azienda che liquida mensilmente.');
        }
        $mese = (new LiquidazioneIvaModel($this->dataMapper))->getPerPeriodo($soggetto, $anno, $trimestre * 3);
        if (($mese === null) || ($mese->trimestreAnticipato !== null)) {
            throw new \DomainException('Elaborare prima ' . LiquidazioneIva::descriviPeriodo(PeriodicitaIva::mensile, $anno, $trimestre * 3) . ' senza anticipare il trimestre.');
        }
        return $this->elaboraPeriodo($soggetto, $anno, PeriodicitaIva::trimestrale, $trimestre, true, false, $creditoAnnoPrecedente, $giroconto, $versamentiAuto, $creditiImposta);
    }

    /**
     * Liquidazione che chiude il trimestre: il trimestre separato delle
     * attività trimestrali, il mese di fine trimestre o il trimestre. Vi si
     * annota il credito chiesto con il modello IVA TR, che non si riporta
     * al periodo successivo (istruzioni della LIPE, rigo VP8).
     */
    public function getLiquidazioneTrimestre(Soggetto $soggetto, int $anno, int $trimestre): ?LiquidazioneIva
    {
        $model = new LiquidazioneIvaModel($this->dataMapper);
        return (new RegimeIva($this->dataMapper))->getPeriodicita($soggetto, $anno) === PeriodicitaIva::mensile
                ? ($model->getPerPeriodo($soggetto, $anno, $trimestre, true) ?? $model->getPerPeriodo($soggetto, $anno, $trimestre * 3))
                : $model->getPerPeriodo($soggetto, $anno, $trimestre);
    }

    /**
     * Attività separate trimestrali di un'azienda che liquida mensilmente.
     *
     * @return AttivitaIva[]
     */
    public function getAttivitaTrimestrali(Soggetto $soggetto, int $anno): array
    {
        $regime = new RegimeIva($this->dataMapper);
        $separate = new AttivitaSeparate($this->dataMapper);
        $attivita = $separate->getAttivita($soggetto, $anno);
        if ((count($attivita) === 0) || ($regime->getPeriodicita($soggetto, $anno) !== PeriodicitaIva::mensile)) {
            return [];
        }
        $azienda = $regime->getPeriodicitaAzienda($soggetto, $anno);
        return array_filter($attivita, fn(AttivitaIva $singola) => $separate->getPeriodicita($singola, $azienda) === PeriodicitaIva::trimestrale);
    }

    private function elaboraPeriodo(Soggetto $soggetto, int $anno, PeriodicitaIva $periodicita, int $periodo, bool $separato, bool $anticipaTrimestre, float $creditoAnnoPrecedente,
            bool $giroconto, float $versamentiAuto, float $creditiImposta): LiquidazioneIva
    {
        if (($versamentiAuto < 0) || ($creditiImposta < 0)) {
            throw new \DomainException('Versamenti auto UE e crediti d\'imposta non possono essere negativi.');
        }
        $model = new LiquidazioneIvaModel($this->dataMapper);
        $dal = PeriodiIva::getInizio($periodicita, $anno, $periodo);
        $al = PeriodiIva::getFine($periodicita, $anno, $periodo);
        $ventilazione = new VentilazioneCorrispettivi($this->dataMapper);
        $ventilata = $ventilazione->calcola($soggetto, $dal, $al);
        $annuale = (PeriodiIva::isUltimoPeriodo($periodicita, $periodo) && ($periodo > 1) && ($separato === false)) ? $ventilazione->calcola($soggetto, new SismaDate($anno . '-01-01'), $al) : $ventilata;
        $liquidazione = $model->getPerPeriodo($soggetto, $anno, $periodo, $separato) ?? new LiquidazioneIva($this->dataMapper);
        $liquidazione->studio = $soggetto->studio;
        $liquidazione->soggetto = $soggetto;
        $liquidazione->anno = $anno;
        $liquidazione->periodo = $periodo;
        $liquidazione->periodicita = $periodicita;
        $liquidazione->trimestreSeparato = $separato;
        $liquidazione->trimestraleSpeciale = ($periodicita === PeriodicitaIva::trimestrale) && ($separato === false) && (new RegimeIva($this->dataMapper))->isTrimestraleSpeciale($soggetto, $anno);
        $liquidazione->dataElaborazione = new SismaDateTime();
        $avvisi = [];
        $dettagli = $this->calcolaAttivita($liquidazione, $ventilazione, $avvisi, $anticipaTrimestre);
        if ($dettagli === null) {
            $risultato = (new CalcoloLiquidazioneIva($this->dataMapper))->calcola($soggetto, $dal, $al);
            $liquidazione->ivaVentilata = $ventilata->imposta;
            $liquidazione->ivaEsigibile = round($risultato->ivaEsigibile + $ventilata->imposta, 2);
            $liquidazione->ivaDetraibile = $risultato->ivaDetraibile;
            array_unshift($avvisi, ...$risultato->avvisi);
        }
        $this->avvisiVentilazione($liquidazione, $ventilata, $annuale, $avvisi);
        $liquidazione->gruppoIva = (new LiquidazioneGruppo($this->dataMapper))->getGruppoDelPeriodo($soggetto, $anno, $periodicita, $periodo);
        // sequenza dei riporti: mese, mese, mese, trimestre separato, mese successivo
        if ($separato) {
            $precedente = $model->getPerPeriodo($soggetto, $anno, $periodo * 3);
        } else {
            $precedente = $periodo > 1 ? $model->getPerPeriodo($soggetto, $anno, $periodo - 1) : null;
            if (($periodicita === PeriodicitaIva::mensile) && ($periodo > 1) && (($periodo - 1) % 3 === 0)) {
                $precedente = $model->getPerPeriodo($soggetto, $anno, intdiv($periodo - 1, 3), true) ?? $precedente;
            }
        }
        if (($separato || ($periodo > 1)) && ($precedente === null)) {
            $avvisi[] = 'Liquidazione del periodo precedente non elaborata: credito e debito da riportare non considerati.';
        }
        // il risultato trasferito al gruppo non si riporta
        $riporto = ($liquidazione->gruppoIva === null) && (($precedente?->isTrasferita() ?? false) === false);
        // il credito chiesto con il modello IVA TR non si riporta (istruzioni della LIPE, rigo VP8)
        $liquidazione->creditoPrecedente = $riporto ? $precedente?->getCreditoRiportato() ?? 0.0 : 0.0;
        $liquidazione->debitoPrecedente = $riporto ? ($precedente?->debitoRinviato ?? 0.0) : 0.0;
        $liquidazione->creditoAnnoPrecedente = round(max(0.0, $creditoAnnoPrecedente), 2);
        if (($liquidazione->gruppoIva !== null) && ($liquidazione->creditoAnnoPrecedente > 0)) {
            $avvisi[] = 'IVA di gruppo: il credito annuale non si compensa nelle liquidazioni trasferite alla controllante (resta della società, art. 73, c. 3), escluso.';
            $liquidazione->creditoAnnoPrecedente = 0.0;
        }
        $liquidazione->versamentiAuto = round($versamentiAuto, 2);
        $liquidazione->creditiImposta = round($creditiImposta, 2);
        if ($creditiImposta > 0) {
            if ($liquidazione->isQuartoTrimestreConDichiarazione()) {
                throw new \DomainException('Quarto trimestre: i crediti d\'imposta si scomputano nel saldo della dichiarazione annuale.');
            }
            $massimo = $this->getMassimoCreditiImposta($liquidazione);
            if ($creditiImposta > $massimo + 0.005) {
                throw new \DomainException(sprintf('I crediti d\'imposta (VP11) non possono superare l\'IVA da versare prima del loro scomputo (€ %s).', number_format($massimo, 2, ',', '.')));
            }
        }
        $primaTransazione = $this->dataMapper->startTransaction();
        $liquidazione->acconto = 0.0;
        if (PeriodiIva::isUltimoPeriodo($periodicita, $periodo) && ($liquidazione->gruppoIva === null) && ($separato === false)) {
            $acconto = (new AccontoIvaModel($this->dataMapper))->getPerAnno($soggetto, $anno);
            $liquidazione->acconto = ($acconto !== null) && $acconto->isDovuto() ? $acconto->importo : 0.0;
        }
        $interessiAnticipati = array_sum(array_map(fn(LiquidazioneAttivita $dettaglio) => $dettaglio->interessi, $dettagli ?? []));
        $this->determinaImporti($liquidazione, $interessiAnticipati);
        if ($liquidazione->trimestraleSpeciale) {
            $avvisi[] = 'Trimestrale per natura dell\'attività (art. 74, c. 4): senza gli interessi dell\'1%' . ($periodo === 4 ? '; il quarto trimestre si versa entro il 16 febbraio con il codice 6034.' : '.');
        } elseif (($periodicita === PeriodicitaIva::trimestrale) && ($periodo === 4)) {
            $avvisi[] = $liquidazione->gruppoIva === null ? 'Quarto trimestre: si versa con il saldo della dichiarazione annuale entro il 16 marzo (codice 6099, con gli interessi dell\'1%).'
                        : 'Quarto trimestre: il saldo si trasferisce al gruppo con la dichiarazione annuale (rigo VX7 o VX8).';
        }
        if ($liquidazione->gruppoIva !== null) {
            $avvisi[] = sprintf('IVA di gruppo: %s di € %s trasferito alla controllante %s, che lo liquida con il gruppo (nessun versamento della società).',
                    $liquidazione->getSaldoTrasferito() >= 0 ? 'debito' : 'credito', number_format(abs($liquidazione->getSaldoTrasferito()), 2, ',', '.'), $liquidazione->gruppoIva->controllante->nominativo);
        }
        $liquidazione->scadenza = PeriodiIva::getScadenza($periodicita, $anno, $periodo, $liquidazione->trimestraleSpeciale);
        $this->dataMapper->save($liquidazione);
        $dettaglioModel = new LiquidazioneAttivitaModel($this->dataMapper);
        foreach ($dettaglioModel->getPerLiquidazione($liquidazione) as $vecchio) {
            $this->dataMapper->delete($vecchio);
        }
        foreach ($dettagli ?? [] as $dettaglio) {
            $dettaglio->liquidazioneIva = $liquidazione;
            $this->dataMapper->save($dettaglio);
        }
        $esito = (new ArchivioVersamenti($this->dataMapper))->registra($soggetto, LiquidazioneIva::ORIGINE, $liquidazione->getRiferimentoVersamento(),
                $liquidazione->gruppoIva === null ? $this->preparaVersamenti($liquidazione) : []);
        $liquidazione->differenze = count($esito->differenze) > 0 ? implode("\n", $esito->differenze) : null;
        $this->registraGiroconto($liquidazione, $giroconto, $avvisi);
        $liquidazione->avvisi = count($avvisi) > 0 ? implode("\n", $avvisi) : null;
        $this->dataMapper->save($liquidazione);
        // il credito chiesto con il modello IVA TR sta sulla liquidazione che chiude il trimestre
        $trimestre = CreditoTrimestrale::getTrimestreChiuso($liquidazione);
        if ($trimestre !== null) {
            (new CreditoTrimestrale($this->dataMapper))->sincronizza($soggetto, $anno, $trimestre, $liquidazione);
        }
        $this->dataMapper->commitTransaction($primaTransazione);
        return $liquidazione;
    }

    /**
     * Liquidazioni delle attività separate del periodo, con i totali
     * riportati sulla liquidazione dell'azienda; null con la contabilità
     * unificata.
     *
     * @param string[] $avvisi
     * @return LiquidazioneAttivita[]|null
     */
    private function calcolaAttivita(LiquidazioneIva $liquidazione, VentilazioneCorrispettivi $ventilazione, array &$avvisi, bool $anticipaTrimestre = true): ?array
    {
        $soggetto = $liquidazione->soggetto;
        $anno = $liquidazione->anno;
        $separate = new AttivitaSeparate($this->dataMapper);
        $attivita = $separate->getAttivita($soggetto, $anno);
        if (count($attivita) === 0) {
            return null;
        }
        $periodicitaAzienda = (new RegimeIva($this->dataMapper))->getPeriodicitaAzienda($soggetto, $anno);
        $ventilazioneAttivita = $separate->getAttivitaVentilazione($soggetto, $anno);
        $calcolo = new CalcoloLiquidazioneIva($this->dataMapper);
        $dettagli = [];
        $liquidazione->trimestreAnticipato = null;
        $liquidazione->ivaEsigibile = $liquidazione->ivaDetraibile = $liquidazione->ivaVentilata = 0.0;
        foreach ($attivita as $singola) {
            $periodicita = $separate->getPeriodicita($singola, $periodicitaAzienda);
            $periodo = $liquidazione->periodo;
            if ($liquidazione->trimestreSeparato) {
                // trimestre separato: solo le attività trimestrali
                if ($periodicita !== PeriodicitaIva::trimestrale) {
                    continue;
                }
            } elseif ($periodicita !== $liquidazione->periodicita) {
                // attività trimestrale di un'azienda mensile: liquidata nel mese di fine trimestre o a parte
                if ($periodo % 3 !== 0) {
                    continue;
                }
                $periodo = intdiv($periodo, 3);
                if ($periodo === 4) {
                    $avvisi[] = 'Attività ' . $singola->getEtichetta() . ': il quarto trimestre si liquida con la dichiarazione annuale.';
                    continue;
                }
                if ($anticipaTrimestre === false) {
                    $avvisi[] = 'Attività ' . $singola->getEtichetta() . ': il ' . $periodo . '° trimestre non è anticipato al mese, si liquida a parte (liquidazione del trimestre, termine dei trimestrali).';
                    continue;
                }
                $liquidazione->trimestreAnticipato = $periodo;
            }
            $dal = PeriodiIva::getInizio($periodicita, $anno, $periodo);
            $al = PeriodiIva::getFine($periodicita, $anno, $periodo);
            $risultato = $calcolo->calcola($soggetto, $dal, $al, $singola);
            $ventilata = ($ventilazioneAttivita !== null) && ($ventilazioneAttivita->id === $singola->id) ? $ventilazione->calcola($soggetto, $dal, $al)->imposta : 0.0;
            $dettaglio = new LiquidazioneAttivita($this->dataMapper);
            $dettaglio->attivitaIva = $singola;
            $dettaglio->periodicita = $periodicita;
            $dettaglio->periodo = $periodo;
            $dettaglio->ivaVentilata = $ventilata;
            $dettaglio->ivaEsigibile = round($risultato->ivaEsigibile + $ventilata, 2);
            $dettaglio->ivaDetraibile = $risultato->ivaDetraibile;
            $dettaglio->percentualeDetrazione = $risultato->percentualeDetrazione;
            if (($periodicita === PeriodicitaIva::trimestrale) && ($liquidazione->periodicita === PeriodicitaIva::mensile) && ($dettaglio->getIvaPeriodo() > 0)) {
                $dettaglio->interessi = round($dettaglio->getIvaPeriodo() * PeriodiIva::INTERESSI_TRIMESTRALI / 100, 2);
            }
            foreach ($risultato->avvisi as $avviso) {
                $avvisi[] = 'Attività ' . $singola->getEtichetta() . ': ' . $avviso;
            }
            $liquidazione->ivaEsigibile += $dettaglio->ivaEsigibile;
            $liquidazione->ivaDetraibile += $dettaglio->ivaDetraibile;
            $liquidazione->ivaVentilata += $ventilata;
            $dettagli[] = $dettaglio;
        }
        $liquidazione->ivaEsigibile = round($liquidazione->ivaEsigibile, 2);
        $liquidazione->ivaDetraibile = round($liquidazione->ivaDetraibile, 2);
        $liquidazione->ivaVentilata = round($liquidazione->ivaVentilata, 2);
        if ($liquidazione->trimestreAnticipato !== null) {
            $avvisi[] = 'Attività separate: il ' . $liquidazione->trimestreAnticipato . '° trimestre delle attività trimestrali è liquidato con il mese di ' . LiquidazioneIva::descriviPeriodo(PeriodicitaIva::mensile, $anno, $liquidazione->periodo)
                    . ' (liquidazione anticipata ai fini della compensazione), con gli interessi dell\'1% sull\'IVA dovuta del trimestre.';
        }
        return $dettagli;
    }

    /**
     * Avvisi della ventilazione: opzione assente nei dati fiscali e, con
     * l'ultimo periodo, confronto tra la ventilazione dei corrispettivi
     * dell'anno con i rapporti di fine anno e l'imposta delle liquidazioni
     * (differenza da indicare nella dichiarazione annuale).
     *
     * @param string[] $avvisi
     */
    private function avvisiVentilazione(LiquidazioneIva $liquidazione, RisultatoVentilazione $periodo, RisultatoVentilazione $annuale, array &$avvisi): void
    {
        if ($annuale->corrispettivi == 0) {
            return;
        }
        $datiFiscali = (new RegimeIva($this->dataMapper))->getDatiFiscali($liquidazione->soggetto, $liquidazione->anno);
        if (($datiFiscali === null) || ($datiFiscali->ventilazioneCorrispettivi === false)) {
            $avvisi[] = 'Corrispettivi da ventilare, ma nei dati fiscali dell\'Anagrafica la ventilazione dei corrispettivi non è indicata per il ' . $liquidazione->anno . '.';
        }
        $requisiti = (new RequisitiVentilazione($this->dataMapper))->verifica($liquidazione->soggetto, $liquidazione->anno);
        if (count($requisiti['superati']) > 0) {
            $avvisi[] = 'Ventilazione non applicabile nel ' . $liquidazione->anno . ': ' . implode('; ', $requisiti['superati']) . '. Liquidare i corrispettivi con le aliquote dei singoli prodotti.';
        }
        if ((PeriodiIva::isUltimoPeriodo($liquidazione->periodicita, $liquidazione->periodo) === false) || ($annuale === $periodo)) {
            return;
        }
        $liquidata = $liquidazione->ivaVentilata;
        foreach ((new LiquidazioneIvaModel($this->dataMapper))->getPerAnno($liquidazione->soggetto, $liquidazione->anno) as $numero => $precedente) {
            if ($numero < $liquidazione->periodo) {
                $liquidata += $precedente->ivaVentilata;
            }
        }
        $differenza = round($annuale->imposta - $liquidata, 2);
        $avvisi[] = sprintf('Ventilazione annuale (corrispettivi dell\'anno € %s con i rapporti di fine anno): imposta € %s, nelle liquidazioni € %s, differenza € %s da riportare nella dichiarazione annuale.',
                number_format($annuale->corrispettivi, 2, ',', '.'), number_format($annuale->imposta, 2, ',', '.'), number_format($liquidata, 2, ',', '.'), number_format($differenza, 2, ',', '.'));
    }

    /**
     * Crediti d'imposta scomputabili (controllo del rigo VP11): IVA dovuta
     * del periodo e debito precedente, al netto dei crediti riportati e dei
     * versamenti auto UE.
     */
    public function getMassimoCreditiImposta(LiquidazioneIva $liquidazione): float
    {
        return max(0.0, round(max(0.0, $liquidazione->getIvaPeriodo()) + $liquidazione->debitoPrecedente - $liquidazione->creditoPrecedente
                        - $liquidazione->creditoAnnoPrecedente - $liquidazione->versamentiAuto, 2));
    }

    /**
     * Importo dovuto o credito del periodo; il debito non superiore alla
     * soglia si rinvia, gli interessi dei trimestrali si aggiungono al
     * versamento.
     */
    public function determinaImporti(LiquidazioneIva $liquidazione, float $interessiAnticipati = 0.0): void
    {
        $saldo = round($liquidazione->getIvaPeriodo() + $liquidazione->debitoPrecedente - $liquidazione->creditoPrecedente - $liquidazione->creditoAnnoPrecedente
                - $liquidazione->versamentiAuto - $liquidazione->creditiImposta - $liquidazione->acconto + $interessiAnticipati, 2);
        $liquidazione->importoDovuto = $liquidazione->creditoFinale = $liquidazione->debitoRinviato = 0.0;
        $liquidazione->interessi = round($interessiAnticipati, 2);
        if ($saldo <= 0) {
            $liquidazione->creditoFinale = -$saldo;
            return;
        }
        // il debito trasferito al gruppo non si rinvia (righi VP7 omessi nella comunicazione)
        if (($liquidazione->gruppoIva === null) && PeriodiIva::isRinviabile($liquidazione->periodicita, $liquidazione->periodo, $saldo, $liquidazione->anno)) {
            $liquidazione->debitoRinviato = $saldo;
            return;
        }
        // gli interessi dell'1% sono dei trimestrali per opzione (art. 7 D.P.R. 542/1999), non di quelli per natura
        if (($liquidazione->periodicita === PeriodicitaIva::trimestrale) && ($liquidazione->trimestraleSpeciale === false)) {
            $liquidazione->interessi = round($saldo * PeriodiIva::INTERESSI_TRIMESTRALI / 100, 2);
            $saldo += $liquidazione->interessi;
        }
        $liquidazione->importoDovuto = round($saldo, 2);
    }

    /**
     * @return Versamento[]
     */
    private function preparaVersamenti(LiquidazioneIva $liquidazione): array
    {
        if ($liquidazione->importoDovuto <= 0) {
            return [];
        }
        $codice = PeriodiIva::getCodiceTributo($liquidazione->periodicita, $liquidazione->periodo, $liquidazione->trimestraleSpeciale);
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
        $versamento->descrizione = 'IVA ' . $liquidazione->getDescrizionePeriodo();
        return [$versamento];
    }

    /**
     * Giroconto della liquidazione all'ultimo giorno del periodo: IVA delle
     * vendite in dare, IVA degli acquisti e acconto in avere, differenza a
     * Erario c/IVA. Sostituisce quello delle elaborazioni precedenti.
     *
     * @param string[] $avvisi
     */
    private function registraGiroconto(LiquidazioneIva $liquidazione, bool $richiesto, array &$avvisi): void
    {
        $soggetto = $liquidazione->soggetto;
        $salvataggio = new SalvataggioRegistrazione($this->dataMapper);
        $riferimento = $liquidazione->getRiferimentoVersamento();
        $esistente = (new RegistrazioneContabileModel($this->dataMapper))->getGenerata($soggetto, LiquidazioneIva::ORIGINE, $riferimento);
        $liquidazione->registrazioneContabile = null;
        if ($esistente !== null) {
            $blocco = $esistente->isModificabile() ? (new StampeDefinitive($this->dataMapper))->getBlocco($esistente) : 'l\'esercizio della registrazione n. ' . $esistente->numero . ' è chiuso.';
            if ($blocco !== null) {
                $avvisi[] = 'Giroconto non aggiornato: ' . lcfirst($blocco);
                $liquidazione->registrazioneContabile = $esistente;
                return;
            }
            $salvataggio->elimina($esistente);
        }
        if (($richiesto === false) || (($liquidazione->ivaEsigibile == 0) && ($liquidazione->ivaDetraibile == 0) && ($liquidazione->acconto == 0))) {
            return;
        }
        $data = PeriodiIva::getFine($liquidazione->periodicita, $liquidazione->anno, $liquidazione->periodo);
        $esercizio = (new EsercizioContabileModel($this->dataMapper))->getPerData($soggetto, $data);
        $piano = (new PianoContiModel($this->dataMapper))->getPerAzienda($soggetto);
        if (($esercizio === null) || $esercizio->chiuso || ($piano === null)) {
            $avvisi[] = 'Giroconto non registrato: nessun esercizio aperto al ' . $data->format('d/m/Y') . '.';
            return;
        }
        $contoModel = new ContoModel($this->dataMapper);
        $conti = [];
        $conti[FunzioneConto::corrispettivi->value] = $contoModel->getPerFunzione($piano, FunzioneConto::corrispettivi) ?? $contoModel->getPerFunzione($piano, FunzioneConto::ricavi);
        if (($conti[FunzioneConto::corrispettivi->value] === null) && ($liquidazione->ivaVentilata > 0)) {
            $avvisi[] = 'Giroconto non registrato: nel piano dei conti manca il conto dei corrispettivi o dei ricavi per la ventilazione.';
            return;
        }
        $conti[FunzioneConto::ivaGruppo->value] = $liquidazione->gruppoIva === null ? null : $contoModel->getPerFunzione($piano, FunzioneConto::ivaGruppo);
        if (($liquidazione->gruppoIva !== null) && ($conti[FunzioneConto::ivaGruppo->value] === null)) {
            $avvisi[] = 'Giroconto: nel piano dei conti manca il conto con funzione "' . FunzioneConto::ivaGruppo->getLabel() . '": il trasferimento al gruppo va a Erario c/IVA.';
        }
        foreach ([FunzioneConto::ivaVendite, FunzioneConto::ivaAcquisti, FunzioneConto::erarioIva, FunzioneConto::accontoIva] as $funzione) {
            $conti[$funzione->value] = $contoModel->getPerFunzione($piano, $funzione);
            if (($conti[$funzione->value] === null) && (($funzione !== FunzioneConto::accontoIva) || ($liquidazione->acconto > 0))) {
                $avvisi[] = 'Giroconto non registrato: nel piano dei conti manca il conto con funzione "' . $funzione->getLabel() . '".';
                return;
            }
        }
        $righe = [];
        $aggiungi = function (FunzioneConto $funzione, float $importo) use (&$righe, $conti): void {
            if (round($importo, 2) == 0) {
                return;
            }
            $riga = new RigaContabile($this->dataMapper);
            $riga->conto = $conti[$funzione->value];
            $riga->dare = $importo > 0 ? round($importo, 2) : 0.0;
            $riga->avere = $importo < 0 ? round(-$importo, 2) : 0.0;
            $righe[] = $riga;
        };
        // ventilazione: l'imposta dei corrispettivi, registrati al lordo nei ricavi, si storna da lì
        $aggiungi(FunzioneConto::corrispettivi, $liquidazione->ivaVentilata);
        $aggiungi(FunzioneConto::ivaVendite, $liquidazione->ivaEsigibile - $liquidazione->ivaVentilata);
        $aggiungi(FunzioneConto::ivaAcquisti, -$liquidazione->ivaDetraibile);
        $aggiungi(FunzioneConto::accontoIva, -$liquidazione->acconto);
        $aggiungi($conti[FunzioneConto::ivaGruppo->value] === null ? FunzioneConto::erarioIva : FunzioneConto::ivaGruppo, -($liquidazione->ivaEsigibile - $liquidazione->ivaDetraibile - $liquidazione->acconto));
        $registrazione = new RegistrazioneContabile($this->dataMapper);
        $registrazione->studio = $soggetto->studio;
        $registrazione->soggetto = $soggetto;
        $registrazione->esercizioContabile = $esercizio;
        $registrazione->dataRegistrazione = $data;
        $registrazione->causale = CausaleContabile::liquidazioneIva;
        $registrazione->descrizione = 'Liquidazione IVA ' . $liquidazione->getDescrizionePeriodo();
        $registrazione->origine = LiquidazioneIva::ORIGINE;
        $registrazione->riferimentoOrigine = $riferimento;
        try {
            $liquidazione->registrazioneContabile = $salvataggio->salva($registrazione, [], $righe);
        } catch (\DomainException $errore) {
            $avvisi[] = 'Giroconto non registrato: ' . lcfirst($errore->getMessage());
        }
    }

    /**
     * Elimina la liquidazione, il versamento ancora modificabile e il
     * giroconto.
     *
     * @return string[] differenze con i versamenti in deleghe non modificabili
     */
    public function elimina(LiquidazioneIva $liquidazione): array
    {
        $primaTransazione = $this->dataMapper->startTransaction();
        $esito = (new ArchivioVersamenti($this->dataMapper))->registra($liquidazione->soggetto, LiquidazioneIva::ORIGINE, $liquidazione->getRiferimentoVersamento(), []);
        $giroconto = $liquidazione->registrazioneContabile;
        $liquidazione->registrazioneContabile = null;
        $this->dataMapper->save($liquidazione);
        if (($giroconto !== null) && $giroconto->isModificabile() && ((new StampeDefinitive($this->dataMapper))->getBlocco($giroconto) === null)) {
            (new SalvataggioRegistrazione($this->dataMapper))->elimina($giroconto);
        }
        $soggetto = $liquidazione->soggetto;
        $anno = $liquidazione->anno;
        $trimestre = CreditoTrimestrale::getTrimestreChiuso($liquidazione);
        $this->dataMapper->delete($liquidazione);
        // eliminato il trimestre separato, il credito chiesto torna sul mese che chiude il trimestre
        if ($trimestre !== null) {
            (new CreditoTrimestrale($this->dataMapper))->sincronizza($soggetto, $anno, $trimestre);
        }
        $this->dataMapper->commitTransaction($primaTransazione);
        return $esito->differenze;
    }
}
