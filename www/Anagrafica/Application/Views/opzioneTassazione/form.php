<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/soggetto/show/soggetto/' . $soggetto->id . '/scheda/fiscali';
$consolidante = isset($formEntity->consolidante) ? $formEntity->consolidante : null;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($soggetto->nominativo) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::selezioneEnum('tipoOpzione', $campi['tipoOpzione'], $formEntity, $errors, $tipiOpzione, ['richiesto' => true, 'vuota' => $selezionare, 'colonna' => 'col-md-8']) ?>
            <?= Campo::testo('annoInizio', $campi['annoInizio'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'maxlength="4" inputmode="numeric"']) ?>
            <?= Campo::testo('annoFine', $campi['annoFine'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'maxlength="4" inputmode="numeric"']) ?>
            <?= Campo::autocompleta('consolidante', $campi['consolidante'], $formEntity, $errors, '/soggetto/cerca', $consolidante !== null ? $consolidante->nominativo . ' - ' . $consolidante->codiceFiscale : null, ['colonna' => 'col-md-8', 'aiuto' => $aiutoConsolidante]) ?>
            <?= Campo::testo('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-12', 'attributi' => 'maxlength="255"']) ?>
            <p class="small text-body-secondary mb-0"><?= Html::e($aiutoOpzioneTassazione) ?></p>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
