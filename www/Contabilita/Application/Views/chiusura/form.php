<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/chiusura/index/soggetto/' . $soggetto->id . '/anno/' . $voce->anno;
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary mb-1"><?= Html::e($soggetto->nominativo) ?> · <?= Html::e($campi['esercizio']) ?> <?= Html::e($voce->anno) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiutoForm) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::testo('descrizione', $campi['descrizione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-8', 'attributi' => 'maxlength="255"']) ?>
            <?= Campo::selezione('conto', $campi['conto'], $formEntity, $errors, $scelteConti, ['richiesto' => true, 'colonna' => 'col-md-4', 'vuota' => $selezionare]) ?>
            <?= Campo::testo('importo', $campi['importo'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"', 'aiuto' => $aiutoImporto]) ?>
            <?= Campo::data('competenzaDal', $campi['competenzaDal'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            <?= Campo::data('competenzaAl', $campi['competenzaAl'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            <?= Campo::spunta('registratoNellEsercizio', $campi['registratoNellEsercizio'], $formEntity, $errors, ['colonna' => 'col-md-12']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php if (isset($voce->id)): ?>
    <form method="post" action="/chiusura/delete/voce/<?= Html::e($voce->id) ?>" class="mt-3" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i><?= Html::e($azioni['elimina']) ?></button></form>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
