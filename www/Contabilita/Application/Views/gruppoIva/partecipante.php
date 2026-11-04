<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/gruppo-iva/index/soggetto/' . $soggetto->id;
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$societa = isset($formEntity->soggetto) ? $formEntity->soggetto : null;
$detentore = isset($formEntity->detentore) ? $formEntity->detentore : null;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary mb-1"><?= Html::e($gruppo->getEtichetta()) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::autocompleta('soggetto', $campi['controllata'], $formEntity, $errors, '/soggetto/cerca', $societa === null ? null : $societa->nominativo . ' - ' . ($societa->partitaIva ?? $societa->codiceFiscale), ['colonna' => 'col-md-6', 'richiesto' => true]) ?>
            <?= Campo::testo('annoIngresso', $campi['annoIngresso'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'tipo' => 'number']) ?>
            <?= Campo::testo('annoUscita', $campi['annoUscita'], $formEntity, $errors, ['colonna' => 'col-md-3', 'tipo' => 'number', 'aiuto' => $aiutoUscita]) ?>
            <?= Campo::data('dataCessazione', $campi['dataCessazione'], $formEntity, $errors, ['colonna' => 'col-md-4', 'aiuto' => $aiutoCessazione]) ?>
            <?= Campo::data('dataPossesso', $campi['dataPossesso'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
            <?= Campo::testo('percentualePossesso', $campi['percentualePossesso'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::autocompleta('detentore', $campi['detentore'], $formEntity, $errors, '/soggetto/cerca', $detentore === null ? null : $detentore->nominativo . ' - ' . $detentore->codiceFiscale, ['colonna' => 'col-md-6', 'aiuto' => $aiutoDetentore]) ?>
            <?= Campo::spunta('detentoreEstero', $campi['detentoreEstero'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
            <?= Campo::spunta('soloCatena', $campi['soloCatena'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php if (isset($partecipante->id)): ?>
    <form method="post" action="/gruppo-iva/elimina-partecipante/partecipante/<?= Html::e($partecipante->id) ?>" class="mt-3" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i><?= Html::e($azioni['elimina']) ?></button></form>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
