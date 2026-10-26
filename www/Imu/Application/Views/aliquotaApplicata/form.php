<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/contribuente-imu/show/soggetto/' . $soggetto->id . '/anno/' . $anno;
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$scelta = Html::valore($formEntity, 'aliquotaDifferenziata');

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($soggetto->nominativo) ?> · <?= Html::e($immobile->getIdentificativo()) ?> · <?= Html::e($immobile->getUbicazione()) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
            <span class="fw-semibold me-auto"><?= Html::e($campi['aliquotaDifferenziata']) ?></span>
            <?php if ($prospetto['prospetto'] !== null): ?>
                <a href="/prospetto-aliquote/show/prospetto/<?= Html::e($prospetto['prospetto']->id) ?>" class="small"><?= Html::e($prospetto['origine']) ?></a>
            <?php else: ?>
                <span class="small text-warning-emphasis"><?= Html::e($prospetto['origine']) ?></span>
            <?php endif; ?>
        </div>
        <div class="list-group list-group-flush">
            <label class="list-group-item d-flex gap-2">
                <input class="form-check-input flex-shrink-0" type="radio" name="aliquotaDifferenziata" value="" <?= $scelta === '' ? 'checked' : '' ?>>
                <span><?= Html::e($aliquotaBase) ?></span>
            </label>
            <?php foreach ($differenziate as $differenziata): ?>
                <label class="list-group-item d-flex gap-2">
                    <input class="form-check-input flex-shrink-0" type="radio" name="aliquotaDifferenziata" value="<?= Html::e($differenziata->id) ?>" <?= $scelta === Html::e($differenziata->id) ? 'checked' : '' ?>>
                    <span>
                        <span class="badge text-bg-primary me-1"><?= Html::e(number_format($differenziata->aliquota, 2, ',', '')) ?>%</span>
                        <span class="small fw-semibold"><?= Html::e($differenziata->fattispecie?->getLabel() ?? '') ?></span>
                        <span class="d-block small text-body-secondary" style="white-space: pre-line"><?= Html::e($differenziata->descrizione) ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="card-body small text-body-secondary">
            <?= count($differenziate) === 0 ? Html::e($nessunaDifferenziata) : Html::e($verificaPdf) ?>
            <?= Html::messaggioErrore($errors, 'aliquotaDifferenziata', '') ?>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::testo('aliquota', $campi['aliquotaStudio'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::testo('motivo', $campi['motivo'], $formEntity, $errors, ['colonna' => 'col-md-8', 'attributi' => 'maxlength="255"']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php if (isset($aliquotaApplicata->id)): ?>
    <form method="post" action="/aliquota-applicata/delete/aliquota-applicata/<?= Html::e($aliquotaApplicata->id) ?>" class="mt-3" data-conferma="<?= Html::e($confermaElimina) ?>">
        <?= $campoCsrf ?>
        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i><?= Html::e($aliquotaBase) ?></button>
    </form>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
