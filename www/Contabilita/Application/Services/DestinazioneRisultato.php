<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Entities\EsercizioContabile;
use Contabilita\Application\Entities\PianoConti;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Models\RegistrazioneContabileModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Destinazione del risultato dell'esercizio, nell'esercizio successivo
 * alla data della delibera (approvazione del bilancio entro 120 giorni
 * dalla chiusura, art. 2364, c. 2, c.c.):
 *
 * - società di capitali: utile alla riserva legale per il 5% finché non
 *   raggiunge un quinto del capitale sociale (art. 2430 c.c.), il resto alla
 *   riserva straordinaria (o, a scelta, a nuovo o ai soci, indicando i
 *   conti); perdita portata a nuovo;
 * - imprese individuali e società di persone: utile o perdita al capitale
 *   netto.
 *
 * La scrittura (origine "Destinazione") chiude il conto dell'utile o della
 * perdita riaperto; si sostituisce finché l'esercizio è aperto e il
 * giornale non è stampato in definitiva alla sua data.
 */
class DestinazioneRisultato
{

    public const ORIGINE = 'Destinazione';
    public const QUOTA_RISERVA_LEGALE = 0.05;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public static function getRiferimento(int $anno): string
    {
        return 'destinazione-' . $anno;
    }

    /**
     * Riserva legale proposta: il 5% dell'utile fino a un quinto del
     * capitale (senza capitale in contabilità, il 5%).
     */
    public static function getRiservaLegale(float $utile, float $capitale, float $riserva): float
    {
        $quota = round($utile * self::QUOTA_RISERVA_LEGALE, 2);
        if ($capitale <= 0) {
            return $quota;
        }
        return round(max(0.0, min($quota, $capitale / 5 - $riserva)), 2);
    }

    /**
     * Risultato da destinare e righe proposte (conto e importo).
     *
     * @return array{risultato: float, righe: array<int, array{conto: ?Conto, importo: float}>}
     */
    public function proponi(Soggetto $soggetto, EsercizioContabile $esercizio): array
    {
        $piano = $this->getPiano($soggetto);
        $chiusura = new ChiusuraEsercizio($this->dataMapper);
        $saldi = $chiusura->getSaldi($soggetto, $esercizio);
        $risultato = ChiusuraEsercizio::getRisultato($saldi);
        $conti = new ContoModel($this->dataMapper);
        $capitali = $soggetto->tipoSoggetto === TipoSoggetto::societaCapitali;
        if ($risultato < 0) {
            return ['risultato' => $risultato, 'righe' => [['conto' => $conti->getPerFunzione($piano, $capitali ? FunzioneConto::utiliANuovo : FunzioneConto::capitaleNetto), 'importo' => -$risultato]]];
        }
        if ($capitali === false) {
            return ['risultato' => $risultato, 'righe' => [['conto' => $conti->getPerFunzione($piano, FunzioneConto::capitaleNetto), 'importo' => $risultato]]];
        }
        $saldo = function (FunzioneConto $funzione) use ($conti, $piano, $saldi): float {
            $conto = $conti->getPerFunzione($piano, $funzione);
            return $conto === null ? 0.0 : -($saldi[$conto->id]['saldo'] ?? 0.0);
        };
        $legale = self::getRiservaLegale($risultato, $saldo(FunzioneConto::capitaleSociale), $saldo(FunzioneConto::riservaLegale));
        $righe = [];
        if ($legale > 0) {
            $righe[] = ['conto' => $conti->getPerFunzione($piano, FunzioneConto::riservaLegale), 'importo' => $legale];
        }
        $righe[] = ['conto' => $conti->getPerFunzione($piano, FunzioneConto::riservaStraordinaria), 'importo' => round($risultato - $legale, 2)];
        return ['risultato' => $risultato, 'righe' => $righe];
    }

    /**
     * Registra (o sostituisce) la destinazione alla data, nell'esercizio
     * che la comprende.
     *
     * @param array<int, array{conto: Conto, importo: float}> $righe
     * @throws \DomainException se gli importi non quadrano con il risultato o la data non è in un esercizio successivo aperto
     */
    public function registra(Soggetto $soggetto, EsercizioContabile $esercizio, \DateTimeInterface $data, array $righe): ?RegistrazioneContabile
    {
        $piano = $this->getPiano($soggetto);
        $risultato = ChiusuraEsercizio::getRisultato((new ChiusuraEsercizio($this->dataMapper))->getSaldi($soggetto, $esercizio));
        $righe = array_values(array_filter($righe, fn(array $riga) => round($riga['importo'], 2) != 0));
        if ($risultato == 0) {
            throw new \DomainException('Nessun risultato da destinare.');
        }
        $totale = round(array_sum(array_column($righe, 'importo')), 2);
        if (abs($totale - abs($risultato)) > 0.005) {
            throw new \DomainException(sprintf('Gli importi destinati (€ %s) devono essere pari al risultato (€ %s).', number_format($totale, 2, ',', '.'), number_format(abs($risultato), 2, ',', '.')));
        }
        foreach ($righe as $riga) {
            if (($riga['importo'] < 0) || ($riga['conto']->natura->isPatrimoniale() === false) || ($riga['conto']->pianoConti->id !== $piano->id)) {
                throw new \DomainException('Indicare importi positivi su conti patrimoniali del piano dell\'azienda.');
            }
        }
        if ($data->format('Y-m-d') <= $esercizio->dataFine->format('Y-m-d')) {
            throw new \DomainException('La destinazione si registra nell\'esercizio successivo, dopo il ' . $esercizio->dataFine->format('d/m/Y') . '.');
        }
        $successivo = (new \Contabilita\Application\Models\EsercizioContabileModel($this->dataMapper))->getPerData($soggetto, $data)
                ?? throw new \DomainException('Nessun esercizio aperto al ' . $data->format('d/m/Y') . '.');
        Ammortamenti::verificaScrivibile($this->dataMapper, $soggetto, $successivo, $data);
        $funzione = $risultato > 0 ? FunzioneConto::utileEsercizio : FunzioneConto::perditaEsercizio;
        $contoRisultato = (new ContoModel($this->dataMapper))->getPerFunzione($piano, $funzione)
                ?? throw new \DomainException('Nel piano dei conti manca il conto con funzione "' . $funzione->getLabel() . '".');
        $utile = $risultato > 0;
        $scrittura = [Ammortamenti::riga($this->dataMapper, $contoRisultato, $utile ? $risultato : 0.0, $utile ? 0.0 : -$risultato)];
        foreach ($righe as $riga) {
            $scrittura[] = Ammortamenti::riga($this->dataMapper, $riga['conto'], $utile ? 0.0 : $riga['importo'], $utile ? $riga['importo'] : 0.0);
        }
        return Ammortamenti::sostituisci($this->dataMapper, $soggetto, $successivo, self::ORIGINE, self::getRiferimento($esercizio->anno), $data,
                        ($utile ? 'Destinazione dell\'utile' : 'Destinazione della perdita') . ' dell\'esercizio ' . $esercizio->getEtichetta(), $scrittura);
    }

    public function getRegistrata(Soggetto $soggetto, int $anno): ?RegistrazioneContabile
    {
        return (new RegistrazioneContabileModel($this->dataMapper))->getGenerata($soggetto, self::ORIGINE, self::getRiferimento($anno));
    }

    private function getPiano(Soggetto $soggetto): PianoConti
    {
        return (new PianoContiModel($this->dataMapper))->getPerAzienda($soggetto) ?? throw new \DomainException('Piano dei conti dell\'azienda mancante.');
    }
}
