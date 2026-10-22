<?php

use CommSuite\Application\Forms\PasswordForm;
use CommSuite\Application\HelperClasses\Campo;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

$sezioneProfilo = 'password';
$urlAnnulla = '/profilo/update';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navProfilo.php';
?>
<h1 class="h3 mb-3"><i class="bi bi-key me-2"></i><?= Html::e($pageTitle) ?></h1>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::password('passwordAttuale', $campi['passwordAttuale'], $errors, ['richiesto' => true, 'colonna' => 'col-md-6', 'attributi' => 'autocomplete="current-password" autofocus']) ?>
            <div class="col-md-6 d-none d-md-block"></div>
            <?= Campo::password('password', $campi['nuovaPassword'], $errors, ['richiesto' => true, 'colonna' => 'col-md-6', 'attributi' => 'minlength="' . PasswordForm::LUNGHEZZA_MINIMA . '" autocomplete="new-password"', 'aiuto' => $aiutoPassword]) ?>
            <?= Campo::password('confermaPassword', $campi['confermaPassword'], $errors, ['richiesto' => true, 'colonna' => 'col-md-6', 'attributi' => 'minlength="' . PasswordForm::LUNGHEZZA_MINIMA . '" autocomplete="new-password"']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
