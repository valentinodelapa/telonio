<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/cespite/index/soggetto/' . $soggetto->id;
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary mb-1"><?= Html::e($soggetto->nominativo) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiutoForm) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <?php if (($cespite->registrazioneAcquisto ?? null) !== null): ?><input type="hidden" name="registrazioneAcquisto" value="<?= Html::e($cespite->registrazioneAcquisto->id) ?>"><?php endif; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body row g-3">
            <?= Campo::testo('codice', $campi['codice'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'maxlength="20"']) ?>
            <?= Campo::testo('descrizione', $campi['descrizione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-10', 'attributi' => 'maxlength="255"']) ?>
            <?= Campo::selezione('contoCespite', $campi['contoCespite'], $formEntity, $errors, $scelteConti['immobilizzazioni'], ['richiesto' => true, 'colonna' => 'col-md-4', 'vuota' => $selezionare]) ?>
            <?= Campo::selezione('contoFondo', $campi['contoFondo'], $formEntity, $errors, $scelteConti['fondi'], ['richiesto' => true, 'colonna' => 'col-md-4', 'vuota' => $selezionare]) ?>
            <?= Campo::selezione('contoAmmortamento', $campi['contoAmmortamento'], $formEntity, $errors, $scelteConti['ammortamenti'], ['richiesto' => true, 'colonna' => 'col-md-4', 'vuota' => $selezionare]) ?>
            <?= Campo::data('dataAcquisto', $campi['dataAcquisto'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            <?= Campo::data('dataEntrataFunzione', $campi['dataEntrataFunzione'], $formEntity, $errors, ['colonna' => 'col-md-3', 'aiuto' => $aiutoEntrataFunzione]) ?>
            <?= Campo::testo('costo', $campi['costo'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::testo('percentuale', $campi['percentualeAmmortamento'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"', 'aiuto' => $aiutoPercentuale]) ?>
            <?= Campo::spunta('riduzionePrimoEsercizio', $campi['riduzionePrimoEsercizio'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
            <?= Campo::spunta('deduzioneIntegrale', $campi['deduzioneIntegrale'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
        </div>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white"><span class="fw-semibold"><?= Html::e($titoloFiscale) ?></span> <span class="small text-body-secondary ms-2"><?= Html::e($aiutoFiscale) ?></span></div>
        <div class="card-body row g-3">
            <?= Campo::testo('percentualeFiscale', $campi['percentualeFiscale'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'inputmode="decimal"', 'aiuto' => $aiutoPercentualeFiscale]) ?>
            <?= Campo::testo('percentualeDeducibile', $campi['percentualeDeducibile'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-4', 'attributi' => 'inputmode="decimal"', 'aiuto' => $aiutoDeducibile]) ?>
            <?= Campo::testo('limiteCostoFiscale', $campi['limiteCostoFiscale'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'inputmode="decimal"', 'aiuto' => $aiutoLimite]) ?>
        </div>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white"><span class="fw-semibold"><?= Html::e($titoloPregressi) ?></span> <span class="small text-body-secondary ms-2"><?= Html::e($aiutoPregressi) ?></span></div>
        <div class="card-body row g-3">
            <?= Campo::testo('fondoIniziale', $campi['fondoIniziale'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::testo('esercizioFondoIniziale', $campi['esercizioFondoIniziale'], $formEntity, $errors, ['colonna' => 'col-md-3', 'tipo' => 'number']) ?>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="card-header bg-white"><span class="fw-semibold"><?= Html::e($titoloDismissione) ?></span> <span class="small text-body-secondary ms-2"><?= Html::e($aiutoDismissione) ?></span></div>
        <div class="card-body row g-3">
            <?= Campo::data('dataDismissione', $campi['dataDismissione'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <?= Campo::testo('prezzoDismissione', $campi['prezzoDismissione'], $formEntity, $errors, ['colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::testo('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'maxlength="255"']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php if (isset($cespite->id)): ?>
    <form method="post" action="/cespite/delete/cespite/<?= Html::e($cespite->id) ?>" class="mt-3" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i><?= Html::e($azioni['elimina']) ?></button></form>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
