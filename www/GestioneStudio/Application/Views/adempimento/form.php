<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::testo('codice', $campi['codice'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'maxlength="30"']) ?>
            <?= Campo::testo('denominazione', $campi['denominazione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-9', 'attributi' => 'maxlength="150"']) ?>
            <?= Campo::selezioneEnum('categoria', $campi['categoria'], $formEntity, $errors, $categorie, ['richiesto' => true, 'colonna' => 'col-md-4']) ?>
            <?= Campo::selezioneEnum('ambito', $campi['ambito'], $formEntity, $errors, $ambiti, ['richiesto' => true, 'colonna' => 'col-md-8']) ?>
            <?= Campo::testo('fonte', $campi['fonte'], $formEntity, $errors, ['colonna' => 'col-12', 'attributi' => 'maxlength="255"']) ?>
            <?= Campo::spunta('slittamentoFestivi', $campi['slittamentoFestivi'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
            <?= Campo::spunta('prorogaAgosto', $campi['prorogaAgosto'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
            <?= Campo::spunta('attivo', $campi['attivo'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
            <?= Campo::areaTesto('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-12']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
