<?php

namespace Successioni\Application\Services;

use Deleghe\Application\Services\CalcoloRavvedimento;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;
use Successioni\Application\Enumerations\TempisticaPagamento;

/**
 * Ravvedimento della dichiarazione presentata dopo il termine (art. 13
 * D.Lgs. 472/1997), per le violazioni dal 1° settembre 2024 (D.Lgs.
 * 87/2024):
 *
 * - tardivo pagamento delle imposte autoliquidate (quadro EF, sezione VI;
 *   istruzioni del modello): sanzione del 25% (12,5% entro 90 giorni, art.
 *   13 D.Lgs. 471/1997) ridotta allo 0,083% per giorno entro 15 giorni,
 *   1,25% entro 30, 1,39% entro 90, 3,12% entro un anno, 3,57% oltre, più
 *   gli interessi legali giorno per giorno. Le imposte ipotecaria,
 *   catastale, di bollo e la tassa ipotecaria scadono con il termine di
 *   presentazione; l'imposta di successione 90 giorni dopo;
 * - tardiva presentazione con imposta di successione dovuta (art. 50 TUS:
 *   45% entro 30 giorni, 120% oltre), ridotta a 1/10 della sanzione
 *   minima entro 30 giorni (4,5%), 1/9 entro 90 (13,33%), 1/8 entro un
 *   anno (15%), 1/7 oltre (17,14%). Con il pagamento contestuale va nella
 *   sezione VI con l'imposta di successione; altrimenti si versa con F24
 *   (codice 1549, risoluzione n. 2/E del 2025).
 *
 * Le misure della tardiva presentazione seguono la prassi applicativa
 * (software di compilazione): da verificare con le istruzioni a ogni
 * aggiornamento.
 */
class Ravvedimento
{

    public const INIZIO_REGIME = '2024-09-01';

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Data in cui la dichiarazione è (o sarà) presentata e le imposte
     * addebitate.
     */
    public static function getDataPresentazione(DatiDichiarazione $dati): SismaDate
    {
        $dichiarazione = $dati->dichiarazione;
        return $dichiarazione->dataPresentazione ?? $dichiarazione->dataDichiarazione ?? new SismaDate();
    }

    public function calcola(DatiDichiarazione $dati, RisultatoLiquidazione $risultato): void
    {
        $dichiarazione = $dati->dichiarazione;
        $presentazione = self::getDataPresentazione($dati);
        $termine = $dichiarazione->getScadenza();
        $giorni = self::giorni($termine, $presentazione);
        if ($giorni <= 0) {
            return;
        }
        $risultato->giorniRitardo = $giorni;
        if ($dichiarazione->tipoDichiarazione->isSostitutiva()) {
            $risultato->avviso(sprintf('Dichiarazione sostitutiva presentata %d giorni dopo il termine: sanzioni e interessi eventualmente dovuti da indicare a mano.', $giorni));
            return;
        }
        if ($termine->format('Y-m-d') < self::INIZIO_REGIME) {
            $risultato->avviso('Termine di presentazione anteriore al 1° settembre 2024: sanzioni del regime precedente da calcolare a mano.');
            return;
        }
        $calcolo = new CalcoloRavvedimento($this->dataMapper);
        foreach (['ipotecaria' => $risultato->ipotecariaDaVersare, 'catastale' => $risultato->catastaleDaVersare, 'tassaIpotecaria' => $risultato->tassaIpotecaria, 'bollo' => $risultato->bollo] as $voce => $importo) {
            if ($importo > 0) {
                $risultato->sanzioni[$voce] = round($importo * self::getPercentualePagamento($giorni) / 100, 2);
                $risultato->interessi[$voce] = $calcolo->calcolaInteressi($importo, $termine, $presentazione)[0];
            }
        }
        if ($risultato->autoliquidazione && ($risultato->successioneImposta > 0)) {
            $risultato->sanzionePresentazione = round($risultato->successioneImposta * self::getPercentualePresentazione($giorni) / 100, 2);
            $contestuale = (new Liquidazione())->getSuccessioneConDichiarazione($dati, $risultato);
            $scadenzaPagamento = $dichiarazione->getScadenzaPagamento();
            $ritardoPagamento = self::giorni($scadenzaPagamento, $presentazione);
            $sanzione = $risultato->sanzionePresentazione;
            $interessi = 0.0;
            if (($contestuale > 0) && ($ritardoPagamento > 0)) {
                $sanzione += round($contestuale * self::getPercentualePagamento($ritardoPagamento) / 100, 2);
                $interessi = $calcolo->calcolaInteressi($contestuale, $scadenzaPagamento, $presentazione)[0];
            }
            if ($dichiarazione->tempisticaPagamento === TempisticaPagamento::contestuale) {
                $risultato->sanzioni['successione'] = round($sanzione, 2);
                $risultato->interessi['successione'] = $interessi;
                $risultato->sanzionePresentazione = 0.0;
            }
        }
        $risultato->avviso(sprintf('Dichiarazione presentata con %d giorni di ritardo: sanzioni ridotte e interessi del ravvedimento nel quadro EF, sezione VI%s.', $giorni,
                        $risultato->sanzionePresentazione > 0 ? '; sanzione per la tardiva presentazione con F24 dei beneficiari (codice 1549)' : ''));
    }

    /**
     * Sanzione ridotta in percentuale per il tardivo pagamento.
     */
    public static function getPercentualePagamento(int $giorni): float
    {
        return match (true) {
            $giorni <= 15 => round(25 / 2 / 15 / 10 * $giorni, 4),
            $giorni <= 30 => 1.25,
            $giorni <= 90 => 1.39,
            $giorni <= 365 => 3.12,
            default => 3.57,
        };
    }

    /**
     * Sanzione ridotta in percentuale per la tardiva presentazione.
     */
    public static function getPercentualePresentazione(int $giorni): float
    {
        return match (true) {
            $giorni <= 30 => 4.5,
            $giorni <= 90 => round(120 / 9, 2),
            $giorni <= 365 => 15.0,
            default => round(120 / 7, 2),
        };
    }

    public static function giorni(\DateTimeInterface $scadenza, \DateTimeInterface $data): int
    {
        $differenza = (new \DateTimeImmutable($scadenza->format('Y-m-d')))->diff(new \DateTimeImmutable($data->format('Y-m-d')));
        return $differenza->invert === 1 ? -$differenza->days : $differenza->days;
    }
}
