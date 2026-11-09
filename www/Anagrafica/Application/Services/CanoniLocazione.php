<?php

namespace Anagrafica\Application\Services;

use Anagrafica\Application\Entities\ContrattoLocazione;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\TipoContrattoLocazione;
use Anagrafica\Application\Models\DatiCatastaliModel;
use Anagrafica\Application\Models\PertinenzaLocataModel;
use Anagrafica\Application\Models\VariazioneCanoneModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Canone di un contratto di locazione in un periodo e sua ripartizione tra
 * l'immobile e le pertinenze locate insieme.
 *
 * - Il canone annuo vale fino alla prima variazione, poi quello della
 *   variazione; nel periodo si conta in proporzione ai giorni (anno di 365
 *   giorni, istruzioni del quadro RB, col. 6: "in proporzione ai giorni").
 * - Per le locazioni brevi il canone del contratto è il corrispettivo lordo
 *   dell'intero soggiorno: nel periodo se ne conta la parte dei giorni che
 *   vi ricadono (esempio delle istruzioni: 980 / 14 x 8).
 * - Le pertinenze con rendita autonoma hanno la quota proporzionale alla
 *   rendita catastale; nelle locazioni brevi il canone va tutto
 *   all'abitazione.
 */
class CanoniLocazione
{

    public function __construct(private DataMapper $dataMapper)
    {

    }

    public function getCanonePeriodo(ContrattoLocazione $contratto, \DateTimeInterface $dal, \DateTimeInterface $al): float
    {
        $inizio = max($dal->format('Y-m-d'), $contratto->dataInizio->format('Y-m-d'));
        $fine = min($al->format('Y-m-d'), $contratto->dataFine?->format('Y-m-d') ?? $al->format('Y-m-d'));
        if ($inizio > $fine) {
            return 0.0;
        }
        if ($contratto->tipo === TipoContrattoLocazione::locazioneBreve) {
            $durata = self::giorni($contratto->dataInizio->format('Y-m-d'), $contratto->dataFine?->format('Y-m-d') ?? $contratto->dataInizio->format('Y-m-d'));
            return round($contratto->canoneAnnuo * self::giorni($inizio, $fine) / max(1, $durata), 2);
        }
        $tratti = [[$contratto->dataInizio->format('Y-m-d'), $contratto->canoneAnnuo]];
        foreach ((new VariazioneCanoneModel($this->dataMapper))->getElenco($contratto) as $variazione) {
            $tratti[] = [$variazione->validoDal->format('Y-m-d'), $variazione->canoneAnnuo];
        }
        $totale = 0.0;
        foreach ($tratti as $indice => [$decorrenza, $canone]) {
            $trattoFine = isset($tratti[$indice + 1]) ? (new \DateTimeImmutable($tratti[$indice + 1][0]))->modify('-1 day')->format('Y-m-d') : $fine;
            $da = max($inizio, $decorrenza);
            $a = min($fine, $trattoFine);
            if ($da <= $a) {
                $totale += $canone * self::giorni($da, $a) / 365;
            }
        }
        return round($totale, 2);
    }

    /**
     * Quote del canone per immobile (id => frazione): con le pertinenze in
     * proporzione alle rendite vigenti alla data, senza rendite in parti
     * uguali.
     *
     * @return array<int, float>
     */
    public function getRipartizione(ContrattoLocazione $contratto, \DateTimeInterface $data): array
    {
        $immobili = [$contratto->immobile];
        foreach ((new PertinenzaLocataModel($this->dataMapper))->getByContrattoLocazione($contratto) as $pertinenza) {
            $immobili[] = $pertinenza->immobile;
        }
        if ((count($immobili) === 1) || ($contratto->tipo === TipoContrattoLocazione::locazioneBreve)) {
            $quote = array_fill_keys(array_map(fn(Immobile $immobile) => $immobile->id, $immobili), 0.0);
            $quote[$contratto->immobile->id] = 1.0;
            return $quote;
        }
        $datiCatastaliModel = new DatiCatastaliModel($this->dataMapper);
        $rendite = [];
        foreach ($immobili as $immobile) {
            $rendite[$immobile->id] = (float) ($datiCatastaliModel->getVigenteAl($immobile, $data)?->rendita ?? 0.0);
        }
        $totale = array_sum($rendite);
        return array_map(fn(float $rendita) => $totale > 0 ? $rendita / $totale : 1 / count($rendite), $rendite);
    }

    private static function giorni(string $dal, string $al): int
    {
        return (new \DateTimeImmutable($dal))->diff(new \DateTimeImmutable($al))->days + 1;
    }
}
