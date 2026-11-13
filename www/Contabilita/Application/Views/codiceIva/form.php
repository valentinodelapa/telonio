<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/codice-iva/index';
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::testo('codice', $campi['codice'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'maxlength="10"']) ?>
            <?= Campo::testo('descrizione', $campi['descrizione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-10', 'attributi' => 'maxlength="150"']) ?>
            <?= Campo::testo('aliquota', $campi['aliquota'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::selezioneEnum('natura', $campi['naturaIva'], $formEntity, $errors, $nature, ['colonna' => 'col-md-6', 'vuota' => $nessuno]) ?>
            <?= Campo::testo('detraibilita', $campi['detraibilita'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::spunta('attivo', $campi['attivo'], $formEntity, $errors, ['colonna' => 'col-md-2']) ?>
            <?= Campo::spunta('inversioneContabile', $campi['inversioneContabile'], $formEntity, $errors, ['colonna' => 'col-12']) ?>
            <?= Campo::spunta('ventilazione', $campi['ventilazioneCodice'], $formEntity, $errors, ['colonna' => 'col-12']) ?>
            <?= Campo::testo('norma', $campi['norma'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'maxlength="255"']) ?>
            <?= Campo::testo('normaTestoUnico', $campi['normaTestoUnico'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'maxlength="255"']) ?>
            <?= Campo::data('validoFino', $campi['validoFino'], $formEntity, $errors, ['colonna' => 'col-md-3', 'aiuto' => $aiutoValidoFino]) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php if (isset($codice->id)): ?>
    <form method="post" action="/codice-iva/delete/codice/<?= Html::e($codice->id) ?>" class="mt-3" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i><?= Html::e($azioni['elimina']) ?></button></form>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
