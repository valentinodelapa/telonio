<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;

/**
 * Tabella del libro giornale: $giornale, $classeTabella, $classeNumero,
 * $collegamenti (link alle registrazioni e alle schede).
 */
?>
<table class="<?= Html::e($classeTabella) ?>">
    <thead><tr><th>N.</th><th>Data</th><th>Descrizione</th><th>Conto</th><th class="<?= Html::e($classeNumero) ?>">Dare</th><th class="<?= Html::e($classeNumero) ?>">Avere</th></tr></thead>
    <tbody>
        <?php foreach ($giornale['registrazioni'] as ['registrazione' => $registrazione, 'righe' => $righe]): ?>
            <?php foreach ($righe as $indice => $riga): ?>
                <tr class="<?= $indice === 0 ? 'inizio-registrazione' : '' ?>">
                    <?php if ($indice === 0): ?>
                        <td rowspan="<?= count($righe) ?>"><?= $collegamenti ? '<a href="/registrazione/show/registrazione/' . Html::e($registrazione->id) . '">' . Html::e($registrazione->numero) . '</a>' : Html::e($registrazione->numero) ?></td>
                        <td rowspan="<?= count($righe) ?>" class="text-nowrap"><?= Html::data($registrazione->dataRegistrazione) ?></td>
                        <td rowspan="<?= count($righe) ?>"><?= Html::e($registrazione->getDescrizioneCompleta()) ?><?= $registrazione->controparte === null ? '' : '<div class="small tenue text-body-secondary">' . Html::e($registrazione->controparte->denominazione) . '</div>' ?></td>
                    <?php endif; ?>
                    <td><?= Html::e($riga->conto->getEtichetta()) ?><?= $riga->descrizione === null ? '' : '<div class="small tenue text-body-secondary">' . Html::e($riga->descrizione) . '</div>' ?></td>
                    <td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($riga->dare, true)) ?></td>
                    <td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($riga->avere, true)) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <tr class="totale table-light fw-semibold"><td colspan="4">Totale</td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($giornale['dare'])) ?></td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($giornale['avere'])) ?></td></tr>
    </tbody>
</table>
