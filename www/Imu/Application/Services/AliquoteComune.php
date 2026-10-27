<?php

namespace Imu\Application\Services;

use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Telonio\Application\Entities\Studio;
use Imu\Application\Entities\AliquotaApplicata;
use Imu\Application\Entities\AliquotaDifferenziata;
use Imu\Application\Entities\ProspettoAliquote;
use Imu\Application\Enumerations\FattispecieImu;
use Imu\Application\Models\AliquotaDifferenziataModel;
use Imu\Application\Models\ProspettoAliquoteModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Aliquote di un comune per un anno, nell'ordine: aliquote inserite dallo
 * studio, prospetto pubblicato dal Dipartimento delle Finanze, e in
 * mancanza (art. 1, comma 767, L. 160/2019, testo vigente) le aliquote
 * vigenti nell'anno precedente (terzo periodo; art. 1, c. 169, L.
 * 296/2006). Dal 2025, primo anno di obbligo del prospetto (comma 757),
 * valgono solo le aliquote approvate con il prospetto: finché il comune non
 * ne pubblica un primo si applicano le aliquote di base dei commi 748-755
 * (quarto periodo), poi in mancanza del prospetto dell'anno quelle
 * dell'ultimo pubblicato (Dipartimento delle Finanze, decreto 6 novembre
 * 2025 e linee guida per il prospetto 2026).
 *
 * Nei comuni nati da fusione che applicano aliquote differenziate negli ex
 * comuni c'è un prospetto per ex comune: vale quello dell'ex comune
 * dell'immobile (CaratteristicheImu::$exComune).
 *
 * L'aliquota del singolo immobile è quella scelta dallo studio per l'anno
 * (AliquotaApplicata), quella differenziata le cui condizioni risultano
 * tutte verificate dai dati (CompatibilitaDifferenziate) o quella della
 * fattispecie di base.
 */
class AliquoteComune
{

    public const PRIMO_ANNO_PROSPETTO = 2025;
    private const ANNI_RIPIEGO = 5;

    /** @var array<string, array{prospetto: ?ProspettoAliquote, origine: string}> */
    private array $cache = [];

    /** @var array<int, AliquotaDifferenziata[]> */
    private array $differenziate = [];

    public function __construct(private Studio $studio, private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return array{prospetto: ?ProspettoAliquote, origine: string}
     */
    public function getProspetto(string $codiceCatastale, int $anno, ?string $exComune = null): array
    {
        $chiave = $codiceCatastale . '|' . $anno . '|' . $exComune;
        if (isset($this->cache[$chiave]) === false) {
            $this->cache[$chiave] = $this->cerca($codiceCatastale, $anno, $exComune);
        }
        return $this->cache[$chiave];
    }

    private function cerca(string $codiceCatastale, int $anno, ?string $exComune): array
    {
        $prospetto = $this->getDellAnno($codiceCatastale, $anno, $exComune);
        if ($prospetto !== null) {
            return ['prospetto' => $prospetto, 'origine' => $prospetto->getOrigine()];
        }
        // dal 2025 contano solo i prospetti: non si risale oltre il primo anno di obbligo
        $primo = $anno < self::PRIMO_ANNO_PROSPETTO ? $anno - self::ANNI_RIPIEGO : self::PRIMO_ANNO_PROSPETTO;
        for ($precedente = $anno - 1; $precedente >= $primo; $precedente--) {
            $prospetto = $this->getDellAnno($codiceCatastale, $precedente, $exComune);
            if ($prospetto !== null) {
                return ['prospetto' => $prospetto, 'origine' => 'aliquote ' . $precedente . ' (nessuna delibera per il ' . $anno . ', comma 767)'];
            }
        }
        return ['prospetto' => null, 'origine' => 'aliquote di base di legge (nessun prospetto per il ' . $anno
            . ($anno > self::PRIMO_ANNO_PROSPETTO ? ' né dal ' . self::PRIMO_ANNO_PROSPETTO : '') . ', comma 767)'];
    }

    /**
     * Prospetto dello studio o pubblicato per l'anno; per i comuni con più
     * prospetti (ex comuni) quello dell'ex comune indicato.
     */
    private function getDellAnno(string $codiceCatastale, int $anno, ?string $exComune): ?ProspettoAliquote
    {
        $prospettoModel = new ProspettoAliquoteModel($this->dataMapper);
        foreach ([$prospettoModel->getDelloStudioTutti($this->studio, $codiceCatastale, $anno), $prospettoModel->getPubblicatiTutti($codiceCatastale, $anno)] as $prospetti) {
            if (count($prospetti) === 0) {
                continue;
            }
            foreach ($prospetti as $prospetto) {
                if (($exComune !== null) && ($prospetto->exComune !== null) && (self::normalizza($prospetto->exComune) === self::normalizza($exComune))) {
                    return $prospetto;
                }
            }
            return $prospetti[0];
        }
        return null;
    }

    /**
     * Il comune ha prospetti diversi per gli ex comuni.
     */
    public function hasExComuni(string $codiceCatastale, int $anno): bool
    {
        $prospettoModel = new ProspettoAliquoteModel($this->dataMapper);
        return count(array_filter(array_merge($prospettoModel->getDelloStudioTutti($this->studio, $codiceCatastale, $anno), $prospettoModel->getPubblicatiTutti($codiceCatastale, $anno)),
                                fn(ProspettoAliquote $prospetto) => $prospetto->exComune !== null)) > 1;
    }

    private static function normalizza(string $testo): string
    {
        return mb_strtolower(preg_replace('/[^\p{L}\p{N}%,]+/u', '', $testo));
    }

    /**
     * Aliquota dell'immobile per l'anno.
     *
     * @return array{aliquota: float, origine: string, differenziata: ?AliquotaDifferenziata, proposta: bool, suggerite: string[]}
     *         proposta = scelta dal programma (da confermare); suggerite =
     *         differenziate compatibili con condizioni da verificare
     */
    public function getAliquota(string $codiceCatastale, int $anno, FattispecieImu $fattispecie, ?AliquotaApplicata $scelta, ?AliquotaApplicata $sceltaAltroAnno = null,
            ?CategoriaCatastale $categoria = null, ?UtilizzoImmobile $utilizzo = null, ?string $exComune = null, bool $ente = true, bool $inagibile = true): array
    {
        ['prospetto' => $prospetto, 'origine' => $origine] = $this->getProspetto($codiceCatastale, $anno, $exComune);
        if ($scelta !== null) {
            if ($scelta->aliquotaDifferenziata !== null) {
                return ['aliquota' => $scelta->aliquotaDifferenziata->aliquota, 'origine' => 'aliquota differenziata del ' . $scelta->aliquotaDifferenziata->prospettoAliquote->getOrigine(),
                    'differenziata' => $scelta->aliquotaDifferenziata, 'proposta' => false, 'suggerite' => []];
            }
            if ($scelta->aliquota !== null) {
                return ['aliquota' => $scelta->aliquota, 'origine' => 'aliquota indicata dallo studio' . ($scelta->motivo !== null ? ' (' . $scelta->motivo . ')' : ''), 'differenziata' => null, 'proposta' => false, 'suggerite' => []];
            }
        }
        if ($sceltaAltroAnno !== null) {
            $corrispondente = $this->trovaCorrispondente($prospetto, $sceltaAltroAnno);
            if ($corrispondente !== null) {
                return ['aliquota' => $corrispondente->aliquota, 'origine' => 'aliquota differenziata del ' . $prospetto->getOrigine() . ', con la stessa descrizione di quella scelta per il ' . $sceltaAltroAnno->anno,
                    'differenziata' => $corrispondente, 'proposta' => true, 'suggerite' => []];
            }
            if (($sceltaAltroAnno->aliquotaDifferenziata === null) && ($sceltaAltroAnno->aliquota !== null)) {
                return ['aliquota' => $sceltaAltroAnno->aliquota, 'origine' => 'aliquota indicata dallo studio per il ' . $sceltaAltroAnno->anno, 'differenziata' => null, 'proposta' => true, 'suggerite' => []];
            }
        }
        [$riconosciuta, $suggerite] = $this->riconosci($prospetto, $fattispecie, $categoria, $utilizzo, $ente, $inagibile);
        if ($riconosciuta !== null) {
            return ['aliquota' => $riconosciuta->aliquota, 'origine' => 'aliquota differenziata riconosciuta da categoria e utilizzo nel ' . $prospetto->getOrigine(),
                'differenziata' => $riconosciuta, 'proposta' => true, 'suggerite' => $suggerite];
        }
        $aliquota = $prospetto?->getAliquota($fattispecie);
        if ($aliquota === null) {
            return ['aliquota' => $fattispecie->getAliquotaBase(), 'origine' => $prospetto === null ? $origine : 'aliquota di base di legge (non indicata nel prospetto)', 'differenziata' => null, 'proposta' => false, 'suggerite' => $suggerite];
        }
        return ['aliquota' => $aliquota, 'origine' => $origine, 'differenziata' => null, 'proposta' => false, 'suggerite' => $suggerite];
    }

    /**
     * Differenziata del prospetto con tutte le condizioni verificate (se è
     * una sola) e descrizioni di quelle compatibili da verificare.
     *
     * @return array{0: ?AliquotaDifferenziata, 1: string[]}
     */
    private function riconosci(?ProspettoAliquote $prospetto, FattispecieImu $fattispecie, ?CategoriaCatastale $categoria, ?UtilizzoImmobile $utilizzo, bool $ente, bool $inagibile): array
    {
        if ($prospetto === null) {
            return [null, []];
        }
        $verificate = $suggerite = [];
        foreach ($this->getDifferenziate($prospetto) as $differenziata) {
            $esito = CompatibilitaDifferenziate::verifica($differenziata, $fattispecie, $categoria, $utilizzo, $ente, $inagibile);
            if ($esito['verificata']) {
                $verificate[] = $differenziata;
            } elseif ($esito['compatibile'] && (count($esito['daVerificare']) > 0)) {
                $suggerite[] = number_format($differenziata->aliquota, 2, ',', '') . '% se ricorrono: ' . implode(', ', $esito['daVerificare']);
            }
        }
        return [count($verificate) === 1 ? $verificate[0] : null, $suggerite];
    }

    /**
     * @return AliquotaDifferenziata[]
     */
    private function getDifferenziate(ProspettoAliquote $prospetto): array
    {
        return $this->differenziate[$prospetto->id] ??= iterator_to_array((new AliquotaDifferenziataModel($this->dataMapper))->getElenco($prospetto));
    }

    /**
     * Aliquota differenziata del prospetto con la stessa fattispecie e
     * descrizione di quella scelta per un altro anno; se per l'anno vale lo
     * stesso prospetto (comma 767, nessuna nuova delibera) la stessa.
     */
    private function trovaCorrispondente(?ProspettoAliquote $prospetto, AliquotaApplicata $sceltaAltroAnno): ?AliquotaDifferenziata
    {
        $riferimento = $sceltaAltroAnno->aliquotaDifferenziata;
        if (($prospetto === null) || ($riferimento === null)) {
            return null;
        }
        if ($riferimento->prospettoAliquote->id === $prospetto->id) {
            return $riferimento;
        }
        foreach ($this->getDifferenziate($prospetto) as $differenziata) {
            if (($differenziata->fattispecie === $riferimento->fattispecie) && (self::normalizza($differenziata->descrizione) === self::normalizza($riferimento->descrizione))) {
                return $differenziata;
            }
        }
        return null;
    }

    public function getDetrazione(string $codiceCatastale, int $anno, ?string $exComune = null): float
    {
        return $this->getProspetto($codiceCatastale, $anno, $exComune)['prospetto']?->detrazioneAbitazionePrincipale ?? 200.0;
    }

    public function getImportoMinimo(string $codiceCatastale, int $anno): float
    {
        return $this->getProspetto($codiceCatastale, $anno)['prospetto']?->importoMinimo ?? 12.0;
    }

    public function isAssimilazioneAnziani(string $codiceCatastale, int $anno, ?string $exComune = null): bool
    {
        return $this->getProspetto($codiceCatastale, $anno, $exComune)['prospetto']?->assimilazioneAnziani ?? false;
    }
}
