<?php

use CommSuite\Application\HelperClasses\Campo;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

$urlAnnulla = '/contribuente-imu/show/soggetto/' . $soggetto->id . '/anno/' . $anno;
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($immobile->tipoCatasto->getLabel()) ?> <?= Html::e($immobile->getIdentificativo()) ?> · <?= Html::e($immobile->getUbicazione()) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?><?= $terreno ? ' ' . Html::e($aiutoTerreno) : '' ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneValidita) ?></div>
        <div class="card-body row g-3">
            <?= Campo::data('validoDal', $campi['validoDal'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            <?= Campo::data('validoAl', $campi['validoAl'], $formEntity, $errors, ['colonna' => 'col-md-3']) ?>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneCaratteristiche) ?></div>
        <div class="card-body row g-3">
            <?php if ($terreno === false): ?>
                <?= Campo::spunta('storicoArtistico', $campi['storicoArtistico'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
                <?= Campo::spunta('inagibile', $campi['inagibile'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
                <?= Campo::spunta('ruraleStrumentale', $campi['ruraleStrumentale'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
                <?= Campo::spunta('beneMerce', $campi['beneMerce'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
            <?php endif; ?>
            <?= Campo::spunta('areaFabbricabile', $campi['areaFabbricabile'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
            <?= Campo::testo('valoreVenale', $campi['valoreVenale'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'inputmode="decimal"']) ?>
            <?php if ($terreno === false): ?>
                <?= Campo::spunta('valoreContabile', $campi['valoreContabile'], $formEntity, $errors, ['colonna' => 'col-md-6']) ?>
            <?php endif; ?>
            <?= Campo::testo('exComune', $campi['exComune'], $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'maxlength="100" list="ex-comuni"', 'aiuto' => count($exComuni) > 0 ? $aiutoExComune : null]) ?>
            <datalist id="ex-comuni"><?php foreach ($exComuni as $exComune): ?><option value="<?= Html::e($exComune) ?>"><?php endforeach; ?></datalist>
            <?= Campo::selezioneEnum('esenzione', $campi['esenzione'], $formEntity, $errors, $esenzioni, ['colonna' => 'col-md-12', 'vuota' => $nessuna]) ?>
            <?= Campo::testo('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-12', 'attributi' => 'maxlength="255"']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>

<?php if (count($storico) > 0): ?>
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-white fw-semibold"><?= Html::e($titoloStorico) ?></div>
        <ul class="list-group list-group-flush">
            <?php foreach ($storico as $versione): ?>
                <li class="list-group-item d-flex align-items-center gap-2 small">
                    <span class="me-auto"><?= Html::data($versione->validoDal) ?> – <?= $versione->validoAl === null ? Html::e($sempre) : Html::data($versione->validoAl) ?>
                        <?php foreach ($versione->getEtichette() as $etichetta): ?><span class="badge text-bg-light border ms-1"><?= Html::e($etichetta) ?></span><?php endforeach; ?></span>
                    <a href="/caratteristiche-imu/update/caratteristiche/<?= Html::e($versione->id) ?>/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($anno) ?>" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['modifica']) ?>"><i class="bi bi-pencil"></i></a>
                    <form method="post" action="/caratteristiche-imu/delete/caratteristiche/<?= Html::e($versione->id) ?>/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($anno) ?>" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button></form>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
