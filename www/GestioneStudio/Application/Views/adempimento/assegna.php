<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>
<?php if ($numeroGiaAssegnati > 0): ?><p class="small"><?= Html::e($giaAssegnati) ?> <strong><?= Html::e($numeroGiaAssegnati) ?></strong></p><?php endif; ?>
<?php if ($errore !== null): ?><div class="alert alert-danger"><?= Html::e($errore) ?></div><?php endif; ?>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label for="validoDal" class="form-label"><?= Html::e($campi['validoDal']) ?> <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="validoDal" name="validoDal" value="<?= Html::e($decorrenza->format('Y-m-d')) ?>" required>
            </div>
            <div class="col-md-4">
                <label for="responsabile" class="form-label"><?= Html::e($campi['responsabile']) ?></label>
                <select class="form-select" id="responsabile" name="responsabile">
                    <option value=""><?= Html::e($nessuno) ?></option>
                    <?php foreach ($operatori as $id => $nome): ?>
                        <option value="<?= Html::e($id) ?>" <?= $responsabileScelto === $id ? 'selected' : '' ?>><?= Html::e($nome) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
    <div class="card shadow-sm">
        <?php if (count($candidati) === 0): ?>
            <div class="card-body text-center text-body-secondary"><?= Html::e($nessunCandidato) ?></div>
        <?php else: ?>
            <div class="card-header bg-white">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="selezionaTutti" data-seleziona-tutti=".casella-cliente">
                    <label class="form-check-label fw-semibold" for="selezionaTutti"><?= Html::e($seleziona) ?></label>
                </div>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($candidati as ['soggetto' => $soggetto, 'proposto' => $proposto]): ?>
                    <li class="list-group-item">
                        <div class="form-check mb-0">
                            <input class="form-check-input casella-cliente" type="checkbox" id="soggetto<?= Html::e($soggetto->id) ?>" name="soggetti[<?= Html::e($soggetto->id) ?>]" value="1" <?= $proposto ? 'checked' : '' ?>>
                            <label class="form-check-label" for="soggetto<?= Html::e($soggetto->id) ?>">
                                <?= Html::e($soggetto->nominativo) ?>
                                <span class="small text-body-secondary ms-1"><?= Html::e($soggetto->getDescrizioneTipo()) ?></span>
                                <?php if ($proposto): ?><span class="badge text-bg-success ms-1"><?= Html::e($etichettaProposto) ?></span><?php endif; ?>
                            </label>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
