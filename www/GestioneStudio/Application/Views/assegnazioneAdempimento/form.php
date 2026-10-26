<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$adempimentoScelto = Html::valore($formEntity, 'adempimento');

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($soggetto->nominativo) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?php if ($nuova): ?>
                <div class="col-12">
                    <label for="adempimento" class="form-label"><?= Html::e($campi['adempimento']) ?> <span class="text-danger">*</span></label>
                    <select class="form-select <?= Html::classeErrore($errors, 'adempimento') ?>" id="adempimento" name="adempimento" required>
                        <option value=""><?= Html::e($selezionare) ?></option>
                        <?php foreach ($adempimenti as $gruppo => $voci): ?>
                            <optgroup label="<?= Html::e($gruppo) ?>">
                                <?php foreach ($voci as $id => $denominazione): ?>
                                    <option value="<?= Html::e($id) ?>" <?= $adempimentoScelto === Html::e($id) ? 'selected' : '' ?>><?= Html::e($denominazione) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <?= Html::messaggioErrore($errors, 'adempimento', 'Seleziona l\'adempimento.') ?>
                </div>
            <?php else: ?>
                <div class="col-12 fw-semibold"><?= Html::e($assegnazione->adempimento->denominazione) ?></div>
            <?php endif; ?>
            <?= Campo::data('validoDal', $campi['validoDal'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            <?= Campo::data('validoAl', $campi['validoAl'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <?= Campo::selezione('responsabile', $campi['responsabile'], $formEntity, $errors, $operatori, ['colonna' => 'col-md-6', 'vuota' => $nessuno]) ?>
            <?= Campo::testo('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-12', 'attributi' => 'maxlength="255"']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>

<?php if (count($storico) > 0): ?>
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-white fw-semibold"><?= Html::e($titoloStorico) ?></div>
        <ul class="list-group list-group-flush">
            <?php foreach ($storico as $versione): ?>
                <li class="list-group-item d-flex align-items-center gap-2 small">
                    <span class="me-auto"><?= Html::data($versione->validoDal) ?> – <?= $versione->validoAl === null ? Html::e($sempre) : Html::data($versione->validoAl) ?>
                        <span class="text-body-secondary ms-2"><?= Html::e($versione->responsabile?->getNomeCompleto() ?? '') ?></span></span>
                    <a href="/assegnazione-adempimento/update/assegnazione/<?= Html::e($versione->id) ?>" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['modifica']) ?>"><i class="bi bi-pencil"></i></a>
                    <form method="post" action="/assegnazione-adempimento/delete/assegnazione/<?= Html::e($versione->id) ?>" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button></form>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
