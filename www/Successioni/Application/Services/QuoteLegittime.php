<?php

namespace Successioni\Application\Services;

use Successioni\Application\Entities\BeneficiarioSuccessione;
use Successioni\Application\Enumerations\GradoParentela;
use Successioni\Application\Enumerations\TipoBeneficiario;

/**
 * Quote della successione legittima (artt. 565-586 c.c.; allegato 5 alle
 * istruzioni, "Devoluzione") per proporre le devoluzioni quando non c'è
 * testamento:
 * - figli (e discendenti per rappresentazione, per stirpi) con il coniuge:
 *   1/2 al coniuge con un figlio, 1/3 con più figli (art. 581);
 * - senza figli: coniuge con ascendenti o fratelli 2/3 (art. 582), con
 *   genitori e fratelli 8/12, 3/12 e 1/12 (allegato 5); senza coniuge,
 *   genitori e fratelli per capi con almeno metà ai genitori (art. 571);
 *   ascendenti per linee (art. 569); i fratelli unilaterali hanno metà
 *   della quota dei germani (art. 570);
 * - altrimenti il coniuge da solo, o i parenti dello stesso grado in parti
 *   uguali.
 *
 * Restano da indicare a mano i casi non coperti (ascendenti diversi dai
 * genitori con fratelli, parenti di grado diverso, rinunce con
 * accrescimento non lineare): l'esito spiega la regola applicata.
 */
class QuoteLegittime
{

    private ?string $regola = null;

    /**
     * @param BeneficiarioSuccessione[] $beneficiari
     * @return array<int, Frazione>|null quota per id del beneficiario
     *         (solo quelli che partecipano), null se il caso va gestito a mano
     */
    public function calcola(array $beneficiari): ?array
    {
        $eredi = array_values(array_filter($beneficiari, fn(BeneficiarioSuccessione $beneficiario) => $beneficiario->isDestinatario()
                        && in_array($beneficiario->tipoBeneficiario, [TipoBeneficiario::erede, TipoBeneficiario::chiamato], true)));
        if (count($eredi) === 0) {
            $this->regola = 'Nessun erede o chiamato all\'eredità che partecipi alla devoluzione.';
            return null;
        }
        $coniuge = array_values(array_filter($eredi, fn(BeneficiarioSuccessione $erede) => $erede->gradoParentela === GradoParentela::coniuge));
        $coniuge = $coniuge[0] ?? null;
        $discendenti = self::stirpi($eredi, [GradoParentela::figlio], [GradoParentela::nipoteLineaRetta, GradoParentela::pronipoteLineaRetta, GradoParentela::figlioPronipote]);
        $genitori = array_values(array_filter($eredi, fn(BeneficiarioSuccessione $erede) => $erede->gradoParentela === GradoParentela::genitore));
        $altriAscendenti = array_values(array_filter($eredi, fn(BeneficiarioSuccessione $erede) => $erede->gradoParentela->isAscendente() && ($erede->gradoParentela !== GradoParentela::genitore)));
        $fratelli = self::stirpi($eredi, [GradoParentela::fratelloGermano, GradoParentela::fratelloUnilaterale], [GradoParentela::nipoteFratello, GradoParentela::figlioNipoteFratello, GradoParentela::nipoteDiNipote]);
        $quote = [];
        if (count($discendenti) > 0) {
            $restante = new Frazione(1);
            if ($coniuge !== null) {
                $quote[$coniuge->id] = count($discendenti) === 1 ? new Frazione(1, 2) : new Frazione(1, 3);
                $restante = $restante->meno($quote[$coniuge->id]);
            }
            $this->ripartisciStirpi($discendenti, $restante, $quote, false);
            $this->regola = 'Coniuge e figli (artt. 566 e 581 c.c.), discendenti per rappresentazione per stirpi.';
        } elseif ($coniuge !== null) {
            $quote[$coniuge->id] = new Frazione(1);
            if ((count($genitori) + count($altriAscendenti) > 0) && (count($fratelli) > 0)) {
                if (count($altriAscendenti) > 0) {
                    return $this->manuale('Coniuge con ascendenti diversi dai genitori e fratelli: quote da indicare a mano.');
                }
                $quote[$coniuge->id] = new Frazione(2, 3);
                $this->ripartisciUguale($genitori, new Frazione(1, 4), $quote);
                $this->ripartisciStirpi($fratelli, new Frazione(1, 12), $quote, true);
                $this->regola = 'Coniuge, genitori e fratelli (art. 582 c.c.; allegato 5: 8/12, 3/12, 1/12).';
            } elseif (count($genitori) + count($altriAscendenti) > 0) {
                $quote[$coniuge->id] = new Frazione(2, 3);
                $this->ripartisciAscendenti($genitori, $altriAscendenti, new Frazione(1, 3), $quote);
                $this->regola = 'Coniuge e ascendenti (art. 582 c.c.).';
            } elseif (count($fratelli) > 0) {
                $quote[$coniuge->id] = new Frazione(2, 3);
                $this->ripartisciStirpi($fratelli, new Frazione(1, 3), $quote, true);
                $this->regola = 'Coniuge e fratelli (art. 582 c.c.).';
            } else {
                $this->regola = 'Coniuge senza figli, ascendenti e fratelli (art. 583 c.c.).';
            }
        } elseif ((count($genitori) > 0) && (count($fratelli) > 0)) {
            if (count($altriAscendenti) > 0) {
                return $this->manuale('Ascendenti diversi dai genitori con i fratelli: quote da indicare a mano.');
            }
            $pesoFratelli = array_sum(array_map(fn(array $stirpe) => $stirpe['peso'], $fratelli)) / 2;
            $quotaGenitori = new Frazione(count($genitori) * 2, (int) round((count($genitori) + $pesoFratelli) * 2));
            if ($quotaGenitori->toFloat() < 0.5) {
                $quotaGenitori = new Frazione(1, 2);
            }
            $this->ripartisciUguale($genitori, $quotaGenitori, $quote);
            $this->ripartisciStirpi($fratelli, (new Frazione(1))->meno($quotaGenitori), $quote, true);
            $this->regola = 'Genitori e fratelli per capi, ai genitori almeno la metà (art. 571 c.c.).';
        } elseif (count($genitori) + count($altriAscendenti) > 0) {
            if (count($fratelli) > 0) {
                return $this->manuale('Ascendenti diversi dai genitori con i fratelli: quote da indicare a mano.');
            }
            $this->ripartisciAscendenti($genitori, $altriAscendenti, new Frazione(1), $quote);
            $this->regola = 'Ascendenti (artt. 568 e 569 c.c.).';
        } elseif (count($fratelli) > 0) {
            $this->ripartisciStirpi($fratelli, new Frazione(1), $quote, true);
            $this->regola = 'Fratelli e sorelle, unilaterali per metà (art. 570 c.c.), discendenti per rappresentazione.';
        } else {
            $gradi = array_unique(array_map(fn(BeneficiarioSuccessione $erede) => $erede->gradoParentela->value, $eredi));
            if (count($gradi) > 1) {
                return $this->manuale('Altri parenti di gradi diversi: succede il più prossimo (art. 572 c.c.), quote da indicare a mano.');
            }
            $this->ripartisciUguale($eredi, new Frazione(1), $quote);
            $this->regola = 'Altri parenti dello stesso grado in parti uguali (art. 572 c.c.).';
        }
        return $quote;
    }

    public function getRegola(): ?string
    {
        return $this->regola;
    }

    private function manuale(string $regola): ?array
    {
        $this->regola = $regola;
        return null;
    }

    /**
     * Raggruppa in stirpi i chiamati diretti e quelli per rappresentazione
     * (stesso soggetto rappresentato). Il peso serve per i fratelli
     * unilaterali (1 contro 2 dei germani).
     *
     * @param BeneficiarioSuccessione[] $eredi
     * @param GradoParentela[] $diretti
     * @param GradoParentela[] $rappresentanti
     * @return array<array{peso: int, membri: BeneficiarioSuccessione[]}>
     */
    private static function stirpi(array $eredi, array $diretti, array $rappresentanti): array
    {
        $stirpi = [];
        foreach ($eredi as $erede) {
            if (in_array($erede->gradoParentela, $diretti, true)) {
                $stirpi['diretto-' . $erede->id] = ['peso' => $erede->gradoParentela === GradoParentela::fratelloUnilaterale ? 1 : 2, 'membri' => [$erede]];
            } elseif (in_array($erede->gradoParentela, $rappresentanti, true) && ($erede->rappresentato !== null)) {
                $chiave = 'rappresentato-' . $erede->rappresentato->id;
                $stirpi[$chiave] ??= ['peso' => $erede->gradoParentelaRappresentato === GradoParentela::fratelloUnilaterale ? 1 : 2, 'membri' => []];
                $stirpi[$chiave]['membri'][] = $erede;
            }
        }
        return array_values($stirpi);
    }

    /**
     * @param array<array{peso: int, membri: BeneficiarioSuccessione[]}> $stirpi
     */
    private function ripartisciStirpi(array $stirpi, Frazione $totale, array &$quote, bool $conPeso): void
    {
        $pesoTotale = array_sum(array_map(fn(array $stirpe) => $conPeso ? $stirpe['peso'] : 1, $stirpi));
        foreach ($stirpi as $stirpe) {
            $quotaStirpe = $totale->per(new Frazione($conPeso ? $stirpe['peso'] : 1, $pesoTotale));
            $this->ripartisciUguale($stirpe['membri'], $quotaStirpe, $quote);
        }
    }

    /**
     * Genitori in parti uguali; in mancanza, metà alla linea paterna e metà
     * a quella materna (tutto alla linea presente).
     *
     * @param BeneficiarioSuccessione[] $genitori
     * @param BeneficiarioSuccessione[] $altri
     */
    private function ripartisciAscendenti(array $genitori, array $altri, Frazione $totale, array &$quote): void
    {
        if (count($genitori) > 0) {
            $this->ripartisciUguale($genitori, $totale, $quote);
            return;
        }
        $paterni = array_values(array_filter($altri, fn(BeneficiarioSuccessione $erede) => in_array($erede->gradoParentela, [GradoParentela::nonnoPaterno, GradoParentela::bisavoPaterno, GradoParentela::trisavoPaterno], true)));
        $materni = array_values(array_filter($altri, fn(BeneficiarioSuccessione $erede) => in_array($erede->gradoParentela, [GradoParentela::nonnoMaterno, GradoParentela::bisavoMaterno, GradoParentela::trisavoMaterno], true)));
        $linee = array_values(array_filter([$paterni, $materni], fn(array $linea) => count($linea) > 0));
        foreach ($linee as $linea) {
            $this->ripartisciUguale($linea, $totale->diviso(count($linee)), $quote);
        }
    }

    /**
     * @param BeneficiarioSuccessione[] $membri
     */
    private function ripartisciUguale(array $membri, Frazione $totale, array &$quote): void
    {
        foreach ($membri as $membro) {
            $quote[$membro->id] = $totale->diviso(count($membri));
        }
    }
}
