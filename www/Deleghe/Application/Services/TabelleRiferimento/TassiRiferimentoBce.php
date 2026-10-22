<?php

namespace Deleghe\Application\Services\TabelleRiferimento;

/**
 * Tasso ufficiale di riferimento per le sanzioni civili INPS e INAIL: tasso
 * delle operazioni di rifinanziamento principali della BCE (serie
 * FM.B.U2.EUR.4F.KR.MRR_FR.LEV del servizio dati BCE), con le date di
 * decorrenza delle variazioni. Le circolari INPS che aggiornano la misura
 * delle sanzioni seguono le stesse decorrenze (es. 2,65% dal 16/09/2026,
 * circolare n. 98/2026).
 *
 * @see https://data.ecb.europa.eu/data/datasets/FM/FM.B.U2.EUR.4F.KR.MRR_FR.LEV
 */
class TassiRiferimentoBce
{

    public const URL = 'https://data-api.ecb.europa.eu/service/data/FM/B.U2.EUR.4F.KR.MRR_FR.LEV?format=csvdata&startPeriod=2008-01-01';
    public const COLONNE = ['valido_dal', 'tasso'];
    private const MINIMO_VARIAZIONI = 20;

    /**
     * @return array<array{valido_dal: string, tasso: string}> solo le date in cui il tasso cambia
     */
    public function leggi(?string $sorgente = null): array
    {
        $contenuto = @file_get_contents($sorgente ?? self::URL, false, stream_context_create(['http' => ['timeout' => 60, 'user_agent' => 'Mozilla/5.0 (CommSuite tabelle-f24)']]));
        if ($contenuto === false) {
            throw new \RuntimeException('Download non riuscito: ' . ($sorgente ?? self::URL));
        }
        $righe = array_map(fn($riga) => str_getcsv($riga, escape: ''), array_filter(explode("\n", trim($contenuto))));
        $intestazione = array_shift($righe);
        $data = array_search('TIME_PERIOD', $intestazione, true);
        $valore = array_search('OBS_VALUE', $intestazione, true);
        if (($data === false) || ($valore === false)) {
            throw new \UnexpectedValueException('Tracciato della serie BCE cambiato: colonne TIME_PERIOD/OBS_VALUE mancanti.');
        }
        usort($righe, fn($a, $b) => strcmp($a[$data], $b[$data]));
        $tassi = [];
        $precedente = null;
        foreach ($righe as $riga) {
            if ((\DateTimeImmutable::createFromFormat('!Y-m-d', $riga[$data]) === false) || (is_numeric($riga[$valore]) === false)) {
                throw new \UnexpectedValueException('Riga non valida nella serie BCE: ' . implode(',', $riga));
            }
            if ($riga[$valore] !== $precedente) {
                $tassi[] = ['valido_dal' => $riga[$data], 'tasso' => $riga[$valore]];
                $precedente = $riga[$valore];
            }
        }
        if (count($tassi) < self::MINIMO_VARIAZIONI) {
            throw new \UnexpectedValueException('Serie dei tassi BCE incompleta.');
        }
        return $tassi;
    }
}
