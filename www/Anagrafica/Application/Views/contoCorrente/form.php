<?php

use CommSuite\Application\HelperClasses\Campo;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

$urlAnnulla = '/soggetto/show/soggetto/' . $soggetto->id . '/scheda/conti';

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($soggetto->nominativo) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::testo('iban', $campi['iban'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-7', 'attributi' => 'maxlength="42" style="text-transform: uppercase"']) ?>
            <?= Campo::testo('descrizione', $campi['descrizioneConto'], $formEntity, $errors, ['colonna' => 'col-md-5', 'attributi' => 'maxlength="100"']) ?>
            <?= Campo::spunta('predefinito', $campi['contoPredefinito'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
            <?= Campo::spunta('attivo', $campi['contoAttivo'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
