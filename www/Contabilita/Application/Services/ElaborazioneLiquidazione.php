<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Contabilita\Application\Entities\LiquidazioneIva;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Models\AccontoIvaModel;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\EsercizioContabileModel;
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
    public function elabora(Soggetto $soggetto, int $anno, int $periodo, float $creditoAnnoPrecedente = 0.0, bool $giroconto = true, float $versamentiAuto = 0.0, float $creditiImposta = 0.0): LiquidazioneIva
    {
        if (($versamentiAuto < 0) || ($creditiImposta < 0)) {
            throw new \DomainException('Versamenti auto UE e crediti d\'imposta non possono essere negativi.');
        }
        $periodicita = (new RegimeIva($this->dataMapper))->getPeriodicita($soggetto, $anno);
        if (($periodo < 1) || ($periodo > PeriodiIva::getNumeroPeriodi($periodicita))) {
            throw new \DomainException('Periodo non valido per la periodicità ' . mb_strtolower($periodicita->getLabel()) . '.');
        }
        $model = new LiquidazioneIvaModel($this->dataMapper);
        $dal = PeriodiIva::getInizio($periodicita, $anno, $periodo);
        $al = PeriodiIva::getFine($periodicita, $anno, $periodo);
        $risultato = (new CalcoloLiquidazioneIva($this->dataMapper))->calcola($soggetto, $dal, $al);
        $ventilazione = new VentilazioneCorrispettivi($this->dataMapper);
        $ventilata = $ventilazione->calcola($soggetto, $dal, $al);
        $annuale = (PeriodiIva::isUltimoPeriodo($periodicita, $periodo) && ($periodo > 1)) ? $ventilazione->calcola($soggetto, new SismaDate($anno . '-01-01'), $al) : $ventilata;
        $liquidazione = $model->getPerPeriodo($soggetto, $anno, $periodo) ?? new LiquidazioneIva($this->dataMapper);
        $liquidazione->studio = $soggetto->studio;
        $liquidazione->soggetto = $soggetto;
        $liquidazione->anno = $anno;
        $liquidazione->periodo = $periodo;
        $liquidazione->periodicita = $periodicita;
        $liquidazione->dataElaborazione = new SismaDateTime();
        $liquidazione->ivaVentilata = $ventilata->imposta;
        $liquidazione->ivaEsigibile = round($risultato->ivaEsigibile + $ventilata->imposta, 2);
        $liquidazione->ivaDetraibile = $risultato->ivaDetraibile;
        $avvisi = $risultato->avvisi;
        $this->avvisiVentilazione($liquidazione, $ventilata, $annuale, $avvisi);
        $precedente = $periodo > 1 ? $model->getPerPeriodo($soggetto, $anno, $periodo - 1) : null;
        if (($periodo > 1) && ($precedente === null)) {
            $avvisi[] = 'Liquidazione del periodo precedente non elaborata: credito e debito da riportare non considerati.';
        }
        $liquidazione->creditoPrecedente = $precedente?->creditoFinale ?? 0.0;
        $liquidazione->debitoPrecedente = $precedente?->debitoRinviato ?? 0.0;
        $liquidazione->creditoAnnoPrecedente = round(max(0.0, $creditoAnnoPrecedente), 2);
        $liquidazione->versamentiAuto = round($versamentiAuto, 2);
        $liquidazione->creditiImposta = round($creditiImposta, 2);
        if ($creditiImposta > 0) {
            if (($periodicita === PeriodicitaIva::trimestrale) && ($periodo === 4)) {
                throw new \DomainException('Quarto trimestre: i crediti d\'imposta si scomputano nel saldo della dichiarazione annuale.');
            }
            $massimo = $this->getMassimoCreditiImposta($liquidazione);
            if ($creditiImposta > $massimo + 0.005) {
                throw new \DomainException(sprintf('I crediti d\'imposta (VP11) non possono superare l\'IVA da versare prima del loro scomputo (€ %s).', number_format($massimo, 2, ',', '.')));
            }
        }
        $primaTransazione = $this->dataMapper->startTransaction();
        $liquidazione->acconto = 0.0;
        if (PeriodiIva::isUltimoPeriodo($periodicita, $periodo)) {
            $acconto = (new AccontoIvaModel($this->dataMapper))->getPerAnno($soggetto, $anno);
            $liquidazione->acconto = ($acconto !== null) && $acconto->isDovuto() ? $acconto->importo : 0.0;
        }
        $this->determinaImporti($liquidazione);
        if (($periodicita === PeriodicitaIva::trimestrale) && ($periodo === 4)) {
            $avvisi[] = 'Quarto trimestre: si versa con il saldo della dichiarazione annuale entro il 16 marzo (codice 6099, con gli interessi dell\'1%).';
        }
        $liquidazione->scadenza = PeriodiIva::getScadenza($periodicita, $anno, $periodo);
        $this->dataMapper->save($liquidazione);
        $esito = (new ArchivioVersamenti($this->dataMapper))->registra($soggetto, LiquidazioneIva::ORIGINE, LiquidazioneIva::getRiferimento($anno, $periodo), $this->preparaVersamenti($liquidazione));
        $liquidazione->differenze = count($esito->differenze) > 0 ? implode("\n", $esito->differenze) : null;
        $this->registraGiroconto($liquidazione, $giroconto, $avvisi);
        $liquidazione->avvisi = count($avvisi) > 0 ? implode("\n", $avvisi) : null;
        $this->dataMapper->save($liquidazione);
        $this->dataMapper->commitTransaction($primaTransazione);
        return $liquidazione;
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
    public function determinaImporti(LiquidazioneIva $liquidazione): void
    {
        $saldo = round($liquidazione->getIvaPeriodo() + $liquidazione->debitoPrecedente - $liquidazione->creditoPrecedente - $liquidazione->creditoAnnoPrecedente
                - $liquidazione->versamentiAuto - $liquidazione->creditiImposta - $liquidazione->acconto, 2);
        $liquidazione->importoDovuto = $liquidazione->creditoFinale = $liquidazione->debitoRinviato = $liquidazione->interessi = 0.0;
        if ($saldo <= 0) {
            $liquidazione->creditoFinale = -$saldo;
            return;
        }
        if (PeriodiIva::isRinviabile($liquidazione->periodicita, $liquidazione->periodo, $saldo, $liquidazione->anno)) {
            $liquidazione->debitoRinviato = $saldo;
            return;
        }
        if ($liquidazione->periodicita === PeriodicitaIva::trimestrale) {
            $liquidazione->interessi = round($saldo * PeriodiIva::INTERESSI_TRIMESTRALI / 100, 2);
        }
        $liquidazione->importoDovuto = round($saldo + $liquidazione->interessi, 2);
    }

    /**
     * @return Versamento[]
     */
    private function preparaVersamenti(LiquidazioneIva $liquidazione): array
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
        $riferimento = LiquidazioneIva::getRiferimento($liquidazione->anno, $liquidazione->periodo);
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
        $aggiungi(FunzioneConto::erarioIva, -($liquidazione->ivaEsigibile - $liquidazione->ivaDetraibile - $liquidazione->acconto));
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
        $esito = (new ArchivioVersamenti($this->dataMapper))->registra($liquidazione->soggetto, LiquidazioneIva::ORIGINE, LiquidazioneIva::getRiferimento($liquidazione->anno, $liquidazione->periodo), []);
        $giroconto = $liquidazione->registrazioneContabile;
        $liquidazione->registrazioneContabile = null;
        $this->dataMapper->save($liquidazione);
        if (($giroconto !== null) && $giroconto->isModificabile() && ((new StampeDefinitive($this->dataMapper))->getBlocco($giroconto) === null)) {
            (new SalvataggioRegistrazione($this->dataMapper))->elimina($giroconto);
        }
        $this->dataMapper->delete($liquidazione);
        $this->dataMapper->commitTransaction($primaTransazione);
        return $esito->differenze;
    }
}
