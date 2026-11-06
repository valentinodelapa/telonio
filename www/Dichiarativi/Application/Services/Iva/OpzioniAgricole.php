<?php

namespace Dichiarativi\Application\Services\Iva;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\RegimeDetrazioneIva;
use Contabilita\Application\Services\EsoneroAgricolo;
use Contabilita\Application\Services\RegimeIva;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Opzioni e revoche del settore agricolo nel quadro VO, dal confronto dei
 * dati fiscali dell'anno con quelli del precedente: rinuncia all'esonero
 * (VO3, casella 1), applicazione dell'imposta nei modi ordinari per l'art. 34
 * e l'art. 34-bis (VO3, caselle 3-6), associazioni (VO31), agriturismo
 * (VO32), enoturismo (VO35) e oleoturismo (VO36). Le opzioni vincolano per
 * almeno un triennio: una revoca anticipata è segnalata.
 *
 * Il ritorno all'esonero (VO3, casella 2) non si distingue dal rientro nei
 * limiti senza che ci fosse stata una rinuncia: resta un avviso.
 */
class OpzioniAgricole
{

    public const VINCOLO_ANNI = 3;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return string[] avvisi
     */
    public function proponi(QuadriDichiarazione $quadri, Soggetto $soggetto, int $anno): array
    {
        $regime = new RegimeIva($this->dataMapper);
        $corrente = $regime->getDatiFiscali($soggetto, $anno);
        if ($corrente === null) {
            return [];
        }
        $precedente = $regime->getDatiFiscali($soggetto, $anno - 1);
        $esonerato = $precedente?->regimeDetrazioneIva === RegimeDetrazioneIva::agricoloEsonerato;
        $volume = $esonerato ? (new EsoneroAgricolo($this->dataMapper))->getVolumi($soggetto, $anno - 1)['totale'] : 0.0;
        $esito = self::calcola($corrente, $precedente, $volume, $anno);
        foreach ($esito['caselle'] as $casella) {
            $quadri->proponi($casella, true);
        }
        $avvisi = $esito['avvisi'];
        foreach ($esito['revocate'] as $opzione) {
            $inizio = $this->getInizioOpzione($regime, $soggetto, $anno, $opzione);
            if ($anno - $inizio < self::VINCOLO_ANNI) {
                $avvisi[] = sprintf('Quadro VO: revoca dell\'opzione "%s" applicata dal %d, prima del triennio minimo.', $opzione->getLabel(), $inizio);
            }
        }
        return $avvisi;
    }

    /**
     * @return array{caselle: string[], revocate: RegimeDetrazioneIva[], avvisi: string[]}
     */
    public static function calcola(DatiFiscali $corrente, ?DatiFiscali $precedente, float $volumePrecedente, int $anno): array
    {
        $esito = ['caselle' => [], 'revocate' => [], 'avvisi' => []];
        $opzioni = $corrente->getOpzioniOrdinarie();
        if ($precedente === null) {
            if ((count($opzioni) > 0) && ($corrente->dataInizioAttivita?->format('Y') !== (string) $anno)) {
                $esito['avvisi'][] = sprintf('Quadro VO: dati fiscali senza la versione del %d, opzioni per i modi ordinari da verificare a mano.', $anno - 1);
                return $esito;
            }
            $prima = [];
        } else {
            $prima = $precedente->getOpzioniOrdinarie();
        }
        foreach (RegimeDetrazioneIva::cases() as $opzione) {
            $caselle = $opzione->getCaselleOpzione();
            if ($caselle === null) {
                continue;
            }
            if (in_array($opzione, $opzioni, true) && (in_array($opzione, $prima, true) === false)) {
                $esito['caselle'][] = $caselle['opzione'];
            } elseif (in_array($opzione, $prima, true) && (in_array($opzione, $opzioni, true) === false)) {
                $esito['caselle'][] = $caselle['revoca'];
                $esito['revocate'][] = $opzione;
            }
        }
        self::verificaPassaggio($esito, $corrente, $precedente, $volumePrecedente, $opzioni, $anno);
        return $esito;
    }

    /**
     * Passaggi di regime tra un anno e l'altro: la rinuncia all'esonero è
     * tale solo nei limiti di volume, oltre i quali l'esonero non spetta.
     */
    private static function verificaPassaggio(array &$esito, DatiFiscali $corrente, ?DatiFiscali $precedente, float $volumePrecedente, array $opzioni, int $anno): void
    {
        $prima = $precedente?->regimeDetrazioneIva;
        $ora = $corrente->regimeDetrazioneIva;
        if (($prima === null) || ($prima === $ora)) {
            return;
        }
        if (($prima === RegimeDetrazioneIva::agricolo) && ($ora === RegimeDetrazioneIva::agricoloEsonerato)) {
            $esito['avvisi'][] = sprintf('Quadro VO: passaggio all\'esonero dal %d. Se è la revoca di una rinuncia barrare la casella 2 del rigo VO3.', $anno);
            return;
        }
        $esonerato = $prima === RegimeDetrazioneIva::agricoloEsonerato;
        $lasciato = $esonerato ? RegimeDetrazioneIva::agricolo : $prima;
        $opzioneIndicata = in_array($lasciato, $opzioni, true);
        if ($esonerato && (($ora === RegimeDetrazioneIva::agricolo) || $opzioneIndicata) && ($volumePrecedente <= EsoneroAgricolo::LIMITE_VOLUME)) {
            $esito['caselle'][] = 'VO003001';
        } elseif (($ora === null) && ($opzioneIndicata === false)) {
            $esito['avvisi'][] = sprintf('Quadro VO: regime "%s" lasciato dal %d senza l\'opzione per i modi ordinari nei dati fiscali: indicarla se è stata esercitata.', $prima->getLabel(), $anno);
        }
    }

    /**
     * Primo anno di un'opzione revocata, cercato fino al vincolo minimo.
     */
    private function getInizioOpzione(RegimeIva $regime, Soggetto $soggetto, int $anno, RegimeDetrazioneIva $opzione): int
    {
        $inizio = $anno - 1;
        while (($anno - $inizio < self::VINCOLO_ANNI) && in_array($opzione, $regime->getDatiFiscali($soggetto, $inizio - 1)?->getOpzioniOrdinarie() ?? [], true)) {
            $inizio--;
        }
        return $inizio;
    }
}
