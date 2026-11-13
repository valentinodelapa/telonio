<?php

namespace Contabilita\Application\Services\Lipe;

use Anagrafica\Application\Entities\Legame;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Models\LegameModel;
use Contabilita\Application\Entities\ComunicazioneLipe;
use Contabilita\Application\Entities\GruppoIva;
use Contabilita\Application\Entities\LiquidazioneIva;
use Contabilita\Application\Entities\ModuloLipe;
use Contabilita\Application\Enumerations\CodiceCaricaIva;
use Contabilita\Application\Enumerations\ImpegnoPresentazione;
use Contabilita\Application\Enumerations\MetodoAccontoIva;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\AccontoIvaModel;
use Contabilita\Application\Models\ComunicazioneLipeModel;
use Contabilita\Application\Models\LiquidazioneAttivitaModel;
use Contabilita\Application\Models\LiquidazioneGruppoIvaModel;
use Contabilita\Application\Models\LiquidazioneIvaModel;
use Contabilita\Application\Models\ModuloLipeModel;
use Contabilita\Application\Models\PartecipanteGruppoIvaModel;
use Contabilita\Application\Models\RigaIvaModel;
use Contabilita\Application\Services\AttivitaSeparate;
use Contabilita\Application\Services\LiquidazioneGruppo;
use Contabilita\Application\Services\PeriodiIva;
use Contabilita\Application\Services\RegimeIva;
use Contabilita\Application\Services\VentilazioneCorrispettivi;
use Deleghe\Application\Models\IntermediarioModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Preparazione della comunicazione delle liquidazioni periodiche del
 * trimestre dalle liquidazioni elaborate (un modulo per ogni mese dei
 * mensili, uno per il trimestre dei trimestrali):
 *
 * - VP2 e VP3: imponibili delle operazioni attive e passive con data di
 *   competenza nel periodo, comprese quelle non imponibili, esenti, in
 *   scissione dei pagamenti, a esigibilità differita e in inversione
 *   contabile; per i corrispettivi ventilati l'imponibile della
 *   ventilazione;
 * - VP4-VP13: dalla liquidazione (VP7 e VP8 omessi dove le specifiche
 *   non li ammettono, VP12 solo per i trimestrali, VP10 versamenti auto UE
 *   e VP11 crediti d'imposta indicati all'elaborazione);
 * - VP14: risultato dei righi, compreso il debito non versato perché non
 *   superiore a 100 euro;
 * - quarto trimestre dei trimestrali: periodo "5", senza VP11, VP12 e
 *   VP14 (l'imposta si versa con la dichiarazione annuale).
 *
 * Attività separate con attività trimestrali in un'azienda mensile: il
 * modulo del mese di fine trimestre riepiloga anche il trimestre
 * anticipato (mese e trimestre nel rigo VP1). Società partecipanti
 * all'IVA di gruppo: righi VP7, VP8, VP9 e VP13 omessi, nel VP14 il
 * debito o credito trasferito alla controllante. La controllante presenta
 * anche la comunicazione del gruppo (preparaGruppo): VP4 e VP5 con i
 * debiti e i crediti trasferiti, senza VP2, VP3, VP10, VP11 e VP12.
 *
 * Il dichiarante (rappresentante legale in carica per i soggetti diversi
 * dalle persone fisiche) e l'intermediario predefinito dello studio sono
 * proposti alla prima preparazione e poi mantenuti.
 */
class PreparazioneLipe
{

    private const METODI_ACCONTO = ['S' => '1', 'P' => '2', 'A' => '3'];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @throws \DomainException se la comunicazione è già inviata o mancano liquidazioni del trimestre
     */
    public function prepara(Soggetto $soggetto, int $anno, int $trimestre): ComunicazioneLipe
    {
        if (($trimestre < 1) || ($trimestre > 4)) {
            throw new \DomainException('Trimestre non valido.');
        }
        $periodicita = (new RegimeIva($this->dataMapper))->getPeriodicita($soggetto, $anno);
        $periodi = $periodicita === PeriodicitaIva::mensile ? range($trimestre * 3 - 2, $trimestre * 3) : [$trimestre];
        $liquidazioni = (new LiquidazioneIvaModel($this->dataMapper))->getPerAnno($soggetto, $anno);
        $mancanti = array_filter($periodi, fn(int $periodo) => isset($liquidazioni[$periodo]) === false);
        if (count($mancanti) > 0) {
            throw new \DomainException('Elaborare prima le liquidazioni di: ' . implode(', ', array_map(fn(int $periodo) => LiquidazioneIva::descriviPeriodo($periodicita, $anno, $periodo), $mancanti)) . '.');
        }
        $model = new ComunicazioneLipeModel($this->dataMapper);
        $comunicazione = $model->getPerTrimestre($soggetto, $anno, $trimestre);
        if (($comunicazione !== null) && ($comunicazione->isModificabile() === false)) {
            throw new \DomainException('La comunicazione del ' . $comunicazione->getDescrizione() . ' è inviata: riportarla in bozza per riprepararla.');
        }
        $primaTransazione = $this->dataMapper->startTransaction();
        if ($comunicazione === null) {
            $comunicazione = new ComunicazioneLipe($this->dataMapper);
            $comunicazione->studio = $soggetto->studio;
            $comunicazione->soggetto = $soggetto;
            $comunicazione->anno = $anno;
            $comunicazione->trimestre = $trimestre;
            $comunicazione->progressivo = $model->getProssimoProgressivo($soggetto);
            $this->proponiFirmatari($comunicazione);
        } else {
            foreach ((new ModuloLipeModel($this->dataMapper))->getModuli($comunicazione) as $modulo) {
                $this->dataMapper->delete($modulo);
            }
        }
        $comunicazione->dataElaborazione = new SismaDateTime();
        $avvisi = [];
        $this->dataMapper->save($comunicazione);
        // attività trimestrali liquidate a parte: un modulo per il trimestre dopo quelli dei mesi
        $moduli = array_map(fn(int $periodo) => $liquidazioni[$periodo], $periodi);
        $separata = $periodicita === PeriodicitaIva::mensile ? (new LiquidazioneIvaModel($this->dataMapper))->getPerPeriodo($soggetto, $anno, $trimestre, true) : null;
        if ($separata !== null) {
            $moduli[] = $separata;
        }
        foreach ($moduli as $indice => $liquidazione) {
            $modulo = $this->preparaModulo($comunicazione, $liquidazione, $avvisi);
            $modulo->numeroModulo = $indice + 1;
            $this->dataMapper->save($modulo);
        }
        $comunicazione->avvisi = count($avvisi) > 0 ? implode("\n", $avvisi) : null;
        $this->dataMapper->save($comunicazione);
        $this->dataMapper->commitTransaction($primaTransazione);
        return $comunicazione;
    }

    /**
     * @param string[] $avvisi
     */
    public function preparaModulo(ComunicazioneLipe $comunicazione, LiquidazioneIva $liquidazione, array &$avvisi): ModuloLipe
    {
        $modulo = new ModuloLipe($this->dataMapper);
        $modulo->comunicazioneLipe = $comunicazione;
        $modulo->liquidazioneIva = $liquidazione;
        $mensile = $liquidazione->periodicita === PeriodicitaIva::mensile;
        $periodo = $liquidazione->periodo;
        if ($mensile) {
            $modulo->mese = $periodo;
            $modulo->trimestre = $liquidazione->trimestreAnticipato;
        } else {
            // quarto trimestre: "5" per i trimestrali per opzione, "4" per quelli per natura (art. 74, c. 4), che lo versano entro il 16 febbraio
            $modulo->trimestre = ($periodo === 4) && ($liquidazione->trimestraleSpeciale === false) ? ModuloLipe::QUARTO_TRIMESTRE_TRIMESTRALI : $periodo;
        }
        [$modulo->operazioniAttive, $modulo->operazioniPassive] = $this->getTotaliOperazioni($liquidazione);
        $modulo->ivaEsigibile = $liquidazione->ivaEsigibile;
        $modulo->ivaDetratta = $liquidazione->ivaDetraibile;
        $descrizione = $liquidazione->getDescrizionePeriodo();
        // VP7: non per gennaio, dicembre, primo e quarto trimestre (il debito di novembre e del terzo trimestre non si rinvia)
        $debitoAmmesso = $mensile ? !in_array($periodo, [1, 12], true) : !in_array($periodo, [1, 4], true);
        $modulo->debitoPrecedente = $debitoAmmesso ? $liquidazione->debitoPrecedente : 0.0;
        // VP8: il credito dell'anno precedente si indica nel VP9, non nel primo periodo come credito del periodo precedente;
        // il trimestre separato riceve il credito del mese di fine trimestre
        $modulo->creditoPrecedente = ($periodo > 1) || $liquidazione->trimestreSeparato ? $liquidazione->creditoPrecedente : 0.0;
        if ($liquidazione->trimestreSeparato) {
            $modulo->debitoPrecedente = $liquidazione->debitoPrecedente;
        }
        foreach (['debitoPrecedente' => 'VP7', 'creditoPrecedente' => 'VP8'] as $campo => $rigo) {
            if ($modulo->$campo != $liquidazione->$campo) {
                $avvisi[] = $descrizione . ': importo della liquidazione non ammesso nel rigo ' . $rigo . ' per il periodo, omesso.';
            }
        }
        if ($liquidazione->isTrasferita()) {
            // IVA di gruppo: VP7, VP8, VP9 e VP13 omessi
            $modulo->debitoPrecedente = $modulo->creditoPrecedente = 0.0;
        }
        $modulo->creditoAnnoPrecedente = $liquidazione->isTrasferita() ? 0.0 : $liquidazione->creditoAnnoPrecedente;
        $modulo->versamentiAuto = $liquidazione->versamentiAuto;
        $modulo->creditiImposta = $modulo->isQuartoTrimestreTrimestrali() ? 0.0 : $liquidazione->creditiImposta;
        $modulo->interessi = ($mensile && ($liquidazione->trimestreAnticipato === null)) || $modulo->isQuartoTrimestreTrimestrali() ? 0.0 : $liquidazione->interessi;
        if (($liquidazione->acconto > 0) && PeriodiIva::isUltimoPeriodo($liquidazione->periodicita, $periodo) && ($liquidazione->isTrasferita() === false)) {
            $acconto = (new AccontoIvaModel($this->dataMapper))->getPerAnno($comunicazione->soggetto, $comunicazione->anno);
            $modulo->acconto = $liquidazione->acconto;
            $modulo->metodoAcconto = self::METODI_ACCONTO[($acconto?->metodo ?? MetodoAccontoIva::storico)->value];
        }
        if ($modulo->isQuartoTrimestreTrimestrali() === false) {
            $saldo = round($modulo->getIvaPeriodo() + $modulo->debitoPrecedente + $modulo->interessi - $modulo->creditoPrecedente - $modulo->creditoAnnoPrecedente
                    - $modulo->versamentiAuto - $modulo->creditiImposta - $modulo->acconto, 2);
            $modulo->importoDaVersare = max(0.0, $saldo);
            $modulo->importoACredito = max(0.0, -$saldo);
            $liquidato = round($liquidazione->importoDovuto + $liquidazione->debitoRinviato - $liquidazione->creditoFinale, 2);
            if (abs($saldo - $liquidato) > 0.005) {
                $avvisi[] = sprintf('%s: il rigo VP14 (€ %s) differisce dal risultato della liquidazione (€ %s): rielaborare la liquidazione.', $descrizione, number_format($saldo, 2, ',', '.'), number_format($liquidato, 2, ',', '.'));
            }
        }
        return $modulo;
    }

    /**
     * Comunicazione della controllante per la liquidazione del gruppo del
     * trimestre: un modulo per ogni liquidazione di gruppo del trimestre
     * (con il trimestre anticipato delle società trimestrali).
     *
     * @throws \DomainException se la comunicazione è già inviata o mancano liquidazioni di gruppo del trimestre
     */
    public function preparaGruppo(GruppoIva $gruppo, int $anno, int $trimestre): ComunicazioneLipe
    {
        if (($trimestre < 1) || ($trimestre > 4)) {
            throw new \DomainException('Trimestre non valido.');
        }
        $periodicita = (new LiquidazioneGruppo($this->dataMapper))->getPeriodicita($gruppo, $anno);
        $periodi = $periodicita === PeriodicitaIva::mensile ? range($trimestre * 3 - 2, $trimestre * 3) : ($trimestre === 4 ? [] : [$trimestre]);
        if (count($periodi) === 0) {
            throw new \DomainException('Gruppo di società trimestrali: il quarto trimestre si liquida con la dichiarazione annuale, nessuna comunicazione del gruppo.');
        }
        $liquidazioniGruppo = new LiquidazioneGruppoIvaModel($this->dataMapper);
        $liquidazioni = $liquidazioniGruppo->getPerAnno($gruppo, $anno);
        $mancanti = array_filter($periodi, fn(int $periodo) => isset($liquidazioni[$periodo]) === false);
        if (count($mancanti) > 0) {
            throw new \DomainException('Elaborare prima le liquidazioni di gruppo di: ' . implode(', ', array_map(fn(int $periodo) => LiquidazioneIva::descriviPeriodo($periodicita, $anno, $periodo), $mancanti)) . '.');
        }
        $controllante = $gruppo->controllante;
        $model = new ComunicazioneLipeModel($this->dataMapper);
        $comunicazione = $model->getPerTrimestre($controllante, $anno, $trimestre, $gruppo);
        if (($comunicazione !== null) && ($comunicazione->isModificabile() === false)) {
            throw new \DomainException('La comunicazione del gruppo del ' . $comunicazione->getDescrizione() . ' è inviata: riportarla in bozza per riprepararla.');
        }
        $primaTransazione = $this->dataMapper->startTransaction();
        if ($comunicazione === null) {
            $comunicazione = new ComunicazioneLipe($this->dataMapper);
            $comunicazione->studio = $controllante->studio;
            $comunicazione->soggetto = $controllante;
            $comunicazione->gruppoIva = $gruppo;
            $comunicazione->anno = $anno;
            $comunicazione->trimestre = $trimestre;
            $comunicazione->progressivo = $model->getProssimoProgressivo($controllante);
            $this->proponiFirmatari($comunicazione);
        } else {
            foreach ((new ModuloLipeModel($this->dataMapper))->getModuli($comunicazione) as $modulo) {
                $this->dataMapper->delete($modulo);
            }
        }
        $comunicazione->dataElaborazione = new SismaDateTime();
        $this->dataMapper->save($comunicazione);
        $avvisi = [];
        $elenco = array_map(fn(int $periodo) => $liquidazioni[$periodo], $periodi);
        $separata = $periodicita === PeriodicitaIva::mensile ? $liquidazioniGruppo->getPerPeriodo($gruppo, $anno, $trimestre, true) : null;
        if ($separata !== null) {
            $elenco[] = $separata;
        }
        foreach ($elenco as $indice => $liquidazione) {
            $periodo = $liquidazione->periodo;
            $modulo = new ModuloLipe($this->dataMapper);
            $modulo->comunicazioneLipe = $comunicazione;
            $modulo->liquidazioneGruppoIva = $liquidazione;
            $modulo->numeroModulo = $indice + 1;
            if ($liquidazione->periodicita === PeriodicitaIva::mensile) {
                $modulo->mese = $periodo;
                $modulo->trimestre = $liquidazione->trimestreAnticipato;
            } else {
                $modulo->trimestre = $periodo;
            }
            $modulo->ivaEsigibile = $liquidazione->debitiTrasferiti;
            $modulo->ivaDetratta = $liquidazione->creditiTrasferiti;
            $modulo->debitoPrecedente = $liquidazione->debitoPrecedente;
            $modulo->creditoPrecedente = ($periodo > 1) || $liquidazione->trimestreSeparato ? $liquidazione->creditoPrecedente : 0.0;
            $modulo->creditoAnnoPrecedente = $liquidazione->creditoAnnoPrecedente;
            if ($liquidazione->acconto > 0) {
                $acconto = (new AccontoIvaModel($this->dataMapper))->getPerAnno($controllante, $anno, $gruppo);
                $modulo->acconto = $liquidazione->acconto;
                $modulo->metodoAcconto = self::METODI_ACCONTO[($acconto?->metodo ?? MetodoAccontoIva::storico)->value];
            }
            $saldo = round($modulo->getIvaPeriodo() + $modulo->debitoPrecedente - $modulo->creditoPrecedente - $modulo->creditoAnnoPrecedente - $modulo->acconto, 2);
            $modulo->importoDaVersare = max(0.0, $saldo);
            $modulo->importoACredito = max(0.0, -$saldo);
            $liquidato = round($liquidazione->importoDovuto + $liquidazione->debitoRinviato - $liquidazione->creditoFinale, 2);
            if (abs($saldo - $liquidato) > 0.005) {
                $avvisi[] = sprintf('%s: il rigo VP14 (€ %s) differisce dal risultato della liquidazione di gruppo (€ %s): rielaborarla.', $liquidazione->getDescrizionePeriodo(), number_format($saldo, 2, ',', '.'), number_format($liquidato, 2, ',', '.'));
            }
            $this->dataMapper->save($modulo);
        }
        $comunicazione->avvisi = count($avvisi) > 0 ? implode("\n", $avvisi) : null;
        $this->dataMapper->save($comunicazione);
        $this->dataMapper->commitTransaction($primaTransazione);
        return $comunicazione;
    }

    /**
     * Partita IVA della controllante e ultimo mese di controllo del
     * frontespizio delle comunicazioni delle società partecipanti.
     *
     * @return array{0: ?string, 1: ?int}
     */
    public function getDatiGruppo(ComunicazioneLipe $comunicazione, array $moduli): array
    {
        if ($comunicazione->gruppoIva !== null) {
            return [null, null];
        }
        $trasferite = array_filter($moduli, fn(ModuloLipe $modulo) => $modulo->liquidazioneIva?->isTrasferita() ?? false);
        $gruppo = count($trasferite) > 0 ? reset($trasferite)->liquidazioneIva->gruppoIva : null;
        $ultimoMese = null;
        $partecipante = (new PartecipanteGruppoIvaModel($this->dataMapper))->getPerControllata($comunicazione->soggetto, $comunicazione->anno);
        $cessazione = $partecipante?->dataCessazione;
        if (($cessazione !== null) && ((int) $cessazione->format('Y') === $comunicazione->anno) && (intdiv((int) $cessazione->format('n') - 1, 3) + 1 === $comunicazione->trimestre)) {
            $ultimoMese = $partecipante->getUltimoMese($comunicazione->anno, (new RegimeIva($this->dataMapper))->getPeriodicita($comunicazione->soggetto, $comunicazione->anno));
            $gruppo ??= $partecipante->gruppoIva;
        }
        $controllante = $gruppo?->controllante;
        $partitaIva = ($controllante !== null) && (count($trasferite) > 0) ? $controllante->partitaIva : null;
        return [$partitaIva, $ultimoMese];
    }

    /**
     * Righi VP2 e VP3 del periodo della liquidazione.
     *
     * @return array{0: float, 1: float}
     */
    public function getTotaliOperazioni(LiquidazioneIva $liquidazione): array
    {
        $dettagli = isset($liquidazione->id) ? (new LiquidazioneAttivitaModel($this->dataMapper))->getPerLiquidazione($liquidazione) : [];
        if (count($dettagli) > 0) {
            // attività separate: operazioni del periodo di ciascuna attività, con le quote dei promiscui
            $attive = $passive = 0.0;
            $separate = new AttivitaSeparate($this->dataMapper);
            foreach ($dettagli as $dettaglio) {
                $dal = PeriodiIva::getInizio($dettaglio->periodicita, $liquidazione->anno, $dettaglio->periodo);
                $al = PeriodiIva::getFine($dettaglio->periodicita, $liquidazione->anno, $dettaglio->periodo);
                foreach ((new RigaIvaModel($this->dataMapper))->getPerPeriodo($liquidazione->soggetto, $dal, $al) as $riga) {
                    $quota = $separate->getQuote($riga, $liquidazione->soggetto, $liquidazione->anno)[$dettaglio->attivitaIva->numero] ?? 0.0;
                    if ($riga->registroIva->tipo === TipoRegistroIva::acquisti) {
                        $passive += $riga->imponibile * $quota;
                    } elseif ((($riga->registroIva->tipo === TipoRegistroIva::corrispettivi) && $riga->codiceIva->ventilazione) === false) {
                        $attive += $riga->imponibile * $quota;
                    }
                }
            }
            return [round($attive, 2), round($passive, 2)];
        }
        $dal = PeriodiIva::getInizio($liquidazione->periodicita, $liquidazione->anno, $liquidazione->periodo);
        $al = PeriodiIva::getFine($liquidazione->periodicita, $liquidazione->anno, $liquidazione->periodo);
        $attive = $passive = 0.0;
        $ventilare = false;
        foreach ((new RigaIvaModel($this->dataMapper))->getPerPeriodo($liquidazione->soggetto, $dal, $al) as $riga) {
            if ($riga->registroIva->tipo === TipoRegistroIva::acquisti) {
                $passive += $riga->imponibile;
            } elseif (($riga->registroIva->tipo === TipoRegistroIva::corrispettivi) && $riga->codiceIva->ventilazione) {
                $ventilare = true;
            } else {
                $attive += $riga->imponibile;
            }
        }
        if ($ventilare) {
            try {
                foreach ((new VentilazioneCorrispettivi($this->dataMapper))->calcola($liquidazione->soggetto, $dal, $al)->righe as $riga) {
                    $attive += $riga['imponibile'];
                }
            } catch (\DomainException) {
                // senza acquisti per la rivendita la liquidazione ha già segnalato il problema
            }
        }
        return [round($attive, 2), round($passive, 2)];
    }

    private function proponiFirmatari(ComunicazioneLipe $comunicazione): void
    {
        $legame = $this->getRappresentante($comunicazione->soggetto, new SismaDate());
        if ($legame !== null) {
            $comunicazione->dichiarante = $legame->soggettoCollegato;
            $comunicazione->codiceCarica = $legame->ruolo === RuoloLegame::liquidatore ? CodiceCaricaIva::liquidatore : CodiceCaricaIva::rappresentanteLegale;
        }
        $intermediario = (new IntermediarioModel($this->dataMapper))->getPredefinito($comunicazione->studio);
        if ($intermediario !== null) {
            $comunicazione->intermediario = $intermediario;
            $comunicazione->impegno = ImpegnoPresentazione::intermediario;
            $comunicazione->dataImpegno = new SismaDate();
        }
    }

    /**
     * Rappresentante legale (amministratore unico, presidente del CdA o
     * liquidatore) persona fisica in carica, per i soggetti diversi dalle
     * persone fisiche.
     */
    public function getRappresentante(Soggetto $soggetto, SismaDate $data): ?Legame
    {
        if ($soggetto->isPersonaFisica()) {
            return null;
        }
        $legami = iterator_to_array((new LegameModel($this->dataMapper))->getCollegati($soggetto));
        foreach ([RuoloLegame::legaleRappresentante, RuoloLegame::amministratoreUnico, RuoloLegame::presidenteCda, RuoloLegame::liquidatore] as $ruolo) {
            foreach ($legami as $legame) {
                if (($legame->ruolo === $ruolo) && $legame->isVigenteAl($data) && $legame->soggettoCollegato->isPersonaFisica()) {
                    return $legame;
                }
            }
        }
        return null;
    }
}
