<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$soggettoCorrente = isset($formEntity->soggetto) && is_object($formEntity->soggetto) ? $formEntity->soggetto->nominativo . ' - ' . $formEntity->soggetto->codiceFiscale : null;
require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($immobile->tipoCatasto->getLabel() . ' ' . $immobile->getIdentificativo() . ' - ' . $immobile->getUbicazione()) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiutoIntervento) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneIntervento) ?></div>
        <div class="card-body row g-3">
            <?= Campo::autocompleta('soggetto', $campi['beneficiario'], $formEntity, $errors, '/soggetto/cerca', $soggettoCorrente, ['richiesto' => true, 'colonna' => 'col-md-6', 'segnaposto' => $segnapostoBeneficiario]) ?>
            <?= Campo::selezioneEnum('tipo', $campi['tipoDetrazione'], $formEntity, $errors, $tipi, ['richiesto' => true, 'colonna' => 'col-md-6']) ?>
            <?= Campo::testo('anno', $campi['annoSpesa'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'inputmode="numeric" maxlength="4"']) ?>
            <?= Campo::testo('tipologia', $campi['tipologiaIntervento'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'inputmode="numeric" maxlength="2"', 'aiuto' => $aiutoTipologia]) ?>
            <?= Campo::testo('codicePercentuale', $campi['codicePercentuale'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'inputmode="numeric" maxlength="1"', 'aiuto' => $aiutoCodicePercentuale]) ?>
            <?= Campo::testo('spesa', $campi['spesaDetraibile'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'inputmode="decimal"', 'aiuto' => $aiutoSpesa]) ?>
            <?= Campo::testo('percentualeDetrazione', $campi['detrazioneIntervento'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'inputmode="decimal"', 'aiuto' => $aiutoProposta]) ?>
            <?= Campo::testo('rateazione', $campi['rateazione'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'inputmode="numeric" maxlength="2"', 'aiuto' => $aiutoProposta]) ?>
            <?= Campo::testo('descrizione', $campi['descrizioneIntervento'], $formEntity, $errors, ['colonna' => 'col-md-12', 'attributi' => 'maxlength="255"']) ?>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneCaselle) ?></div>
        <div class="card-body row g-3">
            <?= Campo::testo('codiceFiscaleCondominio', $campi['codiceFiscaleCondominio'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="16"']) ?>
            <?= Campo::testo('casiParticolari', $campi['casiParticolari'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'inputmode="numeric" maxlength="1"', 'aiuto' => $aiutoCasiParticolari]) ?>
            <?= Campo::spunta('condominio', $campi['interventoCondominiale'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
            <?= Campo::spunta('acquistoEreditaDonazione', $campi['acquistoEreditaDonazione'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
            <?= Campo::spunta('maggiorazioneSisma', $campi['maggiorazioneSisma'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
            <?= Campo::spunta('opzione2023', $campi['opzione2023'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
        </div>
    </div>
    <?php if (count($possessori) > 0): ?>
        <p class="small text-body-secondary mt-2"><?= Html::e($possessoriImmobile) ?>: <?= Html::e(implode(', ', array_map(fn($soggetto) => $soggetto->nominativo, $possessori))) ?></p>
    <?php endif; ?>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
