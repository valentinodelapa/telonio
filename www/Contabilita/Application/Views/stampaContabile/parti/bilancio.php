<?php

use Contabilita\Application\Enumerations\LivelloConto;
use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;

/**
 * Tabella del bilancio di verifica: $bilancio, $nature, $classeTabella,
 * $classeNumero, $collegamenti, $soggetto, $esercizio, $dal, $al.
 */
$rientro = ['M' => 0, 'C' => 1, 'S' => 2];
?>
<table class="<?= Html::e($classeTabella) ?>">
    <thead><tr><th>Codice</th><th>Conto</th><th class="<?= Html::e($classeNumero) ?>">Dare</th><th class="<?= Html::e($classeNumero) ?>">Avere</th><th class="<?= Html::e($classeNumero) ?>">Saldo dare</th><th class="<?= Html::e($classeNumero) ?>">Saldo avere</th></tr></thead>
    <tbody>
        <?php foreach ($bilancio['righe'] as ['conto' => $conto, 'dare' => $dare, 'avere' => $avere, 'saldo' => $saldo]): ?>
            <tr class="livello-<?= Html::e($conto->livello->value) ?> <?= $conto->livello === LivelloConto::mastro ? 'fw-bold table-light' : ($conto->livello === LivelloConto::conto ? 'fw-semibold' : '') ?>">
                <td class="font-monospace text-nowrap"><?= Html::e($conto->codice) ?></td>
                <td style="padding-left: <?= 0.4 + $rientro[$conto->livello->value] * 1.1 ?>rem">
                    <?php if ($collegamenti && $conto->livello === LivelloConto::sottoconto): ?>
                        <a href="/stampa-contabile/scheda/soggetto/<?= Html::e($soggetto->id) ?>/conto/<?= Html::e($conto->id) ?>/anno/<?= Html::e($esercizio->anno) ?>/dal/<?= Html::e($dal->format('Y-m-d')) ?>/al/<?= Html::e($al->format('Y-m-d')) ?>" class="text-decoration-none"><?= Html::e($conto->descrizione) ?></a>
                    <?php else: ?>
                        <?= Html::e($conto->descrizione) ?>
                    <?php endif; ?>
                </td>
                <td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($dare, true)) ?></td>
                <td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($avere, true)) ?></td>
                <td class="<?= Html::e($classeNumero) ?>"><?= $saldo > 0 ? Html::e(Importo::euro($saldo)) : '' ?></td>
                <td class="<?= Html::e($classeNumero) ?>"><?= $saldo < 0 ? Html::e(Importo::euro(-$saldo)) : '' ?></td>
            </tr>
        <?php endforeach; ?>
        <tr class="totale table-light fw-semibold"><td colspan="2">Totale</td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($bilancio['dare'])) ?></td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($bilancio['avere'])) ?></td><td colspan="2"></td></tr>
    </tbody>
</table>
<table class="<?= Html::e($classeTabella) ?>" style="width: auto; margin-top: 1rem">
    <tbody>
        <?php foreach ($nature as $natura): ?>
            <?php if (isset($bilancio['nature'][$natura->value])): ?>
                <?php $saldo = $bilancio['nature'][$natura->value]; ?>
                <tr><td><?= Html::e($natura->getLabel()) ?></td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro(abs($saldo))) ?> <?= $saldo >= 0 ? 'D' : 'A' ?></td></tr>
            <?php endif; ?>
        <?php endforeach; ?>
        <tr class="totale table-light fw-semibold"><td><?= Html::e($risultato) ?></td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro(abs($bilancio['risultato']))) ?> (<?= Html::e($bilancio['risultato'] >= 0 ? $utile : $perdita) ?>)</td></tr>
    </tbody>
</table>
