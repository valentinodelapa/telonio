<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/immobile/show/immobile/' . $immobile->id;
$tipoCatasto = $immobile->tipoCatasto;
$dcEntity = $formEntity;
$dcErrors = $errors;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($immobile->tipoCatasto->getLabel()) ?> <?= Html::e($immobile->getIdentificativo()) ?> · <?= Html::e($nuovo ? $aiutoVariazione : $aiutoRettifica) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?php require __DIR__ . '/../commonParts/campiDatiCatastali.php'; ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
