<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\Cespite;
use Contabilita\Application\Enumerations\VoceCee;
use Contabilita\Application\Models\CespiteModel;
use Contabilita\Application\Models\EsercizioContabileModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Beni strumentali dell'impresa dal registro dei cespiti, per i dati
 * contabili dei modelli ISA (istruzioni del quadro F, righi F15 e F21):
 *
 * - valore dei beni strumentali: costo storico dei beni materiali e
 *   immateriali ammortizzabili, esclusi gli immobili (terreni e fabbricati),
 *   l'avviamento e le immobilizzazioni in corso, ragguagliato ai giorni di
 *   possesso nell'anno (convenzionalmente 365); i beni mobili adibiti
 *   promiscuamente all'impresa e all'uso personale (deducibili al 50%,
 *   escluse le autovetture e gli altri mezzi con il limite di costo) per il
 *   50%; per le imprese (quadro F) il costo dei beni acquistati nel 1994 e
 *   nel 1995 ridotto del 10 e del 20 per cento, per i professionisti
 *   (quadro H, rigo H01) le autovetture e gli altri mezzi di trasporto nel
 *   limite del costo fiscalmente rilevante;
 * - quote di ammortamento deducibili dei beni mobili strumentali (impianti
 *   e macchinari, attrezzature, altri beni).
 *
 * Non sono nel registro, e restano da indicare a mano, i beni in
 * locazione finanziaria (al costo del concedente) e in comodato.
 */
class BeniStrumentali
{

    /** Voci dello stato patrimoniale escluse dal valore dei beni strumentali */
    public const ESCLUSI = [VoceCee::attivoBI5, VoceCee::attivoBII1, VoceCee::attivoBII5];
    /** Beni mobili strumentali */
    public const MOBILI = [VoceCee::attivoBII2, VoceCee::attivoBII3, VoceCee::attivoBII4];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Valore dei beni strumentali nell'anno (rigo F21, campo 1, del modello
     * ISA, senza i beni in locazione finanziaria e in comodato).
     */
    public function getValore(Soggetto $soggetto, int $anno, bool $professionista = false): float
    {
        $totale = 0.0;
        foreach ((new CespiteModel($this->dataMapper))->getElenco($soggetto) as $cespite) {
            $totale += self::valoreNellAnno($cespite, $anno, $professionista);
        }
        return round($totale, 2);
    }

    /**
     * Valore del cespite nell'anno: costo ragguagliato ai giorni di
     * possesso, con le riduzioni previste.
     */
    public static function valoreNellAnno(Cespite $cespite, int $anno, bool $professionista = false): float
    {
        if (in_array($cespite->contoCespite->voceCee, self::ESCLUSI, true)) {
            return 0.0;
        }
        $inizio = max($cespite->dataAcquisto->format('Y-m-d'), $anno . '-01-01');
        $fine = min($cespite->dataDismissione?->format('Y-m-d') ?? $anno . '-12-31', $anno . '-12-31');
        if ($fine < $inizio) {
            return 0.0;
        }
        $giorni = min(365, (new \DateTimeImmutable($inizio))->diff(new \DateTimeImmutable($fine))->days + 1);
        $costo = $professionista ? min($cespite->costo, $cespite->limiteCostoFiscale ?? $cespite->costo) : $cespite->costo * match ((int) $cespite->dataAcquisto->format('Y')) {
            1994 => 0.9,
            1995 => 0.8,
            default => 1.0,
        };
        // beni mobili ad uso promiscuo, escluse autovetture, autocaravan, ciclomotori e motocicli
        if (($cespite->percentualeDeducibile == 50.0) && ($cespite->limiteCostoFiscale === null)) {
            $costo *= 0.5;
        }
        return $costo * $giorni / 365;
    }

    /**
     * Quote di ammortamento deducibili dell'anno dei beni mobili strumentali
     * (rigo F15, campo 2, del modello ISA).
     */
    public function getQuoteBeniMobili(Soggetto $soggetto, int $anno): float
    {
        $esercizio = (new EsercizioContabileModel($this->dataMapper))->getPerAnno($soggetto, $anno);
        if ($esercizio === null) {
            return 0.0;
        }
        $totale = 0.0;
        foreach ((new Ammortamenti($this->dataMapper))->calcola($soggetto, $esercizio) as $voce) {
            if (in_array($voce['cespite']->contoCespite->voceCee, self::MOBILI, true)) {
                $totale += $voce['deducibile'];
            }
        }
        return round($totale, 2);
    }
}
