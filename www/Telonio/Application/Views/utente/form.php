<?php

use Telonio\Application\Forms\PasswordForm;
use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$sezioneStudio = 'utenti';
$urlAnnulla = '/utente/index';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navStudio.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0 me-auto"><i class="bi <?= $nuovo ? 'bi-person-plus' : 'bi-person-gear' ?> me-2"></i><?= Html::e($pageTitle) ?></h1>
    <?php if ($nuovo === false): ?>
        <a href="/utente/password/utente/<?= Html::e($utenteModificato->id) ?>" class="btn btn-outline-secondary"><i class="bi bi-key me-1"></i><?= Html::e($azioni['reimpostaPassword']) ?></a>
    <?php endif; ?>
</div>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::testo('cognome', $campi['cognome'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-6', 'attributi' => 'maxlength="100"']) ?>
            <?= Campo::testo('nome', $campi['nome'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-6', 'attributi' => 'maxlength="100"']) ?>
            <?= Campo::testo('username', $campi['username'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-4', 'attributi' => 'maxlength="50" autocomplete="off"', 'aiuto' => $aiutoUsername]) ?>
            <?= Campo::testo('email', $campi['email'], $formEntity, $errors, ['richiesto' => true, 'tipo' => 'email', 'colonna' => 'col-md-8', 'attributi' => 'maxlength="255"']) ?>
            <?= Campo::selezioneEnum('ruolo', $campi['ruolo'], $formEntity, $errors, $ruoli, ['richiesto' => true, 'colonna' => 'col-md-6', 'aiuto' => $aiutoRuolo]) ?>
            <?= Campo::spunta('attivo', $campi['attivo'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
            <?php if ($nuovo): ?>
                <div class="col-12"><hr class="my-1"></div>
                <?= Campo::password('password', $campi['passwordIniziale'], $passwordErrors, ['richiesto' => true, 'colonna' => 'col-md-6', 'attributi' => 'minlength="' . PasswordForm::LUNGHEZZA_MINIMA . '" autocomplete="new-password"', 'aiuto' => $aiutoPassword]) ?>
                <?= Campo::password('confermaPassword', $campi['confermaPassword'], $passwordErrors, ['richiesto' => true, 'colonna' => 'col-md-6', 'attributi' => 'minlength="' . PasswordForm::LUNGHEZZA_MINIMA . '" autocomplete="new-password"']) ?>
            <?php endif; ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
