<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/gruppo-iva/index/soggetto/' . $soggetto->id;
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$maggioranza = isset($formEntity->maggioranza) ? $formEntity->maggioranza : null;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary mb-1"><?= Html::e($soggetto->nominativo) ?> · <?= Html::e($campi['controllante']) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::testo('annoInizio', $campi['annoInizio'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'tipo' => 'number', 'aiuto' => $aiutoAnnoInizio]) ?>
            <?= Campo::testo('annoRevoca', $campi['annoRevoca'], $formEntity, $errors, ['colonna' => 'col-md-3', 'tipo' => 'number', 'aiuto' => $aiutoRevoca]) ?>
            <div class="col-12"><div class="fw-semibold small"><?= Html::e($titoloMaggioranza) ?></div><div class="small text-body-secondary"><?= Html::e($aiutoMaggioranza) ?></div></div>
            <?= Campo::autocompleta('maggioranza', $campi['maggioranza'], $formEntity, $errors, '/soggetto/cerca', $maggioranza === null ? null : $maggioranza->nominativo . ' - ' . $maggioranza->codiceFiscale, ['colonna' => 'col-md-6']) ?>
            <?= Campo::data('dataPossessoMaggioranza', $campi['dataPossesso'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <?= Campo::testo('percentualeMaggioranza', $campi['percentualePossesso'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::testo('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-12', 'attributi' => 'maxlength="255"']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php if (isset($gruppo->id)): ?>
    <form method="post" action="/gruppo-iva/delete/gruppo/<?= Html::e($gruppo->id) ?>" class="mt-3" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i><?= Html::e($azioni['elimina']) ?></button></form>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
