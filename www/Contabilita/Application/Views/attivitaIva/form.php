<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/registro-iva/index/soggetto/' . $soggetto->id;
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary mb-1"><?= Html::e($soggetto->nominativo) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::testo('numero', $campi['numeroAttivita'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'tipo' => 'number', 'attributi' => 'min="1" max="99"']) ?>
            <?= Campo::testo('descrizione', $campi['descrizione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-6', 'attributi' => 'maxlength="100"']) ?>
            <div class="col-md-4">
                <label class="form-label" for="ateco"><?= Html::e($campi['codiceAteco']) ?></label>
                <input type="text" class="form-control font-monospace" id="ateco" name="ateco" value="<?= Html::e($codiceAteco) ?>" placeholder="86.22.03" maxlength="8">
                <div class="form-text"><?= Html::e($aiutoAteco) ?></div>
            </div>
            <?= Campo::selezioneEnum('periodicita', $campi['periodicitaAttivita'], $formEntity, $errors, $periodicita, ['colonna' => 'col-md-4', 'vuota' => $periodicitaAzienda, 'aiuto' => $aiutoPeriodicita]) ?>
            <?= Campo::testo('dalAnno', $campi['dalAnno'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'tipo' => 'number']) ?>
            <?= Campo::testo('alAnno', $campi['alAnno'], $formEntity, $errors, ['colonna' => 'col-md-2', 'tipo' => 'number']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php if (isset($attivita->id)): ?>
    <form method="post" action="/attivita-iva/delete/attivita/<?= Html::e($attivita->id) ?>" class="mt-3" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i><?= Html::e($azioni['elimina']) ?></button></form>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
