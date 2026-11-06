<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/dichiarazione/show/dichiarazione/' . $dichiarazione->id . '/quadro/VF' . ($modulo > 1 ? '/modulo/' . $modulo : '');
$importoRigo = fn(int $rigo): string => isset($esistenti[$rigo]) ? number_format($esistenti[$rigo]->importo, 2, ',', '.') : (($rigo === 3) && (count($proposta['beni']) > 0) ? number_format($proposta['totale'], 2, ',', '.') : '');

require Layout::percorso('header');
?>
<h1 class="h4 mb-1"><?= Html::e($prospettoD) ?></h1>
<p class="text-body-secondary small"><?= Html::e($dichiarazione->getDescrizione()) ?> · <?= Html::e($soggetto->nominativo) ?> · <?= Html::e($etichettaModulo) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiutoProspettoD) ?></p>
<?php if (count($erroriCampi) > 0): ?>
    <div class="alert alert-danger small py-2"><ul class="mb-0"><?php foreach ($erroriCampi as $errore): ?><li><?= Html::e($errore) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post">
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light"><tr><th><?= Html::e($campi['rigo']) ?></th><th><?= Html::e($campi['descrizione']) ?></th><th style="width: 12rem"><?= Html::e($campi['importoRettifica']) ?></th><th><?= Html::e($campi['notaRettifica']) ?></th></tr></thead>
                <tbody>
                    <?php foreach ($righi as $rigo => $descrizione): ?>
                        <tr class="<?= isset($erroriCampi[$rigo]) ? 'table-danger' : '' ?>">
                            <td class="small font-monospace"><?= Html::e($rigo) ?></td>
                            <td class="small"><?= Html::e($descrizione) ?></td>
                            <td><input type="text" class="form-control form-control-sm text-end" name="importi[<?= Html::e($rigo) ?>]" value="<?= Html::e($importoRigo($rigo)) ?>" inputmode="decimal" aria-label="<?= Html::e($descrizione) ?>"></td>
                            <td><input type="text" class="form-control form-control-sm" name="note[<?= Html::e($rigo) ?>]" value="<?= Html::e($esistenti[$rigo]->nota ?? '') ?>" maxlength="255" aria-label="<?= Html::e($campi['notaRettifica']) ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (count($proposta['beni']) > 0): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header"><?= Html::e($titoloPropostaRettifica) ?></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th><?= Html::e($campi['descrizione']) ?></th><th><?= Html::e($campi['annoAcquisto']) ?></th><th class="text-end"><?= Html::e($campi['impostaDetratta']) ?></th><th class="text-end"><?= Html::e($campi['importoRettifica']) ?></th><th class="text-center" title="<?= Html::e($aiutoCoefficienteElevato) ?>"><?= Html::e($campi['coefficienteElevato']) ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($proposta['beni'] as $bene): ?>
                            <tr class="small <?= $bene['escluso'] ? 'table-secondary' : '' ?>"><td><?= Html::e($bene['descrizione']) ?></td><td><?= Html::e($bene['anno']) ?></td><td class="text-end"><?= Html::e(number_format($bene['imposta'], 2, ',', '.')) ?></td><td class="text-end"><?= Html::e(number_format($bene['importo'], 2, ',', '.')) ?></td><td class="text-center"><input type="checkbox" class="form-check-input" name="esclusi[<?= Html::e($bene['riga']->id) ?>]" value="1" aria-label="<?= Html::e($campi['coefficienteElevato']) ?>" <?= $bene['escluso'] ? 'checked' : '' ?>></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr class="small fw-semibold"><td colspan="3"><?= Html::e($campi['totaleProposto']) ?></td><td class="text-end"><?= Html::e(number_format($proposta['totale'], 2, ',', '.')) ?></td><td></td></tr></tfoot>
                </table>
            </div>
        </div>
    <?php endif; ?>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
