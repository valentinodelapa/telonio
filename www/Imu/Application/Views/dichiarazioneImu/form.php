<?php

use CommSuite\Application\HelperClasses\Campo;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;
use Imu\Application\Forms\DichiarazioneImuForm;

$urlAnnulla = '/dichiarazione-imu/show/dichiarazione/' . $dichiarazione->id;
// dopo un invio con errori il dichiarante è quello inviato, non quello dell'entità
$dichiaranteScelto = isset($formEntity->dichiarante) ? $formEntity->dichiarante : null;
$dichiaranteCorrente = $dichiaranteScelto === null ? null : $dichiaranteScelto->nominativo . ' - ' . $dichiaranteScelto->codiceFiscale;
$intermediariScelte = [];
foreach ($intermediari as $intermediario) {
    $intermediariScelte[$intermediario->id] = $intermediario->soggetto->nominativo . ' - ' . $intermediario->soggetto->codiceFiscale;
}

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($dichiarazione->soggetto->nominativo) ?> · <?= Html::e($dichiarazione->modello->getLabel()) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body row g-3">
            <?= Campo::selezioneEnum('tipologia', $campi['tipologia'], $formEntity, $errors, $tipologie, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            <?= Campo::data('dataDichiarazione', $campi['dataDichiarazione'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <div class="w-100 m-0"></div>
            <?= Campo::autocompleta('dichiarante', $campi['dichiarante'], $formEntity, $errors, '/soggetto/cerca', $dichiaranteCorrente, ['colonna' => 'col-md-6', 'aiuto' => $aiutoDichiarante]) ?>
            <?= Campo::selezioneEnum('codiceCarica', $campi['codiceCarica'], $formEntity, $errors, $codiciCarica, ['colonna' => 'col-md-6', 'vuota' => $nessuna]) ?>
            <?= Campo::selezione('intermediario', $campi['intermediario'], $formEntity, $errors, $intermediariScelte, ['colonna' => 'col-md-6', 'vuota' => $nessuno]) ?>
            <?= Campo::data('dataImpegno', $campi['dataImpegno'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <?= Campo::areaTesto('annotazioni', $campi['annotazioni'], $formEntity, $errors, ['colonna' => 'col-12', 'righe' => 3, 'attributi' => 'maxlength="500"']) ?>
        </div>
    </div>
    <?php if ($dichiarazione->isEnc()): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold"><?= Html::e($quadroC) ?></div>
            <div class="card-body row g-3">
                <?php foreach (DichiarazioneImuForm::IMPORTI_ENC as $campo): ?>
                    <?= Campo::testo($campo, $campi[$campo], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'inputmode="decimal"']) ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
