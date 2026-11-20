<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/contribuente-dichiarativi/show/soggetto/' . $soggetto->id . '/anno/' . $anno;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($soggetto->nominativo) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <p class="small text-body-secondary mb-0"><?= Html::e($aiutoPerditePregresse) ?></p>
            <?= Campo::testo('anno', $campi['annoPerdite'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'inputmode="numeric"']) ?>
            <?= Campo::selezioneEnum('tipo', $campi['tipoPerdita'], $formEntity, $errors, $tipiPerdita, ['richiesto' => true, 'colonna' => 'col-md-9']) ?>
            <?= Campo::testo('limitata', $campi['perditeLimitate'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'inputmode="numeric"', 'aiuto' => $aiutoPerditeLimitate]) ?>
            <?= Campo::testo('piena', $campi['perditePiene'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'inputmode="numeric"', 'aiuto' => $aiutoPerditePiene]) ?>
            <?= Campo::testo('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-12', 'attributi' => 'maxlength="255"']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
