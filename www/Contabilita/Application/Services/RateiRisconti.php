<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Entities\EsercizioContabile;
use Contabilita\Application\Entities\PianoConti;
use Contabilita\Application\Entities\RateoRisconto;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\EsercizioContabileModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Models\RateoRiscontoModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Ratei e risconti dell'esercizio (art. 2424-bis, c. 6, c.c.): quote a
 * giorni dei costi e ricavi a cavallo della fine dell'esercizio.
 *
 * - Già registrati nell'esercizio: la parte di competenza successiva è un
 *   risconto attivo (costi: dare Risconti attivi, avere il costo) o
 *   passivo (ricavi: dare il ricavo, avere Risconti passivi).
 * - Da registrare nell'esercizio successivo: la parte maturata è un rateo
 *   passivo (costi: dare il costo, avere Ratei passivi) o attivo (ricavi:
 *   dare Ratei attivi, avere il ricavo).
 *
 * La scrittura di assestamento è all'ultimo giorno dell'esercizio, lo
 * storno al primo giorno dell'esercizio successivo, se aperto (origine
 * "Assestamento"); si sostituiscono rielaborando.
 */
class RateiRisconti
{

    public const ORIGINE = 'Assestamento';

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Quota di competenza da rinviare (risconto) o da anticipare (rateo)
     * nell'esercizio, a giorni.
     */
    public static function quota(RateoRisconto $voce, EsercizioContabile $esercizio): float
    {
        $dal = new \DateTimeImmutable($voce->competenzaDal->format('Y-m-d'));
        $al = new \DateTimeImmutable($voce->competenzaAl->format('Y-m-d'));
        $fine = new \DateTimeImmutable($esercizio->dataFine->format('Y-m-d'));
        $giorni = $dal->diff($al)->days + 1;
        if (($giorni <= 0) || ($al < $dal)) {
            return 0.0;
        }
        if ($voce->registratoNellEsercizio) {
            // giorni dopo la fine dell'esercizio
            $successivi = $al > $fine ? max($dal, $fine->modify('+1 day'))->diff($al)->days + 1 : 0;
            return round($voce->importo * $successivi / $giorni, 2);
        }
        $maturati = $dal <= $fine ? $dal->diff(min($al, $fine))->days + 1 : 0;
        return round($voce->importo * $maturati / $giorni, 2);
    }

    /**
     * Funzione del conto di ratei o risconti della voce.
     */
    public static function getFunzione(RateoRisconto $voce): FunzioneConto
    {
        $costo = $voce->conto->natura === NaturaConto::costo;
        if ($voce->registratoNellEsercizio) {
            return $costo ? FunzioneConto::riscontiAttivi : FunzioneConto::riscontiPassivi;
        }
        return $costo ? FunzioneConto::rateiPassivi : FunzioneConto::rateiAttivi;
    }

    /**
     * Voci dell'esercizio con la quota e il conto dell'assestamento.
     *
     * @return array<int, array{voce: RateoRisconto, quota: float, funzione: FunzioneConto}>
     */
    public function calcola(Soggetto $soggetto, EsercizioContabile $esercizio): array
    {
        $voci = [];
        foreach ((new RateoRiscontoModel($this->dataMapper))->getPerAnno($soggetto, $esercizio->anno) as $voce) {
            $voci[] = ['voce' => $voce, 'quota' => self::quota($voce, $esercizio), 'funzione' => self::getFunzione($voce)];
        }
        return $voci;
    }

    public static function getRiferimento(int $anno, bool $storno = false): string
    {
        return ($storno ? 'storno-ratei-risconti-' : 'ratei-risconti-') . $anno;
    }

    /**
     * Registra (o sostituisce) assestamento e storno.
     *
     * @param string[] $avvisi
     * @throws \DomainException se manca un conto di ratei o risconti o una scrittura non si può sostituire
     */
    public function registra(Soggetto $soggetto, EsercizioContabile $esercizio, array &$avvisi = []): void
    {
        $piano = (new PianoContiModel($this->dataMapper))->getPerAzienda($soggetto) ?? throw new \DomainException('Piano dei conti dell\'azienda mancante.');
        // tutte le scritture o nessuna: esercizio aperto e giornale non stampato alle date
        $successivo = $this->getSuccessivo($soggetto, $esercizio);
        Ammortamenti::verificaScrivibile($this->dataMapper, $soggetto, $esercizio, $esercizio->dataFine);
        if ($successivo !== null) {
            Ammortamenti::verificaScrivibile($this->dataMapper, $soggetto, $successivo, $successivo->dataInizio);
        }
        $assestamento = $storno = [];
        foreach ($this->calcola($soggetto, $esercizio) as ['voce' => $voce, 'quota' => $quota, 'funzione' => $funzione]) {
            if ($quota <= 0) {
                continue;
            }
            $contoRateo = $this->getConto($piano, $funzione);
            // risconto attivo e rateo attivo in dare; risconto passivo e rateo passivo in avere
            $rateoInDare = in_array($funzione, [FunzioneConto::riscontiAttivi, FunzioneConto::rateiAttivi], true);
            $descrizione = $funzione->getLabel() . ': ' . $voce->descrizione;
            $assestamento[] = Ammortamenti::riga($this->dataMapper, $contoRateo, $rateoInDare ? $quota : 0.0, $rateoInDare ? 0.0 : $quota, $descrizione);
            $assestamento[] = Ammortamenti::riga($this->dataMapper, $voce->conto, $rateoInDare ? 0.0 : $quota, $rateoInDare ? $quota : 0.0, $descrizione);
            $storno[] = Ammortamenti::riga($this->dataMapper, $contoRateo, $rateoInDare ? 0.0 : $quota, $rateoInDare ? $quota : 0.0, 'Storno ' . mb_strtolower($descrizione));
            $storno[] = Ammortamenti::riga($this->dataMapper, $voce->conto, $rateoInDare ? $quota : 0.0, $rateoInDare ? 0.0 : $quota, 'Storno ' . mb_strtolower($descrizione));
        }
        Ammortamenti::sostituisci($this->dataMapper, $soggetto, $esercizio, self::ORIGINE, self::getRiferimento($esercizio->anno), $esercizio->dataFine,
                'Ratei e risconti dell\'esercizio ' . $esercizio->getEtichetta(), $assestamento, $avvisi);
        if ($successivo === null) {
            if (count($storno) > 0) {
                $avvisi[] = 'Storno dei ratei e risconti non registrato: aprire l\'esercizio successivo e rielaborare.';
            }
            return;
        }
        Ammortamenti::sostituisci($this->dataMapper, $soggetto, $successivo, self::ORIGINE, self::getRiferimento($esercizio->anno, true), $successivo->dataInizio,
                'Storno dei ratei e risconti dell\'esercizio ' . $esercizio->getEtichetta(), $storno, $avvisi);
    }

    public function getSuccessivo(Soggetto $soggetto, EsercizioContabile $esercizio): ?EsercizioContabile
    {
        $giorno = (new \DateTimeImmutable($esercizio->dataFine->format('Y-m-d')))->modify('+1 day');
        return (new EsercizioContabileModel($this->dataMapper))->getPerData($soggetto, $giorno);
    }

    private function getConto(PianoConti $piano, FunzioneConto $funzione): Conto
    {
        return (new ContoModel($this->dataMapper))->getPerFunzione($piano, $funzione)
                ?? throw new \DomainException('Nel piano dei conti manca il conto con funzione "' . $funzione->getLabel() . '".');
    }
}
