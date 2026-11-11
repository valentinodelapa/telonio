<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/contribuente-dichiarativi/show/soggetto/' . $soggetto->id . '/anno/' . $anno;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($soggetto->nominativo) ?> · <?= Html::e($campi['annoImposta']) ?> <?= Html::e($anno) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiutoCertificazioneAutonomo) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body row g-3">
            <?= Campo::testo('codiceFiscaleSostituto', $campi['codiceFiscaleSostituto'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'maxlength="16"']) ?>
            <?= Campo::testo('denominazioneSostituto', $campi['denominazioneSostituto'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-9', 'attributi' => 'maxlength="100"']) ?>
            <?= Campo::selezione('causale', $campi['causaleCu'], $formEntity, $errors, $causali, ['richiesto' => true, 'colonna' => 'col-md-12']) ?>
            <?= Campo::testo('ammontareLordo', $campi['ammontareLordo'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::testo('sommeNonSoggette', $campi['sommeNonSoggette'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::testo('ritenuteAcconto', $campi['ritenuteAcconto'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::testo('ritenuteImposta', $campi['ritenuteImposta'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::testo('ritenuteSospese', $campi['ritenuteSospese'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::testo('addizionaleRegionale', $campi['addizionaleRegionaleTrattenuta'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::testo('addizionaleComunale', $campi['addizionaleComunaleTrattenuta'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::testo('speseInerenti', $campi['speseInerenti'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"', 'aiuto' => $aiutoSpeseInerenti]) ?>
            <?= Campo::testo('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-md-12', 'attributi' => 'maxlength="255"']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
