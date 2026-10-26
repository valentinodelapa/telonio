<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/immobile/show/immobile/' . $immobile->id;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($immobile->tipoCatasto->getLabel()) ?> <?= Html::e($immobile->getIdentificativo()) ?> - <?= Html::e($immobile->getUbicazione()) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiutoGraffato) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::testo('sezione', $campi['sezione'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="5"']) ?>
            <?= Campo::testo('foglio', $campi['foglio'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'maxlength="10"']) ?>
            <?= Campo::testo('particella', $campi['particella'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'maxlength="10"']) ?>
            <?= Campo::testo('subalterno', $campi['subalterno'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="10"']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
