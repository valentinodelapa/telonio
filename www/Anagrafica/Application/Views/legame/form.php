<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/soggetto/show/soggetto/' . $soggetto->id . '/scheda/legami';
$collegato = isset($formEntity->soggettoCollegato) ? $formEntity->soggettoCollegato : null;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($soggetto->nominativo) ?> · <?= Html::e($aiutoLegame) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::autocompleta('soggettoCollegato', $campi['soggettoCollegato'], $formEntity, $errors, '/soggetto/cerca', $collegato !== null ? $collegato->nominativo . ' - ' . $collegato->codiceFiscale : null, ['richiesto' => true, 'colonna' => 'col-md-8', 'segnaposto' => $segnapostoCollegato]) ?>
            <?= Campo::selezioneEnum('ruolo', $campi['ruolo'], $formEntity, $errors, $ruoli, ['richiesto' => true, 'vuota' => $selezionare, 'colonna' => 'col-md-4']) ?>
            <?= Campo::data('validoDal', $campi['validoDal'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            <?= Campo::data('validoAl', $campi['validoAl'], $formEntity, $errors, ['colonna' => 'col-md-3', 'aiuto' => $aiutoValidoAl]) ?>
            <?= Campo::testo('quotaPartecipazione', $campi['quotaPartecipazione'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::testo('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-12', 'attributi' => 'maxlength="255"']) ?>
        </div>
    </div>
    <div class="card shadow-sm mt-3">
        <div class="card-header"><?= Html::e($sezioneFamiliare) ?></div>
        <div class="card-body row g-3">
            <p class="small text-body-secondary mb-0"><?= Html::e($aiutoFamiliare) ?></p>
            <?= Campo::spunta('disabile', $campi['disabile'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
            <?= Campo::spunta('convivente', $campi['convivente'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
            <?= Campo::spunta('affidamento', $campi['affidamento'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
            <?= Campo::selezione('percentualeDetrazione', $campi['percentualeDetrazione'], $formEntity, $errors, $percentualiDetrazione, ['vuota' => $percentualeAutomatica, 'colonna' => 'col-md-6']) ?>
        </div>
    </div>
    <p class="small text-body-secondary mt-2"><a href="/soggetto/index" target="_blank"><?= Html::e($aiutoNuovoCollegato) ?></a></p>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
