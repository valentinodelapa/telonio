<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;
use Imu\Application\Entities\UtilizzoMistoEnc;

$urlAnnulla = '/contribuente-imu/show/soggetto/' . $soggetto->id . '/anno/' . $anno;
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$sezioni = ['didattica' => [$sezioneDidattica, $campi['didatticaCorrispettivoMedio'], $campi['didatticaCostoMedioStudente']], 'altre' => [$sezioneAltre, $campi['altreCorrispettivoMedio'], $campi['altreCorrispettivoMercato']]];
$corrispettivi = ['didattica' => ['didatticaCorrispettivoMedio', 'didatticaCostoMedioStudente'], 'altre' => ['altreCorrispettivoMedio', 'altreCorrispettivoMercato']];

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($soggetto->nominativo) ?> · <?= Html::e($immobile->getIdentificativo()) ?> · <?= Html::e($immobile->getUbicazione()) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneAttivita) ?></div>
        <div class="card-body row g-2">
            <?php foreach ($attivita as $voce): ?>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="attivita[]" value="<?= Html::e($voce->value) ?>" id="attivita-<?= Html::e($voce->value) ?>" <?= in_array($voce->value, $attivitaScelte, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="attivita-<?= Html::e($voce->value) ?>"><?= Html::e($voce->getLabel()) ?></label>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($errors->attivitaCustomMessage): ?><div class="col-12 text-danger small"><?= Html::e($errors->attivitaCustomMessage) ?></div><?php endif; ?>
            <?= Campo::spunta('esente', $campi['esente'], $formEntity, $errors, ['colonna' => 'col-12']) ?>
            <?= Campo::selezione('tipoConcessione', $campi['tipoConcessione'], $formEntity, $errors, ['1' => $comodato, '2' => $strutturale], ['colonna' => 'col-md-4', 'vuota' => $nessuna]) ?>
            <?= Campo::testo('comodatario', $campi['comodatario'], $formEntity, $errors, ['colonna' => 'col-md-8', 'attributi' => 'maxlength="100"']) ?>
        </div>
    </div>
    <?php foreach ($sezioni as $sezione => [$titolo, $etichettaUno, $etichettaDue]): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= Html::e($titolo) ?></div>
            <div class="card-body row g-3">
                <?= Campo::testo($corrispettivi[$sezione][0], $etichettaUno, $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'inputmode="decimal"']) ?>
                <?= Campo::testo($corrispettivi[$sezione][1], $etichettaDue, $formEntity, $errors, ['colonna' => 'col-md-6', 'attributi' => 'inputmode="decimal"']) ?>
                <?php foreach (UtilizzoMistoEnc::RAPPORTI as $rapporto): ?>
                    <?= Campo::testo($sezione . $rapporto, $campi['rapporto' . $rapporto], $formEntity, $errors, ['colonna' => 'col-md-4', 'attributi' => 'inputmode="decimal"']) ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <div class="card shadow-sm">
        <div class="card-body row g-3"><?= Campo::testo('note', $campi['note'], $formEntity, $errors, ['colonna' => 'col-12', 'attributi' => 'maxlength="255"']) ?></div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php if (isset($utilizzo->id)): ?>
    <form method="post" action="/utilizzo-misto-enc/delete/utilizzo/<?= Html::e($utilizzo->id) ?>" class="mt-3" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i><?= Html::e($azioni['elimina']) ?></button></form>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
