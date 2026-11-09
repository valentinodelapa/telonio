<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($immobile->tipoCatasto->getLabel() . ' ' . $immobile->getIdentificativo() . ' - ' . $immobile->getUbicazione()) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiutoContratto) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneContratto) ?></div>
        <div class="card-body row g-3">
            <?= Campo::selezioneEnum('tipo', $campi['tipoContratto'], $formEntity, $errors, $tipi, ['richiesto' => true, 'colonna' => 'col-md-6']) ?>
            <?= Campo::data('dataInizio', $campi['dataInizioContratto'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            <?= Campo::data('dataFine', $campi['dataFineContratto'], $formEntity, $errors, ['colonna' => 'col-md-3', 'aiuto' => $aiutoDataFine]) ?>
            <?= Campo::testo('canoneAnnuo', $campi['canoneAnnuo'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"', 'aiuto' => $aiutoCanone]) ?>
            <?= Campo::testo('conduttore', $campi['conduttore'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'maxlength="255"']) ?>
            <?= Campo::testo('codiceFiscaleConduttore', $campi['codiceFiscaleConduttore'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="16"']) ?>
            <?php if ($terreno): ?>
                <?= Campo::spunta('giovaneAgricoltore', $campi['giovaneAgricoltore'], $formEntity, $errors, ['colonna' => 'col-md-12']) ?>
            <?php else: ?>
                <?= Campo::spunta('veneziaCentro', $campi['veneziaCentro'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!$terreno): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneCedolare) ?></div>
            <div class="card-body row g-3">
                <?= Campo::spunta('cedolareSecca', $campi['cedolareSecca'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
                <?= Campo::data('cedolareRevocataDal', $campi['cedolareRevocataDal'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
                <?= Campo::spunta('cedolareRidotta', $campi['cedolareRidotta'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
                <?= Campo::spunta('emergenzaCalamita', $campi['emergenzaCalamita'], $formEntity, $errors, ['colonna' => 'col-md-12']) ?>
            </div>
        </div>
    <?php endif; ?>
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneRegistrazione) ?></div>
        <div class="card-body row g-3">
            <?= Campo::testo('codiceIdentificativo', $campi['codiceIdentificativo'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="17"', 'aiuto' => $aiutoCodiceIdentificativo]) ?>
            <?= Campo::data('dataRegistrazione', $campi['dataRegistrazione'], $formEntity, $errors, ['colonna' => 'col-md-2']) ?>
            <?= Campo::testo('serieRegistrazione', $campi['serieRegistrazione'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="2"']) ?>
            <?= Campo::testo('numeroRegistrazione', $campi['numeroRegistrazione'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="20"']) ?>
            <?= Campo::testo('codiceUfficio', $campi['codiceUfficio'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="3"']) ?>
            <?php if (!$terreno): ?>
                <?= Campo::testo('cin', $campi['cin'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="18"', 'aiuto' => $aiutoCin]) ?>
            <?php endif; ?>
            <?= Campo::testo('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-md-8', 'attributi' => 'maxlength="255"']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
