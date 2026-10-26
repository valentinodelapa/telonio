<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/dichiarazione-successione/show/dichiarazione/' . $dichiarazione->id . '/scheda/beneficiari';
$etichettaSoggetto = fn(?object $soggetto): ?string => $soggetto === null ? null : $soggetto->nominativo . ' - ' . $soggetto->codiceFiscale;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($dichiarazione->getTitolo()) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body row g-3">
            <?= Campo::autocompleta('soggetto', $campi['soggetto'], $formEntity, $errors, '/soggetto/cerca', $etichettaSoggetto($formEntity->soggetto ?? null), ['richiesto' => true, 'colonna' => 'col-md-6', 'aiuto' => $aiutoSoggetto]) ?>
            <?= Campo::selezioneEnum('tipoBeneficiario', $campi['tipoBeneficiario'], $formEntity, $errors, $tipi, ['richiesto' => true, 'colonna' => 'col-md-6']) ?>
            <?= Campo::selezioneEnum('gradoParentela', $campi['gradoParentela'], $formEntity, $errors, $gradi, ['richiesto' => true, 'vuota' => $selezionare, 'colonna' => 'col-md-6']) ?>
            <?= Campo::spunta('disabile', $campi['disabile'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
            <?= Campo::testo('certificatoreDisabilita', $campi['certificatoreDisabilita'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'maxlength="35"']) ?>
        </div>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneRinuncia) ?></div>
        <div class="card-body row g-3">
            <?= Campo::spunta('rinuncia', $campi['rinuncia'], $formEntity, $errors, ['colonna' => 'col-12']) ?>
            <?= Campo::autocompleta('rinunciaUfficio', $campi['rinunciaUfficio'], $formEntity, $errors, '/tabelle-successioni/uffici', $formEntity->rinunciaUfficio?->getEtichetta(), ['colonna' => 'col-md-5']) ?>
            <?= Campo::testo('rinunciaSerie', $campi['rinunciaSerie'], $formEntity, $errors, ['colonna' => 'col-md-1', 'attributi' => 'maxlength="2"']) ?>
            <?= Campo::testo('rinunciaNumero', $campi['rinunciaNumero'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="6"']) ?>
            <?= Campo::testo('rinunciaSottonumero', $campi['rinunciaSottonumero'], $formEntity, $errors, ['colonna' => 'col-md-1', 'attributi' => 'maxlength="3"']) ?>
            <?= Campo::data('rinunciaData', $campi['rinunciaData'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
        </div>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneRappresentazione) ?></div>
        <div class="card-body row g-3">
            <?= Campo::autocompleta('rappresentato', $campi['rappresentato'], $formEntity, $errors, '/soggetto/cerca', $etichettaSoggetto($formEntity->rappresentato ?? null), ['colonna' => 'col-md-6', 'aiuto' => $aiutoRappresentazione]) ?>
            <?= Campo::selezioneEnum('gradoParentelaRappresentato', $campi['gradoParentelaRappresentato'], $formEntity, $errors, $gradiRappresentato, ['vuota' => $nessuno, 'colonna' => 'col-md-6']) ?>
        </div>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneTrust) ?></div>
        <div class="card-body row g-3">
            <div class="col-12 small text-body-secondary"><?= Html::e($aiutoTrust) ?></div>
            <?= Campo::testo('codiceFiscaleTrust', $campi['codiceFiscaleTrust'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="11"']) ?>
            <?= Campo::autocompleta('beneficiarioFinale', $campi['beneficiarioFinale'], $formEntity, $errors, '/soggetto/cerca', $etichettaSoggetto($formEntity->beneficiarioFinale ?? null), ['colonna' => 'col-md-8']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
