<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/soggetto/show/soggetto/' . $soggetto->id . '/scheda/documenti';

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($soggetto->nominativo) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::selezioneEnum('tipoDocumento', $campi['tipoDocumento'], $formEntity, $errors, $tipiDocumento, ['richiesto' => true, 'vuota' => $selezionare, 'colonna' => 'col-md-4']) ?>
            <?= Campo::testo('numero', $campi['numero'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-4', 'attributi' => 'maxlength="30"']) ?>
            <?= Campo::testo('rilasciatoDa', $campi['rilasciatoDa'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="100"']) ?>
            <?= Campo::data('dataRilascio', $campi['dataRilascio'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
            <?= Campo::data('dataScadenza', $campi['dataScadenza'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
