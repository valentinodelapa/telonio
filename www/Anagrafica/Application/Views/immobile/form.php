<?php

use CommSuite\Application\HelperClasses\Campo;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

$tipoCatasto = $immobile->tipoCatasto;
if ($soggetto !== null) {
    $urlAnnulla = '/soggetto/show/soggetto/' . $soggetto->id . '/scheda/immobili';
} else {
    $urlAnnulla = $nuovo ? '/immobile/index' : '/immobile/show/immobile/' . $immobile->id;
}

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><i class="bi <?= Html::e($tipoCatasto->getIcona()) ?> me-2"></i><?= Html::e($pageTitle) ?></h1>
<?php if ($soggetto !== null): ?>
    <p class="text-body-secondary"><?= Html::e($soggetto->nominativo) ?> · <?= Html::e($aiutoEsistente) ?></p>
<?php endif; ?>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneIdentificativo) ?></div>
        <div class="card-body row g-3">
            <?= Campo::autocompleta('comune', $campi['comune'], $formEntity, $errors, '/tabelle/comuni', isset($formEntity->comune) ? $formEntity->comune->getEtichetta() : null, ['richiesto' => true, 'colonna' => 'col-md-5']) ?>
            <?= Campo::testo('sezione', $campi['sezione'], $formEntity, $errors, ['colonna' => 'col-md-1', 'attributi' => 'maxlength="5"']) ?>
            <?= Campo::testo('foglio', $campi['foglio'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'maxlength="10"']) ?>
            <?= Campo::testo('particella', $campi['particella'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'maxlength="10"']) ?>
            <?= Campo::testo('subalterno', $campi['subalterno'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="10"']) ?>
            <?= Campo::testo('indirizzo', $campi['indirizzo'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'maxlength="150"']) ?>
            <?= Campo::testo('numeroCivico', $campi['numeroCivico'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="20"']) ?>
            <?= Campo::testo('piano', $campi['piano'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="10"']) ?>
            <?= Campo::testo('interno', $campi['interno'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="10"']) ?>
            <?= Campo::testo('descrizione', $campi['descrizioneImmobile'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'maxlength="255" placeholder="Es. appartamento, box auto, negozio"']) ?>
            <?= Campo::testo('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneTavolare) ?></div>
        <div class="card-body row g-3">
            <?= Campo::testo('partitaTavolare', $campi['partitaTavolare'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="7"']) ?>
            <?= Campo::testo('corpoTavolare', $campi['corpoTavolare'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="3"']) ?>
            <?= Campo::testo('comuneCatastale', $campi['comuneCatastale'], $formEntity, $errors, ['colonna' => 'col-md-5', 'attributi' => 'maxlength="50"']) ?>
            <?= Campo::testo('porzioneMateriale', $campi['porzioneMateriale'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="12"']) ?>
            <div class="col-12 form-text mt-1"><?= Html::e($aiutoTavolare) ?></div>
        </div>
    </div>

    <?php if ($nuovo): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneDatiCatastali) ?></div>
            <div class="card-body row g-3">
                <?php $dcEntity = $datiCatastaliEntity; $dcErrors = $datiCatastaliErrors; $mostraValidoAl = false; ?>
                <?php require __DIR__ . '/../commonParts/campiDatiCatastali.php'; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($soggetto !== null): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= Html::e($sezionePossesso) ?></div>
            <div class="card-body row g-3">
                <?php $pEntity = $possessoEntity; $pErrors = $possessoErrors; $prefissoPossesso = 'possesso'; ?>
                <?php require __DIR__ . '/../commonParts/campiPossesso.php'; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
