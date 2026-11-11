<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/contribuente-dichiarativi/show/soggetto/' . $soggetto->id . '/anno/' . $anno;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($soggetto->nominativo) ?> · <?= Html::e($campi['annoImposta']) ?> <?= Html::e($anno) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiutoCessione) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body row g-3">
            <?= Campo::selezione('tipo', $campi['tipoCessione'], $formEntity, $errors, $tipiCessione, ['richiesto' => true, 'colonna' => 'col-md-6']) ?>
            <?= Campo::data('dataCessione', $campi['dataCessione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            <?= Campo::testo('descrizione', $campi['descrizioneCessione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-12', 'attributi' => 'maxlength="150"']) ?>
            <?= Campo::testo('corrispettivo', $campi['corrispettivo'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::testo('costo', $campi['costoAcquisto'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"', 'aiuto' => $aiutoCosto]) ?>
            <?= Campo::spunta('costoRideterminato', $campi['costoRideterminato'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
            <?= Campo::spunta('paesePrivilegiato', $campi['paesePrivilegiato'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
            <?= Campo::spunta('anteriore2025', $campi['anteriore2025'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
            <?= Campo::testo('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-md-12', 'attributi' => 'maxlength="255"']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
