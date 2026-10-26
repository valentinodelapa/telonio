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
<h1 class="h3 mb-1"><i class="bi bi-key me-2"></i><?= Html::e($pageTitle) ?></h1>
<p class="small text-body-secondary mb-3"><?= Html::e($utenteModificato->getNomeCompleto()) ?> (<span class="font-monospace"><?= Html::e($utenteModificato->username) ?></span>)</p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::password('password', $campi['nuovaPassword'], $errors, ['richiesto' => true, 'colonna' => 'col-md-6', 'attributi' => 'minlength="' . PasswordForm::LUNGHEZZA_MINIMA . '" autocomplete="new-password" autofocus', 'aiuto' => $aiutoPassword]) ?>
            <?= Campo::password('confermaPassword', $campi['confermaPassword'], $errors, ['richiesto' => true, 'colonna' => 'col-md-6', 'attributi' => 'minlength="' . PasswordForm::LUNGHEZZA_MINIMA . '" autocomplete="new-password"']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
