<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;

/**
 * Registro dei beni ammortizzabili dell'esercizio (art. 16 D.P.R.
 * 600/1973): per bene costo, coefficiente, fondo iniziale, quota, fondo
 * finale e residuo, con le dismissioni.
 */
$titoloStampa = $pageTitle;
$dal = $esercizio->dataInizio;
$al = $esercizio->dataFine;
$orizzontale = true;
require __DIR__ . '/../commonParts/stampaTesta.php';
$totali = ['costo' => 0.0, 'fondoInizio' => 0.0, 'quota' => 0.0, 'fondoFine' => 0.0, 'residuo' => 0.0];
?>
<table>
    <thead><tr><th><?= Html::e($campi['codice']) ?></th><th><?= Html::e($campi['descrizione']) ?></th><th><?= Html::e($campi['contoCespite']) ?></th><th><?= Html::e($campi['dataAcquisto']) ?></th><th class="num"><?= Html::e($campi['costo']) ?></th><th class="num">%</th><th class="num"><?= Html::e($fondoInizio) ?></th><th class="num"><?= Html::e($quotaEsercizio) ?></th><th class="num"><?= Html::e($fondoFine) ?></th><th class="num"><?= Html::e($residuo) ?></th><th><?= Html::e($titoloDismissione) ?></th></tr></thead>
    <tbody>
        <?php foreach ($cespiti as ['cespite' => $cespite, 'voce' => $voce]): ?>
            <?php if ($voce === null) {
                continue;
            } ?>
            <?php
            $totali['costo'] += $cespite->costo;
            foreach (['fondoInizio', 'quota', 'fondoFine', 'residuo'] as $campo) {
                $totali[$campo] += $voce[$campo];
            }
            ?>
            <tr>
                <td><?= Html::e($cespite->codice) ?></td>
                <td><?= Html::e($cespite->descrizione) ?></td>
                <td><?= Html::e($cespite->contoCespite->codice . ' ' . $cespite->contoCespite->descrizione) ?></td>
                <td><?= Html::data($cespite->dataAcquisto) ?><?= $cespite->dataEntrataFunzione === null ? '' : '<div class="tenue">' . Html::e($campi['dataEntrataFunzione']) . ' ' . Html::data($cespite->dataEntrataFunzione) . '</div>' ?></td>
                <td class="num"><?= Html::e(Importo::euro($cespite->costo)) ?></td>
                <td class="num"><?= Html::e(number_format($cespite->percentuale, 2, ',', '.')) ?><?= $voce['primo'] && $cespite->riduzionePrimoEsercizio && ($cespite->deduzioneIntegrale === false) ? ' (½)' : '' ?></td>
                <td class="num"><?= Html::e(Importo::euro($voce['fondoInizio'])) ?></td>
                <td class="num"><?= Html::e(Importo::euro($voce['quota'])) ?></td>
                <td class="num"><?= Html::e(Importo::euro($voce['fondoFine'])) ?></td>
                <td class="num"><?= Html::e(Importo::euro($voce['residuo'])) ?></td>
                <td><?= $voce['dismesso'] ? Html::data($cespite->dataDismissione) . ' · € ' . Html::e(Importo::euro($cespite->prezzoDismissione ?? 0.0)) . ' · ' . Html::e($voce['plusvalenza'] >= 0 ? $plusvalenza : $minusvalenza) . ' € ' . Html::e(Importo::euro(abs($voce['plusvalenza']))) : '' ?></td>
            </tr>
        <?php endforeach; ?>
        <tr class="totale"><td colspan="4"><?= Html::e($campi['totale'] ?? 'Totale') ?></td><td class="num"><?= Html::e(Importo::euro($totali['costo'])) ?></td><td></td><td class="num"><?= Html::e(Importo::euro($totali['fondoInizio'])) ?></td><td class="num"><?= Html::e(Importo::euro($totali['quota'])) ?></td><td class="num"><?= Html::e(Importo::euro($totali['fondoFine'])) ?></td><td class="num"><?= Html::e(Importo::euro($totali['residuo'])) ?></td><td></td></tr>
    </tbody>
</table>
<p class="tenue"><?= Html::e($aiuto) ?></p>
</body>
</html>
