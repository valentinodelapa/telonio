<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;

/**
 * Prospetto della liquidazione (vista a schermo e da stampare): $liquidazione,
 * $classeTabella, $classeNumero, $classeTotale.
 */
$righeProspetto = [
    [$campi['ivaEsigibile'], $liquidazione->ivaEsigibile],
    [$campi['ivaVentilata'], $liquidazione->ivaVentilata],
    [$campi['ivaDetraibile'], -$liquidazione->ivaDetraibile],
    [$campi['debitoPrecedente'], $liquidazione->debitoPrecedente],
    [$campi['creditoPrecedente'], -$liquidazione->creditoPrecedente],
    [$campi['creditoAnnoPrecedente'], -$liquidazione->creditoAnnoPrecedente],
    [$campi['versamentiAuto'], -$liquidazione->versamentiAuto],
    [$campi['creditiImposta'], -$liquidazione->creditiImposta],
    [$campi['acconto'], -$liquidazione->acconto],
    [$campi['interessi'], $liquidazione->interessi],
];
?>
<table class="<?= Html::e($classeTabella) ?>">
    <tbody>
        <?php foreach ($righeProspetto as [$etichetta, $importo]): ?>
            <?php if (($importo != 0) || ($etichetta === $campi['ivaEsigibile']) || ($etichetta === $campi['ivaDetraibile'])): ?>
                <tr><td><?= $etichetta === $campi['ivaVentilata'] ? '<span class="ps-3">' . Html::e($etichetta) . '</span>' : Html::e($etichetta) ?></td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($importo)) ?></td></tr>
            <?php endif; ?>
        <?php endforeach; ?>
        <tr class="<?= Html::e($classeTotale) ?>"><td><?= Html::e($campi['importoDovuto']) ?><?= $liquidazione->scadenza === null ? '' : ' (' . Html::e($campi['scadenza']) . ' ' . Html::data($liquidazione->scadenza) . ')' ?></td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($liquidazione->importoDovuto)) ?></td></tr>
        <?php if ($liquidazione->debitoRinviato > 0): ?><tr><td><?= Html::e($campi['debitoRinviato']) ?></td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($liquidazione->debitoRinviato)) ?></td></tr><?php endif; ?>
        <?php if ($liquidazione->creditoFinale > 0): ?><tr><td><?= Html::e($campi['creditoFinale']) ?></td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($liquidazione->creditoFinale)) ?></td></tr><?php endif; ?>
    </tbody>
</table>
