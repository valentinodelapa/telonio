<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$sezioneStudio = 'dati';
$urlAnnulla = '/home/index';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navStudio.php';
?>
<h1 class="h3 mb-2"><i class="bi bi-building-gear me-2"></i><?= Html::e($pageTitle) ?></h1>
<p class="small text-body-secondary mb-3"><?= Html::e($aiuto) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::testo('denominazione', $campi['denominazione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-12', 'attributi' => 'maxlength="255"']) ?>
            <?= Campo::testo('codiceFiscale', $campi['codiceFiscale'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-6', 'attributi' => 'maxlength="16" style="text-transform: uppercase"']) ?>
            <?= Campo::testo('partitaIva', $campi['partitaIva'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'maxlength="13"']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
