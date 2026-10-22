<?php

use CommSuite\Application\HelperClasses\Campo;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

$urlAnnulla = '/prospetto-aliquote/index/anno/' . $prospetto->anno;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>
<?php if ($pubblicato !== null): ?><div class="alert alert-info small"><?= Html::e($pubblicatoPresente) ?> <a href="/prospetto-aliquote/show/prospetto/<?= Html::e($pubblicato->id) ?>"><?= Html::e($azioni['vediProspetto']) ?></a></div><?php endif; ?>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneAliquote) ?></div>
        <div class="card-body row g-3">
            <?php foreach ($fattispecie as $voce): ?>
                <?= Campo::testo($voce->getProprietaProspetto(), $voce->getCodiceTributo() . ' ' . $voce->getLabel() . ' (%)', $formEntity, $errors,
                    ['colonna' => 'col-md-4', 'attributi' => 'inputmode="decimal" placeholder="' . Html::e($baseLegge . ' ' . number_format($voce->getAliquotaBase(), 2, ',', '')) . '"']) ?>
            <?php endforeach; ?>
            <?= Campo::spunta('assimilazioneAnziani', $campi['assimilazioneAnziani'], $formEntity, $errors, ['colonna' => 'col-12']) ?>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneAltro) ?></div>
        <div class="card-body row g-3">
            <?= Campo::testo('detrazioneAbitazionePrincipale', $campi['detrazioneAbitazionePrincipale'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-4', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::testo('importoMinimo', $campi['importoMinimo'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-4', 'attributi' => 'inputmode="decimal"']) ?>
            <div class="col-md-4"></div>
            <?= Campo::testo('numeroDelibera', $campi['numeroDelibera'], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'maxlength="20"']) ?>
            <?= Campo::data('dataDelibera', $campi['dataDelibera'], $formEntity, $errors, ['colonna' => 'col-md-4']) ?>
            <?= Campo::areaTesto('esenzioni', $campi['esenzioni'], $formEntity, $errors, ['colonna' => 'col-12', 'righe' => 3]) ?>
            <?= Campo::areaTesto('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-12', 'righe' => 2]) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
