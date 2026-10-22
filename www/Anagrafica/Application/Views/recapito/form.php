<?php

use CommSuite\Application\HelperClasses\Campo;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

$urlAnnulla = '/soggetto/show/soggetto/' . $soggetto->id . '/scheda/recapiti';

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($soggetto->nominativo) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::selezioneEnum('tipoRecapito', $campi['tipoRecapito'], $formEntity, $errors, $tipiRecapito, ['richiesto' => true, 'vuota' => $selezionare, 'colonna' => 'col-md-4']) ?>
            <?= Campo::testo('valore', $campi['valore'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-8', 'attributi' => 'maxlength="255"']) ?>
            <?= Campo::testo('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-md-8', 'attributi' => 'maxlength="255"']) ?>
            <?= Campo::spunta('principale', $campi['principale'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
