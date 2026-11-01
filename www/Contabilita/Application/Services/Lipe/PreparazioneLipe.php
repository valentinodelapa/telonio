<?php

namespace Contabilita\Application\Services\Lipe;

use Anagrafica\Application\Entities\Legame;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Models\LegameModel;
use Contabilita\Application\Entities\ComunicazioneLipe;
use Contabilita\Application\Entities\LiquidazioneIva;
use Contabilita\Application\Entities\ModuloLipe;
use Contabilita\Application\Enumerations\CodiceCaricaIva;
use Contabilita\Application\Enumerations\ImpegnoPresentazione;
use Contabilita\Application\Enumerations\MetodoAccontoIva;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\AccontoIvaModel;
use Contabilita\Application\Models\ComunicazioneLipeModel;
use Contabilita\Application\Models\LiquidazioneIvaModel;
use Contabilita\Application\Models\ModuloLipeModel;
use Contabilita\Application\Models\RigaIvaModel;
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
        foreach ($periodi as $indice => $periodo) {
            $modulo = $this->preparaModulo($comunicazione, $liquidazioni[$periodo], $avvisi);
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
        } else {
            $modulo->trimestre = $periodo === 4 ? ModuloLipe::QUARTO_TRIMESTRE_TRIMESTRALI : $periodo;
        }
        [$modulo->operazioniAttive, $modulo->operazioniPassive] = $this->getTotaliOperazioni($liquidazione);
        $modulo->ivaEsigibile = $liquidazione->ivaEsigibile;
        $modulo->ivaDetratta = $liquidazione->ivaDetraibile;
        $descrizione = $liquidazione->getDescrizionePeriodo();
        // VP7: non per gennaio, dicembre, primo e quarto trimestre (il debito di novembre e del terzo trimestre non si rinvia)
        $debitoAmmesso = $mensile ? !in_array($periodo, [1, 12], true) : !in_array($periodo, [1, 4], true);
        $modulo->debitoPrecedente = $debitoAmmesso ? $liquidazione->debitoPrecedente : 0.0;
        // VP8: il credito dell'anno precedente si indica nel VP9, non nel primo periodo come credito del periodo precedente
        $modulo->creditoPrecedente = $periodo > 1 ? $liquidazione->creditoPrecedente : 0.0;
        foreach (['debitoPrecedente' => 'VP7', 'creditoPrecedente' => 'VP8'] as $campo => $rigo) {
            if ($modulo->$campo != $liquidazione->$campo) {
                $avvisi[] = $descrizione . ': importo della liquidazione non ammesso nel rigo ' . $rigo . ' per il periodo, omesso.';
            }
        }
        $modulo->creditoAnnoPrecedente = $liquidazione->creditoAnnoPrecedente;
        $modulo->versamentiAuto = $liquidazione->versamentiAuto;
        $modulo->creditiImposta = $modulo->isQuartoTrimestreTrimestrali() ? 0.0 : $liquidazione->creditiImposta;
        $modulo->interessi = $mensile || $modulo->isQuartoTrimestreTrimestrali() ? 0.0 : $liquidazione->interessi;
        if (($liquidazione->acconto > 0) && PeriodiIva::isUltimoPeriodo($liquidazione->periodicita, $periodo)) {
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
     * Righi VP2 e VP3 del periodo della liquidazione.
     *
     * @return array{0: float, 1: float}
     */
    public function getTotaliOperazioni(LiquidazioneIva $liquidazione): array
    {
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
