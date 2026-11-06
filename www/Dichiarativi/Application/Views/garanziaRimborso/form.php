<?php

use Dichiarativi\Application\Enumerations\TipoGaranzia;
use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/contribuente-dichiarativi/show/soggetto/' . $soggetto->id . '/anno/' . $anno;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($soggetto->nominativo) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::testo('annoImposta', $campi['annoImposta'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'inputmode="numeric"']) ?>
            <?= Campo::selezioneEnum('tipo', $campi['tipoGaranzia'], $formEntity, $errors, TipoGaranzia::cases(), ['richiesto' => true, 'colonna' => 'col-md-9']) ?>
            <?= Campo::testo('garante', $campi['garante'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'maxlength="120"']) ?>
            <?= Campo::testo('estremi', $campi['estremiGaranzia'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="60"']) ?>
            <?= Campo::testo('importo', $campi['importoGarantito'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::data('dataRilascio', $campi['dataRilascio'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            <?= Campo::spunta('compensazioneGruppo', $campi['compensazioneGruppo'], $formEntity, $errors, ['colonna' => 'col-12']) ?>
            <?= Campo::data('dataRimborso', $campi['dataRimborso'], $formEntity, $errors, ['colonna' => 'col-md-3', 'aiuto' => $aiutoDataRimborso]) ?>
            <?= Campo::data('dataScadenza', $campi['dataScadenzaGaranzia'], $formEntity, $errors, ['colonna' => 'col-md-3', 'aiuto' => $aiutoScadenza]) ?>
            <?= Campo::data('dataSvincolo', $campi['dataSvincolo'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <?= Campo::data('dataRichiestaDocumenti', $campi['dataRichiestaDocumenti'], $formEntity, $errors, ['colonna' => 'col-md-3', 'aiuto' => $aiutoDocumenti]) ?>
            <?= Campo::data('dataConsegnaDocumenti', $campi['dataConsegnaDocumenti'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
