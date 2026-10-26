<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;
use Imu\Application\Entities\RigoDichiarazioneImu;

$urlAnnulla = '/dichiarazione-imu/show/dichiarazione/' . $dichiarazione->id;
$enc = $dichiarazione->isEnc();

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneImmobile) ?></div>
        <div class="card-body row g-3">
            <?= Campo::selezione('immobile', $campi['immobile'], $formEntity, $errors, $immobili, ['richiesto' => true, 'colonna' => 'col-md-8', 'vuota' => $selezionare]) ?>
            <?= Campo::selezione('quadro', $campi['quadro'], $formEntity, $errors, $enc ? ['A' => $quadroAEnc, 'B' => $quadroBEnc] : ['A' => $quadroA], ['richiesto' => true, 'colonna' => 'col-md-4']) ?>
            <?= Campo::selezioneEnum('caratteristica', $campi['caratteristica'], $formEntity, $errors, $caratteristiche, ['richiesto' => true, 'colonna' => 'col-md-6']) ?>
            <?= Campo::testo('valore', $campi['valore'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"', 'aiuto' => $aiutoValore]) ?>
            <?= Campo::testo('percentualePossesso', $campi['percentualePossesso'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?php if ($enc === false): ?>
                <?= Campo::testo('detrazione', $campi['detrazione'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
                <?= Campo::selezione('equiparazione', $campi['equiparazione'], $formEntity, $errors, RigoDichiarazioneImu::EQUIPARAZIONI, ['colonna' => 'col-md-3', 'vuota' => $nessuna]) ?>
            <?php endif; ?>
            <?= Campo::selezione('riduzione', $campi['riduzione'], $formEntity, $errors, $riduzioni, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            <?= Campo::selezione('esenzione', $campi['esenzione'], $formEntity, $errors, RigoDichiarazioneImu::ESENZIONI, ['richiesto' => true, 'colonna' => 'col-md-3', 'aiuto' => $enc ? $aiutoEsenzioneEnc : null]) ?>
        </div>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneVicenda) ?></div>
        <div class="card-body row g-3">
            <?= Campo::spunta('acquisto', $campi['acquisto'], $formEntity, $errors, ['colonna' => 'col-md-2']) ?>
            <?= Campo::spunta('cessione', $campi['cessione'], $formEntity, $errors, ['colonna' => 'col-md-2']) ?>
            <?= Campo::spunta('altro', $campi['altro'], $formEntity, $errors, ['colonna' => 'col-md-2']) ?>
            <?= Campo::testo('altroDescrizione', $campi['altroDescrizione'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'maxlength="100"']) ?>
            <?= Campo::data('dataVariazione', $campi['dataVariazione'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <?= Campo::selezione('inizioTermine', $campi['inizioTermine'], $formEntity, $errors, ['I' => $inizio, 'T' => $termine], ['colonna' => 'col-md-3', 'vuota' => $nessuna]) ?>
            <?= Campo::testo('agenziaEntrate', $campi['agenziaEntrate'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="24"']) ?>
            <?= Campo::testo('estremiTitolo', $campi['estremiTitolo'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="24"']) ?>
        </div>
    </div>
    <?php if ($enc === false): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneNonUtilizzabile) ?></div>
            <div class="card-body row g-3">
                <?= Campo::selezione('nonUtilizzabileTipo', $campi['nonUtilizzabileTipo'], $formEntity, $errors, ['1' => $violazioneDomicilio, '2' => $occupazioneAbusiva], ['colonna' => 'col-md-4', 'vuota' => $nessuna]) ?>
                <?= Campo::testo('nonUtilizzabileAutorita', $campi['nonUtilizzabileAutorita'], $formEntity, $errors, ['colonna' => 'col-md-5', 'attributi' => 'maxlength="100"']) ?>
                <?= Campo::data('nonUtilizzabileData', $campi['nonUtilizzabileData'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            </div>
        </div>
    <?php endif; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3"><?= Campo::testo('motivo', $campi['motivo'], $formEntity, $errors, ['colonna' => 'col-12', 'attributi' => 'maxlength="255"']) ?></div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
