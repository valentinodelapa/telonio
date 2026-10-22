<?php

namespace Deleghe\Application\Services;

use Deleghe\Application\Enumerations\CircostanzaRavvedimento;
use Deleghe\Application\HelperClasses\Calendario;
use Deleghe\Application\Models\TassoInteresseLegaleModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Ravvedimento operoso di un versamento omesso o tardivo.
 *
 * Sanzione (art. 13, c. 1, D.Lgs. 471/1997): 30% dell'importo non versato,
 * 25% per le violazioni commesse dal 1° settembre 2024 (D.Lgs. 87/2024,
 * art. 5); ridotta alla metà per ritardi fino a 90 giorni e, fino a 15
 * giorni, a un quindicesimo di questa per ogni giorno di ritardo.
 *
 * Riduzione da ravvedimento (art. 13, c. 1, D.Lgs. 472/1997):
 * - lett. a) 1/10 entro 30 giorni dalla scadenza;
 * - lett. a-bis) 1/9 entro 90 giorni;
 * - lett. b) 1/8 entro il termine della dichiarazione relativa all'anno
 *   della violazione (o un anno, senza dichiarazione periodica);
 * - lett. b-bis) 1/7 oltre; per le violazioni fino al 31/08/2024: 1/7 entro
 *   il termine della dichiarazione dell'anno successivo (o due anni) e,
 *   oltre, 1/6 (lett. b-ter nel testo previgente).
 * Dopo uno schema d'atto o un verbale di constatazione la riduzione è
 * fissa (CircostanzaRavvedimento): 1/6, 1/5 o 1/4 (lett. b-ter, b-quater,
 * b-quinquies); per le violazioni anteriori alla riforma solo 1/5 dopo il
 * verbale.
 *
 * Interessi al tasso legale, giorno per giorno dal giorno successivo alla
 * scadenza a quello del versamento (c. 2), su base 365.
 */
class CalcoloRavvedimento
{

    public const DECORRENZA_RIFORMA = '2024-09-01';
    private const GIORNI_ANNO = 365;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @param SismaDate|null $termineDichiarazioneSuccessiva solo violazioni
     *        anteriori alla riforma; default un anno dopo il termine
     * @return array{giorni: int, riforma: bool, percentuale: float, riduzione: int, sanzione: float, interessi: float, periodi: array}
     */
    public function calcola(float $importo, SismaDate $scadenza, SismaDate $versamento, SismaDate $termineDichiarazione, ?SismaDate $termineDichiarazioneSuccessiva = null,
            CircostanzaRavvedimento $circostanza = CircostanzaRavvedimento::nessuna): array
    {
        $giorni = Calendario::giorniTra($scadenza, $versamento);
        if ($giorni <= 0) {
            throw new \InvalidArgumentException('Il versamento non è in ritardo rispetto alla scadenza.');
        }
        $riforma = $scadenza->format('Y-m-d') >= self::DECORRENZA_RIFORMA;
        $base = $riforma ? 25.0 : 30.0;
        $percentuale = match (true) {
            $giorni <= 15 => $base / 2 / 15 * $giorni,
            $giorni <= 90 => $base / 2,
            default => $base,
        };
        $termineSuccessivo = $termineDichiarazioneSuccessiva ?? new SismaDate((new \DateTimeImmutable($termineDichiarazione->format('Y-m-d')))->modify('+1 year')->format('Y-m-d'));
        $riduzione = $circostanza->getRiduzione($riforma) ?? match (true) {
            $giorni <= 30 => 10,
            $giorni <= 90 => 9,
            $versamento->format('Y-m-d') <= $termineDichiarazione->format('Y-m-d') => 8,
            $riforma, $versamento->format('Y-m-d') <= $termineSuccessivo->format('Y-m-d') => 7,
            default => 6,
        };
        [$interessi, $periodi] = $this->calcolaInteressi($importo, $scadenza, $versamento);
        return [
            'giorni' => $giorni,
            'riforma' => $riforma,
            'percentuale' => round($percentuale, 6),
            'riduzione' => $riduzione,
            'sanzione' => round($importo * $percentuale / 100 / $riduzione, 2),
            'interessi' => $interessi,
            'periodi' => $periodi,
        ];
    }

    /**
     * Interessi per i periodi di vigenza dei tassi: somma esatta, arrotondata
     * al centesimo alla fine. Usato anche dal modulo Successioni (sanzioni e
     * interessi della dichiarazione presentata in ritardo).
     *
     * @return array{0: float, 1: array}
     */
    public function calcolaInteressi(float $importo, SismaDate $scadenza, SismaDate $versamento): array
    {
        $tassi = (new TassoInteresseLegaleModel($this->dataMapper))->getSerie();
        $inizio = (new \DateTimeImmutable($scadenza->format('Y-m-d')))->modify('+1 day');
        $fine = new \DateTimeImmutable($versamento->format('Y-m-d'));
        $totale = 0.0;
        $periodi = [];
        foreach ($tassi as $indice => $tasso) {
            $dal = max($inizio, new \DateTimeImmutable($tasso->validoDal->format('Y-m-d')));
            $al = isset($tassi[$indice + 1]) ? min($fine, (new \DateTimeImmutable($tassi[$indice + 1]->validoDal->format('Y-m-d')))->modify('-1 day')) : $fine;
            if ($dal > $al) {
                continue;
            }
            $giorni = (int) $dal->diff($al)->format('%a') + 1;
            $quota = $importo * $tasso->tasso / 100 * $giorni / self::GIORNI_ANNO;
            $totale += $quota;
            $periodi[] = ['dal' => $dal->format('Y-m-d'), 'al' => $al->format('Y-m-d'), 'giorni' => $giorni, 'tasso' => $tasso->tasso, 'interessi' => round($quota, 2)];
        }
        if (($tassi === []) || (new \DateTimeImmutable($tassi[0]->validoDal->format('Y-m-d')) > $inizio)) {
            throw new \UnexpectedValueException('Tassi di interesse legale non disponibili per il periodo: aggiornare le tabelle con sisma tabelle-f24.');
        }
        return [round($totale, 2), $periodi];
    }
}
