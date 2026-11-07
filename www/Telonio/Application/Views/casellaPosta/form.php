<?php

use Telonio\Application\Enumerations\TipoCasellaPosta;
use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$sezioneStudio = 'posta';
$urlAnnulla = '/casella-posta/index';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navStudio.php';
?>
<h1 class="h3 mb-2"><i class="bi <?= Html::e($tipo->getIcona()) ?> me-2"></i><?= Html::e($pageTitle) ?></h1>
<p class="small text-body-secondary mb-3"><?= Html::e($tipo === TipoCasellaPosta::pec ? $aiutoPec : $aiutoEmail) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::testo('indirizzo', $campi['indirizzoMittente'], $formEntity, $errors, ['richiesto' => true, 'tipo' => 'email', 'colonna' => 'col-md-6', 'attributi' => 'maxlength="255"']) ?>
            <?= Campo::testo('nomeMittente', $campi['nomeMittente'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'maxlength="255" placeholder="' . Html::e($studio->denominazione) . '"', 'aiuto' => $aiutoNomeMittente]) ?>
            <?= Campo::testo('host', $campi['hostSmtp'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-6', 'attributi' => 'maxlength="255" autocomplete="off"', 'aiuto' => $aiutoHost]) ?>
            <?= Campo::selezioneEnum('cifratura', $campi['cifraturaSmtp'], $formEntity, $errors, $cifrature, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            <?= Campo::testo('porta', $campi['portaSmtp'], $formEntity, $errors, ['richiesto' => true, 'tipo' => 'number', 'colonna' => 'col-md-3', 'attributi' => 'min="1" max="65535"', 'aiuto' => $aiutoPorta]) ?>
            <?= Campo::testo('utente', $campi['utenteSmtp'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'maxlength="255" autocomplete="off"', 'aiuto' => $aiutoUtente]) ?>
            <?= Campo::password('password', $campi['passwordSmtp'], $errors, ['colonna' => 'col-md-6', 'attributi' => 'maxlength="255" autocomplete="new-password"', 'aiuto' => $conPassword ? $aiutoPasswordSalvata : $aiutoPassword]) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
