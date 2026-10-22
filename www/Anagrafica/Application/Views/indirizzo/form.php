<?php

use CommSuite\Application\HelperClasses\Campo;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

$urlAnnulla = '/soggetto/show/soggetto/' . $soggetto->id . '/scheda/indirizzi';

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($soggetto->nominativo) ?> · <?= Html::e($nuovo ? $aiutoVariazione : $aiutoRettifica) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::selezioneEnum('tipoIndirizzo', $campi['tipoIndirizzo'], $formEntity, $errors, $tipiIndirizzo, ['richiesto' => true, 'vuota' => $selezionare]) ?>
            <?= Campo::data('validoDal', $campi['validoDal'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            <?= Campo::data('validoAl', $campi['validoAl'], $formEntity, $errors, ['colonna' => 'col-md-3', 'aiuto' => $aiutoValidoAl]) ?>
            <?= Campo::autocompleta('nazione', $campi['nazione'], $formEntity, $errors, '/tabelle/nazioni', $formEntity->nazione?->denominazione, ['richiesto' => true, 'colonna' => 'col-md-4']) ?>
            <?= Campo::testo('presso', $campi['presso'], $formEntity, $errors, ['colonna' => 'col-md-8', 'attributi' => 'maxlength="100"']) ?>
            <?= Campo::testo('indirizzo', $campi['indirizzo'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-8', 'attributi' => 'maxlength="150" placeholder="Via Roma"']) ?>
            <?= Campo::testo('numeroCivico', $campi['numeroCivico'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="20"']) ?>
            <?= Campo::testo('cap', $campi['cap'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="10"']) ?>
            <?= Campo::autocompleta('comune', $campi['comune'], $formEntity, $errors, '/tabelle/comuni', $formEntity->comune?->getEtichetta(), ['colonna' => 'col-md-5', 'aiuto' => $aiutoComune]) ?>
            <?= Campo::testo('frazione', $campi['frazione'], $formEntity, $errors, ['colonna' => 'col-md-5', 'attributi' => 'maxlength="100"']) ?>
            <?= Campo::testo('localitaEstera', $campi['localitaEstera'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'maxlength="100"', 'aiuto' => $aiutoLocalitaEstera]) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
