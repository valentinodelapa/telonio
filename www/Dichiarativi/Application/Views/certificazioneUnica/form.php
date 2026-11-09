<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/contribuente-dichiarativi/show/soggetto/' . $soggetto->id . '/anno/' . $anno;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($soggetto->nominativo) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiutoCertificazione) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneSostituto) ?></div>
        <div class="card-body row g-3">
            <?= Campo::testo('annoImposta', $campi['annoImposta'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'inputmode="numeric"']) ?>
            <?= Campo::testo('codiceFiscaleSostituto', $campi['codiceFiscaleSostituto'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'maxlength="16"']) ?>
            <?= Campo::testo('denominazioneSostituto', $campi['denominazioneSostituto'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-7', 'attributi' => 'maxlength="100"']) ?>
            <?= Campo::spunta('conguagliata', $campi['conguagliata'], $formEntity, $errors, ['colonna' => 'col-md-12']) ?>
            <?= Campo::testo('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-md-12', 'attributi' => 'maxlength="255"']) ?>
        </div>
    </div>
    <?php foreach ($sezioniPunti as $sezione => $punti): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= Html::e($sezione) ?></div>
            <div class="card-body row g-3">
                <?php foreach ($punti as $punto => $descrizione): ?>
                    <div class="col-md-4">
                        <label class="form-label small" for="punto-<?= Html::e($punto) ?>"><span class="badge text-bg-light me-1"><?= Html::e($punto) ?></span><?= Html::e($descrizione) ?></label>
                        <input type="text" class="form-control text-end<?= isset($erroriPunti[$punto]) ? ' is-invalid' : '' ?>" id="punto-<?= Html::e($punto) ?>" name="punto[<?= Html::e($punto) ?>]" value="<?= Html::e($valoriPunti[$punto] ?? '') ?>" inputmode="decimal">
                        <?php if (isset($erroriPunti[$punto])): ?><div class="invalid-feedback"><?= Html::e($errorePunto) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
