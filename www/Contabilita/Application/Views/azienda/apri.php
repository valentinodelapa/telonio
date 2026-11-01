<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/azienda/index';

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($azioni['apriContabilita']) ?></h1>
<p class="text-body-secondary mb-1"><?= Html::e($soggetto->nominativo) ?> · <?= Html::e($soggetto->partitaIva ?? $soggetto->codiceFiscale) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>
<?php if ($datiFiscali !== null): ?>
    <p class="small"><span class="text-body-secondary"><?= Html::e($campi['regime']) ?>:</span> <?= Html::e($datiFiscali->regimeFiscale?->getLabel() ?? '-') ?> · <span class="text-body-secondary"><?= Html::e($campi['periodicita']) ?>:</span> <?= Html::e($datiFiscali->periodicitaIva?->getLabel() ?? '-') ?></p>
<?php endif; ?>

<?php if (count($modelli) === 0): ?>
    <div class="alert alert-warning"><?= Html::e($senzaModelli) ?></div>
<?php else: ?>
    <form method="post" novalidate>
        <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
        <div class="card shadow-sm">
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label for="modello" class="form-label"><?= Html::e($campi['modello']) ?> <span class="text-danger">*</span></label>
                    <select class="form-select" id="modello" name="modello">
                        <?php foreach ($modelli as $modello): ?>
                            <option value="<?= Html::e($modello->id) ?>" <?= $modello->id === $modelloScelto ? 'selected' : '' ?>><?= Html::e($modello->denominazione) ?> (<?= Html::e($modello->isDellaSuite() ? $dellaSuite : $delloStudio) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?= Campo::data('dataInizio', $campi['dataInizio'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
                <?= Campo::data('dataFine', $campi['dataFine'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            </div>
        </div>
        <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
    </form>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
