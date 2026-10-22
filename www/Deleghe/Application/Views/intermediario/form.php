<?php

use CommSuite\Application\HelperClasses\Campo;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

$urlAnnulla = '/intermediario/index';
$soggettoScelto = isset($formEntity->soggetto) ? $formEntity->soggetto : null;

require Layout::percorso('header');
?>
<h1 class="h3 mb-3"><i class="bi bi-person-badge me-2"></i><?= Html::e($pageTitle) ?></h1>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::autocompleta('soggetto', $campi['intermediario'], $formEntity, $errors, '/soggetto/cerca', $soggettoScelto !== null ? $soggettoScelto->nominativo . ' - ' . $soggettoScelto->codiceFiscale : null, ['richiesto' => true, 'colonna' => 'col-md-8', 'segnaposto' => $segnapostoSoggetto]) ?>
            <?= Campo::spunta('predefinito', $campi['predefinito'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
            <?= Campo::testo('telefono', $campi['telefono'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="12" inputmode="tel"']) ?>
            <?= Campo::testo('email', $campi['email'], $formEntity, $errors, ['tipo' => 'email', 'colonna' => 'col-md-8', 'attributi' => 'maxlength="55"']) ?>
            <?= Campo::selezione('contoAddebito', $campi['contoIntermediario'], $formEntity, $errors, $conti, ['vuota' => $nessunConto, 'colonna' => 'col-12', 'aiuto' => $aiutoConto]) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
