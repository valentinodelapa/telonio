<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;

/**
 * Prospetto della ventilazione dei corrispettivi del periodo (vista a
 * schermo e da stampare): $ventilazione, $classeTabella, $classeNumero,
 * $classeTotale.
 */
?>
<table class="<?= Html::e($classeTabella) ?>">
    <thead><tr><th><?= Html::e($campi['codiceIva']) ?></th><th class="<?= Html::e($classeNumero) ?>"><?= Html::e($acquistiRivendita) ?></th><th class="<?= Html::e($classeNumero) ?>">%</th><th class="<?= Html::e($classeNumero) ?>"><?= Html::e($corrispettiviVentilati) ?></th><th class="<?= Html::e($classeNumero) ?>"><?= Html::e($campi['imponibile']) ?></th><th class="<?= Html::e($classeNumero) ?>"><?= Html::e($campi['imposta']) ?></th></tr></thead>
    <tbody>
        <?php foreach ($ventilazione->righe as $riga): ?>
            <tr><td><?= Html::e($riga['codice']->getEtichetta()) ?></td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($riga['acquisti'])) ?></td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e(number_format($riga['percentuale'], 2, ',', '')) ?></td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($riga['corrispettivi'])) ?></td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($riga['imponibile'])) ?></td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($riga['imposta'])) ?></td></tr>
        <?php endforeach; ?>
        <tr class="<?= Html::e($classeTotale) ?>"><td><?= Html::e($totale) ?></td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($ventilazione->acquisti)) ?></td><td class="<?= Html::e($classeNumero) ?>">100,00</td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($ventilazione->corrispettivi)) ?></td><td></td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($ventilazione->imposta)) ?></td></tr>
    </tbody>
</table>
