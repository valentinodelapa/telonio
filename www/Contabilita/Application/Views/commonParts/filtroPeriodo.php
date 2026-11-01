<?php

use Telonio\Application\HelperClasses\Html;

/**
 * Selezione del periodo delle stampe contabili: $urlPeriodo (azione senza
 * parametri), $dal, $al, $esercizio; $urlStampa per la versione da
 * stampare.
 */
?>
<form method="get" action="<?= Html::e($urlPeriodo) ?>" class="row g-2 align-items-end mb-3">
    <input type="hidden" name="anno" value="<?= Html::e($esercizio->anno) ?>">
    <div class="col-6 col-md-3">
        <label class="form-label small mb-0" for="dal"><?= Html::e($campi['dal']) ?></label>
        <input type="date" class="form-control form-control-sm" id="dal" name="dal" value="<?= Html::e($dal->format('Y-m-d')) ?>" min="<?= Html::e($esercizio->dataInizio->format('Y-m-d')) ?>" max="<?= Html::e($esercizio->dataFine->format('Y-m-d')) ?>">
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small mb-0" for="al"><?= Html::e($campi['al']) ?></label>
        <input type="date" class="form-control form-control-sm" id="al" name="al" value="<?= Html::e($al->format('Y-m-d')) ?>" min="<?= Html::e($esercizio->dataInizio->format('Y-m-d')) ?>" max="<?= Html::e($esercizio->dataFine->format('Y-m-d')) ?>">
    </div>
    <?php foreach ($parametriPeriodo ?? [] as $nome => $valore): ?>
        <input type="hidden" name="<?= Html::e($nome) ?>" value="<?= Html::e($valore) ?>">
    <?php endforeach; ?>
    <div class="col-auto"><button type="submit" class="btn btn-sm btn-outline-primary"><?= Html::e($azioni['aggiorna']) ?></button></div>
    <div class="col-auto ms-auto"><a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= Html::e($urlStampa) ?>"><i class="bi bi-printer me-1"></i><?= Html::e($azioni['versioneStampa']) ?></a></div>
</form>
