<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;

/**
 * Tabella della scheda contabile: $scheda, $classeTabella, $classeNumero,
 * $collegamenti.
 */
$saldoTesto = fn(float $saldo): string => $saldo == 0 ? '0,00' : Importo::euro(abs($saldo)) . ($saldo > 0 ? ' D' : ' A');
?>
<table class="<?= Html::e($classeTabella) ?>">
    <thead><tr><th>Data</th><th>N.</th><th>Descrizione</th><th class="<?= Html::e($classeNumero) ?>">Dare</th><th class="<?= Html::e($classeNumero) ?>">Avere</th><th class="<?= Html::e($classeNumero) ?>">Saldo</th></tr></thead>
    <tbody>
        <tr class="table-light"><td colspan="5"><?= Html::e($saldoIniziale) ?></td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e($saldoTesto($scheda['saldoIniziale'])) ?></td></tr>
        <?php foreach ($scheda['movimenti'] as ['riga' => $riga, 'saldo' => $saldo]): ?>
            <?php $registrazione = $riga->registrazioneContabile; ?>
            <tr>
                <td class="text-nowrap"><?= Html::data($riga->dataRegistrazione) ?></td>
                <td><?= $collegamenti ? '<a href="/registrazione/show/registrazione/' . Html::e($registrazione->id) . '">' . Html::e($registrazione->numero) . '</a>' : Html::e($registrazione->numero) ?></td>
                <td><?= Html::e($riga->descrizione ?? $registrazione->getDescrizioneCompleta()) ?><?= ($registrazione->controparte === null) ? '' : '<span class="small text-body-secondary tenue"> · ' . Html::e($registrazione->controparte->denominazione) . '</span>' ?></td>
                <td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($riga->dare, true)) ?></td>
                <td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($riga->avere, true)) ?></td>
                <td class="<?= Html::e($classeNumero) ?>"><?= Html::e($saldoTesto($saldo)) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr class="totale table-light fw-semibold"><td colspan="3"><?= Html::e($saldoFinale) ?></td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($scheda['dare'])) ?></td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e(Importo::euro($scheda['avere'])) ?></td><td class="<?= Html::e($classeNumero) ?>"><?= Html::e($saldoTesto($scheda['saldoFinale'])) ?></td></tr>
    </tbody>
</table>
