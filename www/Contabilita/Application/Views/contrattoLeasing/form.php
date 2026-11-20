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
<p class="small text-body-secondary"><?= Html::e($aiutoLeasing) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body row g-3">
            <?= Campo::testo('descrizione', $campi['descrizione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-6', 'attributi' => 'maxlength="255"']) ?>
            <?= Campo::testo('concedente', $campi['concedente'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="100"']) ?>
            <?= Campo::testo('numeroContratto', $campi['numeroContratto'], $formEntity, $errors, ['colonna' => 'col-md-2', 'attributi' => 'maxlength="40"']) ?>
            <?= Campo::data('dataInizio', $campi['dataInizioLeasing'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-4']) ?>
            <?= Campo::data('dataFine', $campi['dataFineLeasing'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-4']) ?>
            <?= Campo::data('dataRiscatto', $campi['dataRiscatto'], $formEntity, $errors, ['colonna' => 'col-md-4', 'aiuto' => $aiutoRiscatto]) ?>
            <?= Campo::testo('costoConcedente', $campi['costoConcedente'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-4', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::testo('totaleCanoni', $campi['totaleCanoni'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'inputmode="decimal"', 'aiuto' => $aiutoCanoni]) ?>
            <?= Campo::testo('prezzoRiscatto', $campi['prezzoRiscatto'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::spunta('beneImmobile', $campi['beneImmobile'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
            <?= Campo::selezione('immobile', $campi['immobileAnagrafica'], $formEntity, $errors, $immobili, ['colonna' => 'col-md-8', 'vuota' => $nessunImmobile, 'aiuto' => $aiutoImmobile]) ?>
            <?= Campo::testo('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-12', 'attributi' => 'maxlength="255"']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php if (isset($formEntity->id) && ($formEntity instanceof \Contabilita\Application\Entities\ContrattoLeasing)): ?>
    <form method="post" action="/contratto-leasing/delete/contratto/<?= Html::e($formEntity->id) ?>" class="mt-3" data-conferma="<?= Html::e($confermaEliminaLeasing) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i><?= Html::e($azioni['elimina']) ?></button></form>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
