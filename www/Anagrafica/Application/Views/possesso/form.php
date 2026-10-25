<?php

use CommSuite\Application\HelperClasses\Campo;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

$immobileFisso = isset($possesso->immobile);
$soggettoFisso = isset($possesso->soggetto);
$pEntity = $formEntity;
$pErrors = $errors;
$prefissoPossesso = null;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary">
    <?php if ($immobileFisso): ?><?= Html::e($possesso->immobile->tipoCatasto->getLabel() . ' ' . $possesso->immobile->getIdentificativo() . ' - ' . $possesso->immobile->getUbicazione()) ?><?php endif; ?>
    <?php if ($immobileFisso && $soggettoFisso): ?> · <?php endif; ?>
    <?php if ($soggettoFisso): ?><?= Html::e($possesso->soggetto->nominativo) ?><?php endif; ?>
    <?php if (!$nuovo): ?> · <?= Html::e($aiutoRettifica) ?><?php endif; ?>
    <?php if ($variazione): ?> · <?= Html::e($aiutoVariazione) ?><?php endif; ?>
</p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3">
            <?php if ($soggettoFisso === false): ?>
                <?= Campo::autocompleta('soggetto', $campi['soggetto'], $formEntity, $errors, '/soggetto/cerca', isset($formEntity->soggetto) ? $formEntity->soggetto->nominativo . ' - ' . $formEntity->soggetto->codiceFiscale : null, ['richiesto' => true, 'colonna' => 'col-12', 'segnaposto' => $segnapostoSoggetto]) ?>
            <?php endif; ?>
            <?php if ($immobileFisso === false): ?>
                <?= Campo::autocompleta('immobile', $campi['immobile'], $formEntity, $errors, '/immobile/cerca', isset($formEntity->immobile) ? $formEntity->immobile->getIdentificativo() . ' - ' . $formEntity->immobile->getUbicazione() : null, ['richiesto' => true, 'colonna' => 'col-12', 'segnaposto' => $segnapostoImmobile, 'aiuto' => $aiutoImmobileNuovo]) ?>
            <?php endif; ?>
            <?php require __DIR__ . '/../commonParts/campiPossesso.php'; ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
