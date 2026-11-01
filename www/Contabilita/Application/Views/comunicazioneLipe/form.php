<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlAnnulla = '/comunicazione-lipe/show/comunicazione/' . $comunicazione->id;
// dopo un invio con errori il dichiarante è quello inviato, non quello dell'entità
$dichiaranteScelto = isset($formEntity->dichiarante) ? $formEntity->dichiarante : null;
$dichiaranteCorrente = $dichiaranteScelto === null ? null : $dichiaranteScelto->nominativo . ' - ' . $dichiaranteScelto->codiceFiscale;
$intermediariScelte = [];
foreach ($intermediari as $intermediario) {
    $intermediariScelte[$intermediario->id] = $intermediario->getEtichetta();
}

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($comunicazione->soggetto->nominativo) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?= Campo::autocompleta('dichiarante', $campi['dichiarante'], $formEntity, $errors, '/soggetto/cerca', $dichiaranteCorrente, ['colonna' => 'col-md-6', 'aiuto' => $aiutoDichiarante]) ?>
            <?= Campo::selezioneEnum('codiceCarica', $campi['codiceCarica'], $formEntity, $errors, $codiciCarica, ['colonna' => 'col-md-6', 'vuota' => $nessuno]) ?>
            <?= Campo::selezione('intermediario', $campi['intermediario'], $formEntity, $errors, $intermediariScelte, ['colonna' => 'col-md-6', 'vuota' => $nessuno]) ?>
            <?= Campo::selezioneEnum('impegno', $campi['impegno'], $formEntity, $errors, $impegni, ['colonna' => 'col-md-3', 'vuota' => $nessuno]) ?>
            <?= Campo::data('dataImpegno', $campi['dataImpegno'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
