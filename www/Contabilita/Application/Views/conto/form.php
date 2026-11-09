<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/piano-conti/show/piano/' . $pianoConti->id;
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$voceScelta = Html::valore($formEntity, 'voceCee');

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary mb-1"><?= Html::e($pianoConti->denominazione) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?php if (isset($conto->id)): ?>
                <div class="col-md-3"><label class="form-label"><?= Html::e($campi['codice']) ?></label><input type="text" class="form-control font-monospace" value="<?= Html::e($conto->codice) ?>" disabled></div>
            <?php else: ?>
                <?= Campo::testo('codice', $campi['codice'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'maxlength="20" placeholder="NN.NN.NNNN"']) ?>
            <?php endif; ?>
            <?= Campo::testo('descrizione', $campi['descrizione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-9', 'attributi' => 'maxlength="150"']) ?>
            <?= Campo::selezioneEnum('natura', $campi['natura'], $formEntity, $errors, $nature, ['richiesto' => true, 'colonna' => 'col-md-4', 'vuota' => $selezionare]) ?>
            <?= Campo::selezioneEnum('funzione', $campi['funzione'], $formEntity, $errors, $funzioni, ['colonna' => 'col-md-4', 'vuota' => $nessuno]) ?>
            <?= Campo::spunta('attivo', $campi['attivo'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
            <div class="col-12">
                <label for="voceCee" class="form-label"><?= Html::e($campi['voceCee']) ?></label>
                <select class="form-select <?= Html::classeErrore($errors, 'voceCee') ?>" id="voceCee" name="voceCee">
                    <option value=""><?= Html::e($nessuno) ?></option>
                    <?php foreach ($vociCee as $sezione => $voci): ?>
                        <optgroup label="<?= Html::e($sezione) ?>">
                            <?php foreach ($voci as $voce): ?>
                                <option value="<?= Html::e($voce->value) ?>" <?= $voceScelta === $voce->value ? 'selected' : '' ?>><?= Html::e($voce->getEtichetta()) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <?= Html::messaggioErrore($errors, 'voceCee', Campo::$messaggioErrore) ?>
            </div>
            <?= Campo::selezione('rigoRedditi', $campi['rigoRedditi'], $formEntity, $errors, $righiRedditi, ['colonna' => 'col-md-8', 'vuota' => $rigoDallaVoce, 'aiuto' => $aiutoRigoRedditi]) ?>
            <?= Campo::testo('percentualeDeducibile', $campi['percentualeDeducibile'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'inputmode="decimal"', 'aiuto' => $aiutoPercentualeDeducibile]) ?>
            <?= Campo::selezione('variazioneRedditi', $campi['variazioneRedditi'], $formEntity, $errors, $variazioniRedditi, ['colonna' => 'col-md-8', 'vuota' => $variazioneAltre, 'aiuto' => $aiutoVariazione]) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php if (isset($conto->id)): ?>
    <form method="post" action="/conto/delete/conto/<?= Html::e($conto->id) ?>" class="mt-3" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i><?= Html::e($azioni['elimina']) ?></button></form>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
