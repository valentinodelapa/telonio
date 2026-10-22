<?php

use CommSuite\Application\HelperClasses\Campo;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;
use Deleghe\Application\Enumerations\SezioneF24;

$urlAnnulla = '/delega/show/delega/' . $delega->id;
$urlCodici = '/codice-tributo/cerca/sezione/' . $sezione->getSezioneCodici()->value;
$maiuscolo = 'style="text-transform: uppercase"';

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><i class="bi bi-clock-history me-2"></i><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary">F24 <?= Html::data($delega->scadenza) ?> · <?= Html::e($delega->soggetto->nominativo) ?></p>

<?php if (isset($erroreCalcolo)): ?>
    <div class="alert alert-danger"><strong><?= Html::e($erroreCalcoloTitolo) ?>:</strong> <?= Html::e($erroreCalcolo) ?></div>
<?php endif; ?>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneVersamento) ?></div>
        <div class="card-body row g-3">
            <?= Campo::autocompleta('codiceTributo', $campi['codiceTributo'], $formEntity, $errors, $urlCodici, $etichette['codiceTributo'], ['richiesto' => true, 'colonna' => 'col-md-8', 'segnaposto' => $segnapostoCodice]) ?>
            <?= Campo::testo('annoRiferimento', $campi['annoRiferimento'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="4" inputmode="numeric"']) ?>
            <?= Campo::testo('rateazione', $campi['rateazione'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="4" ' . $maiuscolo]) ?>
            <?php if ($sezione !== SezioneF24::erario): ?>
                <?= Campo::testo('codiceEnte', $sezione === SezioneF24::regioni ? $campi['codiceEnteRegioni'] : $campi['codiceEnteTributiLocali'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'maxlength="4" ' . $maiuscolo]) ?>
            <?php endif; ?>
            <?php if ($sezione === SezioneF24::tributiLocali): ?>
                <?= Campo::spunta('acconto', $campi['acconto'], $formEntity, $errors, ['colonna' => 'col-6 col-md-2']) ?>
                <?= Campo::spunta('saldo', $campi['saldo'], $formEntity, $errors, ['colonna' => 'col-6 col-md-2']) ?>
                <?= Campo::testo('numeroImmobili', $campi['numeroImmobili'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="3" inputmode="numeric"']) ?>
            <?php endif; ?>
            <div class="w-100 m-0"></div>
            <?= Campo::testo('importoTributo', $campi['importoTributo'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-4', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::data('scadenzaOriginale', $campi['scadenzaOriginale'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-4']) ?>
            <div class="col-md-4 small text-body-secondary align-self-center"><?= Html::e($campi['scadenza']) ?>: <strong><?= Html::data($delega->scadenza) ?></strong></div>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneCalcolo) ?></div>
        <div class="card-body row g-3">
            <p class="small text-body-secondary mb-0"><?= Html::e($aiutoNormativa) ?></p>
            <?= Campo::selezioneEnum('circostanza', $campi['circostanza'], $formEntity, $errors, $circostanze, ['richiesto' => true, 'colonna' => 'col-12', 'aiuto' => $aiutoCircostanza]) ?>
            <?= Campo::data('termineDichiarazione', $campi['termineDichiarazione'], $formEntity, $errors, ['colonna' => 'col-md-6', 'aiuto' => $aiutoTermine]) ?>
            <?= Campo::data('termineDichiarazioneSuccessiva', $campi['termineDichiarazioneSuccessiva'], $formEntity, $errors, ['colonna' => 'col-md-6', 'aiuto' => $aiutoTermineSuccessivo]) ?>
            <?= Campo::autocompleta('codiceSanzione', $campi['codiceSanzione'], $formEntity, $errors, $urlCodici, $etichette['codiceSanzione'], ['colonna' => 'col-md-6', 'segnaposto' => $segnapostoCodice]) ?>
            <?= Campo::autocompleta('codiceInteressi', $campi['codiceInteressi'], $formEntity, $errors, $urlCodici, $etichette['codiceInteressi'], ['colonna' => 'col-md-6', 'segnaposto' => $segnapostoCodice]) ?>
            <p class="small text-body-secondary mb-0"><?= Html::e($aiutoCodici) ?><br><?= Html::e($titoloRegole) ?>: <span class="font-monospace"><?= Html::e($codiciConRegola) ?></span></p>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2 mt-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-calculator me-1"></i><?= Html::e($azioni['calcola']) ?></button>
        <a href="<?= Html::e($urlAnnulla) ?>" class="btn btn-outline-secondary"><?= Html::e($azioni['annulla']) ?></a>
        <span class="ms-auto small text-body-secondary"><?= Html::e($campiObbligatori) ?></span>
    </div>
</form>
<?php require Layout::percorso('footer'); ?>
