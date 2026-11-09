<?php

namespace Dichiarativi\Application\Services\Redditi;

use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Services\Iva\ControlliIva;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Controlli del modello Redditi PF dalle specifiche tecniche 2026 (par. 6.1
 * per il quadro RA, 7.1 per il quadro RB): errori che impediscono la
 * trasmissione, segnalazioni da confermare, avvertenze.
 */
class ControlliRedditi
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return string[]
     */
    public function getErrori(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri): array
    {
        $errori = [];
        foreach ($quadri->getModuli() as $modulo) {
            $etichetta = $modulo > 1 ? 'Mod. ' . $modulo . ', ' : '';
            $this->controllaTerreni($quadri, $modulo, $etichetta, $errori);
            $this->controllaFabbricati($quadri, $modulo, $etichetta, $errori);
        }
        $cin = array_map(fn(int $numero) => $quadri->get(sprintf('RB%03d003', $numero), 1), [24, 25]);
        foreach ($cin as $codice) {
            if (($codice !== null) && (strlen($codice) !== 18)) {
                $errori[] = 'Quadro RB, sezione III: il CIN ha 18 caratteri.';
            }
        }
        return $errori;
    }

    private function controllaTerreni(QuadriDichiarazione $quadri, int $modulo, string $etichetta, array &$errori): void
    {
        $giorni = [];
        $terreno = 0;
        for ($rigo = 1; $rigo <= 22; $rigo++) {
            $codice = fn(int $colonna) => sprintf('RA%03d%03d', $rigo, $colonna);
            if ($quadri->get($codice(2), $modulo) === null) {
                continue;
            }
            $titolo = $quadri->getImporto($codice(2), $modulo);
            if ((in_array($titolo, [1, 2, 3, 4, 7, 8, 9], true) === false)) {
                $errori[] = sprintf('%sRA%d: titolo %d non ammesso (da 1 a 4, da 7 a 9).', $etichetta, $rigo, $titolo);
            }
            $continuazione = $quadri->get($codice(8), $modulo) !== null;
            if ($continuazione && ($rigo === 1) && ($modulo === 1)) {
                $errori[] = 'RA1: la casella "continuazione" non può essere barrata sul primo rigo.';
            }
            $terreno = $continuazione ? $terreno : $rigo;
            $giorni[$terreno] = ($giorni[$terreno] ?? 0) + $quadri->getImporto($codice(4), $modulo);
            if (($quadri->get($codice(6), $modulo) !== null) && ($titolo !== 2)) {
                $errori[] = sprintf('%sRA%d: il canone in regime vincolistico richiede il titolo 2.', $etichetta, $rigo);
            }
            if ($continuazione && ($quadri->get($codice(9), $modulo) !== null)) {
                $errori[] = sprintf('%sRA%d: la casella "IMU non dovuta" va solo sul primo rigo del terreno.', $etichetta, $rigo);
            }
        }
        foreach ($giorni as $rigo => $totale) {
            if (($totale < 1) || ($totale > 365)) {
                $errori[] = sprintf('%sRA%d: giorni di possesso del terreno %d (da 1 a 365).', $etichetta, $rigo, $totale);
            }
        }
    }

    private function controllaFabbricati(QuadriDichiarazione $quadri, int $modulo, string $etichetta, array &$errori): void
    {
        $giorni = [];
        $fabbricato = 0;
        for ($rigo = 1; $rigo <= 9; $rigo++) {
            $codice = fn(int $colonna) => sprintf('RB%03d%03d', $rigo, $colonna);
            if ($quadri->get($codice(2), $modulo) === null) {
                continue;
            }
            $utilizzo = $quadri->getImporto($codice(2), $modulo);
            $locato = in_array($utilizzo, [3, 4, 8, 11, 12, 14], true);
            if (in_array($utilizzo, [1, 2, 3, 4, 5, 6, 8, 9, 10, 11, 12, 14, 15], true) === false) {
                $errori[] = sprintf('%sRB%d: utilizzo %d non ammesso.', $etichetta, $rigo, $utilizzo);
            }
            $continuazione = $quadri->get($codice(8), $modulo) !== null;
            if ($continuazione && ($rigo === 1) && ($modulo === 1)) {
                $errori[] = 'RB1: la casella "continuazione" non può essere barrata sul primo rigo.';
            }
            if (!$continuazione && ($quadri->get($codice(9), $modulo) === null)) {
                $errori[] = sprintf('%sRB%d: manca il codice del comune.', $etichetta, $rigo);
            }
            $fabbricato = $continuazione ? $fabbricato : $rigo;
            $giorni[$fabbricato] = ($giorni[$fabbricato] ?? 0) + $quadri->getImporto($codice(3), $modulo);
            if ($locato !== ($quadri->get($codice(5), $modulo) !== null)) {
                $errori[] = sprintf('%sRB%d: il codice canone va indicato solo con gli utilizzi 3, 4, 8, 11, 12 e 14.', $etichetta, $rigo);
            }
            $casi = $quadri->get($codice(7), $modulo) === null ? null : $quadri->getImporto($codice(7), $modulo);
            if ($locato && ($quadri->get($codice(6), $modulo) === null) && (in_array($casi, [4, 7], true) === false)) {
                $errori[] = sprintf('%sRB%d: manca il canone di locazione.', $etichetta, $rigo);
            }
            if (in_array($casi, [1, 3], true) && ($utilizzo !== 9)) {
                $errori[] = sprintf('%sRB%d: i casi particolari 1 e 3 richiedono l\'utilizzo 9.', $etichetta, $rigo);
            }
            $cedolare = $quadri->get($codice(11), $modulo) === null ? null : $quadri->getImporto($codice(11), $modulo);
            if (($cedolare !== null) && ($quadri->getImporto($codice(5), $modulo) !== 3)) {
                $errori[] = sprintf('%sRB%d: la cedolare secca richiede il codice canone 3.', $etichetta, $rigo);
            }
            if (in_array($cedolare, [2, 3], true) && (in_array($utilizzo, [3, 11], true) === false)) {
                $errori[] = sprintf('%sRB%d: i codici 2 e 3 della cedolare secca (locazioni brevi) richiedono l\'utilizzo 3 o 11.', $etichetta, $rigo);
            }
            $imu = $quadri->get($codice(12), $modulo) === null ? null : $quadri->getImporto($codice(12), $modulo);
            $ammessi = [1 => [2, 3, 4, 8, 9, 10, 14, 15], 2 => [1, 5, 6, 11, 12], 3 => [2, 9, 10, 15]];
            if (($imu !== null) && (in_array($utilizzo, $ammessi[$imu] ?? [], true) === false)) {
                $errori[] = sprintf('%sRB%d: il caso particolare IMU %d non è ammesso con l\'utilizzo %d.', $etichetta, $rigo, $imu, $utilizzo);
            }
        }
        foreach ($giorni as $rigo => $totale) {
            if (($totale < 1) || ($totale > 365)) {
                $errori[] = sprintf('%sRB%d: giorni di possesso del fabbricato %d (da 1 a 365).', $etichetta, $rigo, $totale);
            }
        }
    }

    /**
     * Segnalazioni da confermare (scarto con possibilità di conferma).
     *
     * @return string[]
     */
    public function getSegnalazioni(QuadriDichiarazione $quadri): array
    {
        $segnalazioni = [];
        // fabbricato a disposizione nel comune dell'abitazione principale tenuta tutto l'anno: casi IMU 1 o 3 (specifiche 7.1.1)
        $comuniPrincipale = [];
        $righi = [];
        foreach ($quadri->getModuli() as $modulo) {
            $comune = null;
            for ($rigo = 1; $rigo <= 9; $rigo++) {
                $codice = fn(int $colonna) => sprintf('RB%03d%03d', $rigo, $colonna);
                if ($quadri->get($codice(2), $modulo) === null) {
                    continue;
                }
                $comune = $quadri->get($codice(9), $modulo) ?? $comune;
                $righi[] = ['modulo' => $modulo, 'rigo' => $rigo, 'comune' => $comune, 'utilizzo' => $quadri->getImporto($codice(2), $modulo),
                    'giorni' => $quadri->getImporto($codice(3), $modulo), 'imu' => $quadri->get($codice(12), $modulo)];
            }
        }
        foreach ($righi as $rigo) {
            if (($rigo['utilizzo'] === 1) && ($rigo['giorni'] === 365)) {
                $comuniPrincipale[] = $rigo['comune'];
            }
        }
        foreach ($righi as $rigo) {
            if (($rigo['utilizzo'] === 2) && in_array($rigo['comune'], $comuniPrincipale, true) && (in_array($rigo['imu'], ['1', '3'], true) === false)) {
                $segnalazioni[] = sprintf('RB%d: immobile a disposizione nel comune dell\'abitazione principale senza il caso particolare IMU 1 o 3.', $rigo['rigo']);
            }
        }
        return $segnalazioni;
    }

    /**
     * @return string[]
     */
    public function getAvvertenze(QuadriDichiarazione $quadri, ?Dichiarazione $dichiarazione = null): array
    {
        return array_values(array_filter([ControlliIva::getAvvisoVisto($dichiarazione)]));
    }
}
