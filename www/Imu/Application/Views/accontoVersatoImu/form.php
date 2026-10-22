<?php

use CommSuite\Application\HelperClasses\Campo;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

$urlAnnulla = '/contribuente-imu/show/soggetto/' . $soggetto->id . '/anno/' . $anno;
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($soggetto->nominativo) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::testo('codiceComune', $campi['codiceComune'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'maxlength="4" list="comuni-calcolo" style="text-transform: uppercase"']) ?>
            <datalist id="comuni-calcolo"><?php foreach ($comuni as $codice => $etichetta): ?><option value="<?= Html::e($codice) ?>"><?= Html::e($etichetta) ?></option><?php endforeach; ?></datalist>
            <?= Campo::selezione('codiceTributo', $campi['codiceTributo'], $formEntity, $errors, $codiciTributo, ['richiesto' => true, 'colonna' => 'col-md-3', 'vuota' => $selezionare]) ?>
            <?= Campo::testo('importo', $campi['importo'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::data('dataVersamento', $campi['dataVersamento'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <?= Campo::testo('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-12', 'attributi' => 'maxlength="255"']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php if (isset($acconto->id)): ?>
    <form method="post" action="/acconto-versato-imu/delete/acconto/<?= Html::e($acconto->id) ?>" class="mt-3" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i><?= Html::e($azioni['elimina']) ?></button></form>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
