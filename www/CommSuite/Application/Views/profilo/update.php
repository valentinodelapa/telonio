<?php

use CommSuite\Application\HelperClasses\Campo;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

$sezioneProfilo = 'dati';
$urlAnnulla = '/home/index';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navProfilo.php';
?>
<h1 class="h3 mb-3"><i class="bi bi-person-gear me-2"></i><?= Html::e($pageTitle) ?></h1>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label text-body-secondary small mb-1"><?= Html::e($campi['username']) ?></label>
                <div class="font-monospace fw-semibold"><?= Html::e($utente->username) ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label text-body-secondary small mb-1"><?= Html::e($campi['studio']) ?></label>
                <div><?= Html::e($studio->denominazione) ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label text-body-secondary small mb-1"><?= Html::e($campi['ruolo']) ?></label>
                <div><?= Html::e($utente->ruolo->getLabel()) ?></div>
            </div>
            <div class="col-12"><hr class="my-1"></div>
            <?= Campo::testo('cognome', $campi['cognome'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-6', 'attributi' => 'maxlength="100"']) ?>
            <?= Campo::testo('nome', $campi['nome'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-6', 'attributi' => 'maxlength="100"']) ?>
            <?= Campo::testo('email', $campi['email'], $formEntity, $errors, ['richiesto' => true, 'tipo' => 'email', 'colonna' => 'col-md-8', 'attributi' => 'maxlength="255"']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
