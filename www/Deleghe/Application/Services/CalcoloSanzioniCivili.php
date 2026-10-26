<?php

namespace Deleghe\Application\Services;

use Deleghe\Application\Enumerations\RegimeSanzioneCivile;
use CommSuite\Application\HelperClasses\Calendario;
use Deleghe\Application\Models\TassoRiferimentoModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Sanzioni civili per omesso o ritardato pagamento di contributi INPS e
 * premi INAIL (art. 116, c. 8, L. 388/2000): in ragione d'anno, giorno per
 * giorno dal giorno successivo alla scadenza al pagamento, su base 365, con
 * il tasso di riferimento in vigore in ciascun giorno più la maggiorazione
 * del regime (RegimeSanzioneCivile), entro il tetto in percentuale dei
 * contributi.
 */
class CalcoloSanzioniCivili
{

    private const GIORNI_ANNO = 365;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return array{giorni: int, sanzione: float, tetto: bool, periodi: array}
     */
    public function calcola(float $contributi, SismaDate $scadenza, SismaDate $pagamento, RegimeSanzioneCivile $regime, bool $spontaneo): array
    {
        $giorni = Calendario::giorniTra($scadenza, $pagamento);
        if ($giorni <= 0) {
            throw new \InvalidArgumentException('Il pagamento non è in ritardo rispetto alla scadenza.');
        }
        $maggiorazione = $regime->getMaggiorazione($spontaneo && ($giorni <= RegimeSanzioneCivile::GIORNI_RIDUZIONE_SPONTANEA));
        $periodi = $maggiorazione === null
                ? [$this->periodo($scadenza, $pagamento, $regime->getMisuraFissa(), $contributi)]
                : $this->periodiTasso($scadenza, $pagamento, $maggiorazione, $contributi);
        $sanzione = array_sum(array_column($periodi, 'esatta'));
        $tetto = round($contributi * $regime->getTetto() / 100, 2);
        return [
            'giorni' => $giorni,
            'sanzione' => round(min($sanzione, $tetto), 2),
            'tetto' => $sanzione > $tetto,
            'periodi' => array_map(fn($periodo) => array_diff_key($periodo, ['esatta' => true]), $periodi),
        ];
    }

    private function periodiTasso(SismaDate $scadenza, SismaDate $pagamento, float $maggiorazione, float $contributi): array
    {
        $tassi = (new TassoRiferimentoModel($this->dataMapper))->getSerie();
        $inizio = (new \DateTimeImmutable($scadenza->format('Y-m-d')))->modify('+1 day');
        $fine = new \DateTimeImmutable($pagamento->format('Y-m-d'));
        if (($tassi === []) || (new \DateTimeImmutable($tassi[0]->validoDal->format('Y-m-d')) > $inizio)) {
            throw new \UnexpectedValueException('Tasso di riferimento non disponibile per il periodo: aggiornare le tabelle con sisma tabelle-f24.');
        }
        $periodi = [];
        foreach ($tassi as $indice => $tasso) {
            $dal = max($inizio, new \DateTimeImmutable($tasso->validoDal->format('Y-m-d')));
            $al = isset($tassi[$indice + 1]) ? min($fine, (new \DateTimeImmutable($tassi[$indice + 1]->validoDal->format('Y-m-d')))->modify('-1 day')) : $fine;
            if ($dal <= $al) {
                $periodi[] = $this->periodo(new SismaDate($dal->modify('-1 day')->format('Y-m-d')), new SismaDate($al->format('Y-m-d')), $tasso->tasso + $maggiorazione, $contributi);
            }
        }
        return $periodi;
    }

    /**
     * Periodo dal giorno successivo a $da fino ad $a incluso.
     */
    private function periodo(SismaDate $da, SismaDate $a, float $tassoAnnuo, float $contributi): array
    {
        $giorni = Calendario::giorniTra($da, $a);
        $esatta = $contributi * $tassoAnnuo / 100 * $giorni / self::GIORNI_ANNO;
        return [
            'dal' => (new \DateTimeImmutable($da->format('Y-m-d')))->modify('+1 day')->format('Y-m-d'),
            'al' => $a->format('Y-m-d'),
            'giorni' => $giorni,
            'tasso' => round($tassoAnnuo, 3),
            'sanzione' => round($esatta, 2),
            'esatta' => $esatta,
        ];
    }
}
