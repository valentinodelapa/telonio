<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($adempimento->denominazione) ?> · <?= Html::e($proroga->termineAdempimento->periodo) ?> <?= Html::e($proroga->anno) ?> · <?= Html::e($campi['termineOrdinario']) ?> <?= Html::data($ordinario) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::data('data', $campi['termine'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            <?= Campo::selezioneEnum('condizione', $campi['condizione'], $formEntity, $errors, $condizioni, ['richiesto' => true, 'colonna' => 'col-md-5', 'aiuto' => $aiutoCondizione]) ?>
            <?= Campo::testo('provvedimento', $campi['provvedimento'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-12', 'attributi' => 'maxlength="255"']) ?>
            <?= Campo::areaTesto('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-12']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
