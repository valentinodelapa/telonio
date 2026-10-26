<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;
use Deleghe\Application\Entities\CausaleContributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\TabellaF24;

$urlAnnulla = '/delega/show/delega/' . $delega->id;
$valore = fn(string $campo): ?string => (isset($formEntity->$campo) && ($formEntity->$campo !== '')) ? (string) $formEntity->$campo : null;
$maiuscolo = 'style="text-transform: uppercase"';

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><i class="bi bi-shield-exclamation me-2"></i><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary">F24 <?= Html::data($delega->scadenza) ?> · <?= Html::e($delega->soggetto->nominativo) ?></p>

<?php if (isset($erroreCalcolo)): ?>
    <div class="alert alert-danger"><?= Html::e($erroreCalcolo) ?></div>
<?php endif; ?>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneContributi) ?></div>
        <div class="card-body row g-3">
            <p class="small text-body-secondary mb-0"><?= Html::e($aiutoNormativa) ?></p>
            <?= Campo::testo('riferimento', $campi['riferimentoContributi'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-6', 'attributi' => 'maxlength="100"', 'aiuto' => $aiutoRiferimento]) ?>
            <?= Campo::testo('importoContributi', $campi['importoContributi'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::data('scadenzaOriginale', $campi['scadenzaOriginale'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            <?= Campo::selezioneEnum('regime', $campi['regime'], $formEntity, $errors, $regimi, ['richiesto' => true, 'colonna' => 'col-md-8']) ?>
            <?= Campo::spunta('spontaneo', $campi['spontaneo'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
        </div>
    </div>
    <?php if ($sezione === SezioneF24::inps): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneVersamento) ?></div>
            <div class="card-body row g-3">
                <p class="small text-body-secondary mb-0"><?= Html::e($aiutoVersamento) ?></p>
                <?= Campo::autocompleta('causale', $campi['causale'], $formEntity, $errors, '/tabelle-f24/causali/ente/' . CausaleContributo::ENTE_INPS, $valore('causale'), ['colonna' => 'col-md-4', 'segnaposto' => $segnapostoCodice]) ?>
                <?= Campo::autocompleta('codiceSede', $campi['codiceSede'], $formEntity, $errors, '/tabelle-f24/voci/tabella/' . TabellaF24::sediInps->value, $valore('codiceSede'), ['colonna' => 'col-md-4', 'segnaposto' => $segnapostoCodice]) ?>
                <?= Campo::testo('posizione', $campi['posizioneInps'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="17" ' . $maiuscolo]) ?>
                <?= Campo::testo('periodoDa', $campi['periodoDa'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="7" placeholder="MM/AAAA"']) ?>
                <?= Campo::testo('periodoA', $campi['periodoA'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'maxlength="7" placeholder="MM/AAAA"']) ?>
            </div>
        </div>
    <?php endif; ?>
    <div class="d-flex align-items-center gap-2 mt-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-calculator me-1"></i><?= Html::e($azioni['calcolaSanzioni']) ?></button>
        <a href="<?= Html::e($urlAnnulla) ?>" class="btn btn-outline-secondary"><?= Html::e($azioni['annulla']) ?></a>
        <span class="ms-auto small text-body-secondary"><?= Html::e($campiObbligatori) ?></span>
    </div>
</form>
<?php require Layout::percorso('footer'); ?>
