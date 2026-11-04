<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/dichiarazione/show/dichiarazione/' . $dichiarazione->id . '/quadro/' . $quadro . ($modulo > 1 ? '/modulo/' . $modulo : '');

require Layout::percorso('header');
?>
<h1 class="h4 mb-1"><?= Html::e($quadro . ' - ' . $denominazioneQuadro) ?></h1>
<p class="text-body-secondary small"><?= Html::e($dichiarazione->getDescrizione()) ?> · <?= Html::e($soggetto->nominativo) ?> · <?= Html::e($etichettaModulo) ?>. <?= Html::e($aiutoQuadro) ?></p>
<?php if (count($erroriCampi) > 0): ?>
    <div class="alert alert-danger small py-2"><ul class="mb-0"><?php foreach ($erroriCampi as $errore): ?><li><?= Html::e($errore) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post">
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light"><tr><th><?= Html::e($campi['rigo']) ?></th><th><?= Html::e($campi['descrizione']) ?></th><th style="width: 14rem"><?= Html::e($campi['valore']) ?></th><th class="text-center"><?= Html::e($campi['ripristina']) ?></th></tr></thead>
                <tbody>
                    <?php foreach ($righe as ['campo' => $campo, 'valore' => $valore, 'manuale' => $manuale]): ?>
                        <?php
                        $codice = $campo->codice;
                        $mostrato = is_array($inviati) ? (string) ($inviati[$codice] ?? '') : ($campo->formato?->isImporto() ? (string) $valore : ($campo->formato?->mostra($valore) ?? (string) $valore));
                        ?>
                        <tr class="<?= isset($erroriCampi[$codice]) ? 'table-danger' : '' ?>">
                            <td class="text-nowrap small font-monospace" title="<?= Html::e($codice) ?>"><?= Html::e($campo->getEtichettaRigo()) ?><span class="text-body-secondary"> c. <?= Html::e($campo->getColonna()) ?></span></td>
                            <td class="small"><?= Html::e($campo->descrizione) ?><span class="text-body-secondary"> · <?= Html::e($campo->formato?->getLabel() ?? $senzaFormato) ?></span></td>
                            <td>
                                <?php if ($campo->formato === null): ?>
                                    <span class="small text-body-secondary"><?= Html::e($senzaFormato) ?></span>
                                <?php elseif ($campo->formato->isCasella()): ?>
                                    <input class="form-check-input" type="checkbox" name="valori[<?= Html::e($codice) ?>]" value="1" <?= (is_array($inviati) ? isset($inviati[$codice]) : $valore === '1') ? 'checked' : '' ?> aria-label="<?= Html::e($codice) ?>">
                                <?php else: ?>
                                    <input type="text" class="form-control form-control-sm <?= $campo->formato->isImporto() ? 'text-end' : '' ?>" name="valori[<?= Html::e($codice) ?>]" value="<?= Html::e($mostrato) ?>" aria-label="<?= Html::e($codice) ?>">
                                <?php endif; ?>
                                <?php if ($manuale): ?><span class="badge text-bg-warning"><?= Html::e($badgeManuale) ?></span><?php endif; ?>
                            </td>
                            <td class="text-center"><?php if ($manuale): ?><input class="form-check-input" type="checkbox" name="ripristina[]" value="<?= Html::e($codice) ?>" title="<?= Html::e($aiutoRipristina) ?>"><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
