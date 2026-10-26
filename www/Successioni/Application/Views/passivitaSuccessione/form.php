<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/dichiarazione-successione/show/dichiarazione/' . $dichiarazione->id . '/scheda/attivo';

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($dichiarazione->getTitolo()) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body row g-3">
            <?= Campo::selezioneEnum('tipoPassivita', $campi['tipoPassivita'], $formEntity, $errors, $tipi, ['richiesto' => true, 'vuota' => $selezionare, 'colonna' => 'col-md-6']) ?>
            <?= Campo::testo('descrizione', $campi['descrizione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-6', 'attributi' => 'maxlength="250"']) ?>
            <?= Campo::testo('estremiDocumento', $campi['estremiDocumento'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-4', 'attributi' => 'maxlength="50"']) ?>
            <?= Campo::data('dataDocumento', $campi['dataDocumento'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            <?= Campo::testo('valore', $campi['importo'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'inputmode="numeric"']) ?>
            <?= Campo::testo('quotaNumeratore', $campi['quotaNumeratore'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'inputmode="numeric"']) ?>
            <?= Campo::testo('quotaDenominatore', $campi['quotaDenominatore'], $formEntity, $errors, ['colonna' => 'col-md-1', 'attributi' => 'inputmode="numeric"']) ?>
            <?= Campo::selezione('beneSuccessione', $campi['beneSuccessione'], $formEntity, $errors, $beni, ['vuota' => $nessuno, 'colonna' => 'col-12']) ?>
        </div>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneRipartizione) ?></div>
        <div class="card-body row g-2">
            <?php if ($erroreRipartizione !== null): ?><div class="col-12 small text-danger"><?= Html::e($erroreRipartizione) ?></div><?php endif; ?>
            <?php foreach ($beneficiari as $id => $beneficiario): ?>
                <div class="col-md-6 d-flex align-items-center gap-2">
                    <label class="me-auto small" for="quota<?= Html::e($id) ?>"><?= Html::e($beneficiario->getRiferimento() . ' ' . $beneficiario->soggetto->nominativo) ?></label>
                    <input type="text" class="form-control form-control-sm" style="width:7em" id="quota<?= Html::e($id) ?>" name="quote[<?= Html::e($id) ?>]" value="<?= Html::e($quoteInviate[$id] ?? (isset($quote[$id]) ? (string) $quote[$id] : '')) ?>" placeholder="es. 1/3">
                </div>
            <?php endforeach; ?>
            <div class="col-12 small text-body-secondary"><?= Html::e($aiutoRipartizione) ?></div>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
