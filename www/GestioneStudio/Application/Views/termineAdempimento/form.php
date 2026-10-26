<?php

use CommSuite\Application\HelperClasses\Campo;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($adempimento->denominazione) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::testo('ordine', $campi['ordine'], $formEntity, $errors, ['tipo' => 'number', 'richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'min="1" max="99"']) ?>
            <?= Campo::testo('periodo', $campi['periodo'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-4', 'attributi' => 'maxlength="50"', 'aiuto' => $aiutoPeriodo]) ?>
            <?= Campo::testo('giorno', $campi['giorno'], $formEntity, $errors, ['tipo' => 'number', 'richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'min="0" max="31"']) ?>
            <?= Campo::selezione('mese', $campi['mese'], $formEntity, $errors, $mesi, ['richiesto' => true, 'colonna' => 'col-md-2']) ?>
            <?= Campo::testo('anniSuccessivi', $campi['anniSuccessivi'], $formEntity, $errors, ['tipo' => 'number', 'richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'min="0" max="5"']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
