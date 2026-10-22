<?php

use CommSuite\Application\HelperClasses\Campo;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

$urlAnnulla = $nuova ? '/contribuente/show/soggetto/' . $delega->soggetto->id : '/delega/show/delega/' . $delega->id;

require Layout::percorso('header');
?>
<h1 class="h3 mb-3"><i class="bi bi-credit-card-2-front me-2"></i><?= Html::e($pageTitle) ?></h1>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneTestata) ?></div>
        <div class="card-body row g-3">
            <div class="col-md-8">
                <div class="form-label"><?= Html::e($campi['soggetto']) ?></div>
                <div class="fw-semibold"><?= Html::e($delega->soggetto->nominativo) ?> <span class="small text-body-secondary font-monospace"><?= Html::e($delega->soggetto->codiceFiscale) ?></span></div>
            </div>
            <?= Campo::data('scadenza', $campi['scadenza'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-4', 'aiuto' => $nuova ? $aiutoScadenzaNuova : null]) ?>
            <?php if ($nuova): ?>
                <div class="col-12 small">
                    <?php if (count($dateDaVersare) === 0): ?>
                        <span class="text-body-secondary"><?= Html::e($nessunaDataDaVersare) ?></span>
                    <?php else: ?>
                        <span class="text-body-secondary me-1"><?= Html::e($dateConVersamenti) ?></span>
                        <?php foreach ($dateDaVersare as $data => $numero): ?>
                            <a href="/delega/create/soggetto/<?= Html::e($delega->soggetto->id) ?>/scadenza/<?= Html::e($data) ?>" class="badge rounded-pill text-decoration-none <?= $data === Html::data($delega->scadenza, 'Y-m-d') ? 'text-bg-primary' : 'text-bg-light border' ?> me-1"><?= Html::data(new DateTime($data)) ?> (<?= Html::e($numero) ?>)</a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php if ($conCrediti): ?>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="compensa" name="compensa" value="1" checked>
                            <label class="form-check-label" for="compensa"><?= Html::e($azioni['compensaTutti']) ?></label>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <?= Campo::selezioneEnum('modello', $campi['modello'], $formEntity, $errors, $modelli, ['richiesto' => true, 'colonna' => 'col-md-4']) ?>
            <?= Campo::testo('descrizione', $campi['descrizione'], $formEntity, $errors, ['colonna' => 'col-md-8', 'attributi' => 'maxlength="255"']) ?>
            <?= Campo::selezione('contoAddebito', $campi['contoAddebito'], $formEntity, $errors, $conti, ['vuota' => $nessunConto, 'colonna' => 'col-12', 'aiuto' => $nuova ? $aiutoContoNuova : $aiutoConto]) ?>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneCoobbligato) ?></div>
        <div class="card-body row g-3">
            <p class="small text-body-secondary mb-0"><?= Html::e($aiutoCoobbligato) ?></p>
            <?= Campo::testo('codiceFiscaleCoobbligato', $campi['codiceFiscaleCoobbligato'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'maxlength="16" style="text-transform: uppercase"']) ?>
            <?= Campo::selezioneEnum('codiceIdentificativo', $campi['codiceIdentificativo'], $formEntity, $errors, $codiciIdentificativi, ['vuota' => $selezionare, 'colonna' => 'col-md-6']) ?>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneDatiSezioni) ?></div>
        <div class="card-body row g-3">
            <p class="small text-body-secondary mb-0"><?= Html::e($aiutoDatiSezioni) ?></p>
            <?= Campo::testo('codiceUfficio', $campi['codiceUfficio'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="3" style="text-transform: uppercase"']) ?>
            <?= Campo::testo('codiceAtto', $campi['codiceAtto'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="11" inputmode="numeric"']) ?>
            <?= Campo::testo('identificativoOperazione', $campi['identificativoOperazione'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="18" style="text-transform: uppercase"']) ?>
            <?= Campo::testo('detrazioneImu', $campi['detrazioneImu'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::areaTesto('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-12', 'righe' => 2]) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
