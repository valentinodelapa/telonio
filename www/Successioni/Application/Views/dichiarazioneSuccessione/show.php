<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;
use Successioni\Application\Enumerations\StatoDichiarazioneSuccessione;

$id = $dichiarazione->id;
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$modificabile = $dichiarazione->isModificabile();
$euro = fn(float|int $importo): string => number_format($importo, 2, ',', '.');
$euroIntero = fn(float|int $importo): string => number_format($importo, 0, ',', '.');

require Layout::percorso('header');
?>
<div class="d-flex flex-wrap align-items-start gap-2 mb-3">
    <div class="me-auto">
        <h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
        <div class="text-body-secondary small">
            <?= Html::e($dichiarazione->tipoDichiarazione->getLabel()) ?>
            · <span class="badge <?= Html::e($dichiarazione->stato->getClasseBadge()) ?>"><?= Html::e($dichiarazione->stato->getLabel()) ?></span>
            <?php if ($dichiarazione->dataPresentazione !== null): ?> <?= Html::e($presentataIl) ?> <?= Html::data($dichiarazione->dataPresentazione) ?><?= $dichiarazione->protocollo !== null ? ' · ' . Html::e($dichiarazione->protocollo) : '' ?><?= $dichiarazione->registrazioneNumero !== null ? ' · vol. ' . Html::e($dichiarazione->registrazioneVolume) . ' n. ' . Html::e($dichiarazione->registrazioneNumero) : '' ?><?php endif; ?>
            <?php if ($dichiarazione->sostituita !== null): ?> · sostituisce la dichiarazione del <?= Html::data($dichiarazione->sostituita->dataPresentazione) ?><?php endif; ?>
            · <?= Html::e($termine) ?> <?= Html::data($dichiarazione->getScadenza()) ?>
            <?php if ($dichiarazione->isAutoliquidazione()): ?> · <?= Html::e($terminePagamento) ?> <?= Html::data($dichiarazione->getScadenzaPagamento()) ?><?php endif; ?>
        </div>
    </div>
    <a href="/dichiarazione-successione/stampa/dichiarazione/<?= Html::e($id) ?>" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i><?= Html::e($azioni['stampa']) ?></a>
    <?php if (count($errori) === 0): ?>
        <a href="/dichiarazione-successione/scarica/dichiarazione/<?= Html::e($id) ?>" class="btn btn-primary"><i class="bi bi-download me-1"></i><?= Html::e($azioni['fileTelematico']) ?></a>
    <?php endif; ?>
</div>

<?php if (count($errori) > 0): ?>
    <div class="alert alert-warning small"><div class="fw-semibold mb-1"><?= Html::e($datiMancanti) ?></div><ul class="mb-0"><?php foreach ($errori as $errore): ?><li><?= Html::e($errore) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php if (count($risultato->avvisi) > 0): ?>
    <div class="alert alert-info small"><div class="fw-semibold mb-1"><?= Html::e($avvisi) ?></div><ul class="mb-0"><?php foreach ($risultato->avvisi as $avviso): ?><li><?= Html::e($avviso) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<ul class="nav nav-tabs mb-3">
    <?php foreach ($schede as $voce): ?>
        <li class="nav-item"><a class="nav-link <?= $voce === $scheda ? 'active' : '' ?>" href="/dichiarazione-successione/show/dichiarazione/<?= Html::e($id) ?>/scheda/<?= Html::e($voce) ?>"><?= Html::e($etichetteSchede[$voce]) ?></a></li>
    <?php endforeach; ?>
</ul>

<?php require __DIR__ . '/scheda/' . $scheda . '.php'; ?>

<?php if ($scheda === 'generale'): ?>
    <div class="card shadow-sm mt-3">
        <div class="card-body d-flex flex-wrap align-items-end gap-2">
            <?php if ($modificabile): ?>
                <form method="post" action="/dichiarazione-successione/stato/dichiarazione/<?= Html::e($id) ?>/stato/<?= Html::e(StatoDichiarazioneSuccessione::presentata->value) ?>" class="d-flex flex-wrap align-items-end gap-2">
                    <?= $campoCsrf ?>
                    <div><label class="form-label small mb-0" for="dataPresentazione"><?= Html::e($campi['dataPresentazione']) ?></label><input type="date" class="form-control form-control-sm" id="dataPresentazione" name="dataPresentazione" value="<?= Html::e(date('Y-m-d')) ?>"></div>
                    <div><label class="form-label small mb-0" for="protocollo"><?= Html::e($campi['protocollo']) ?></label><input type="text" class="form-control form-control-sm" id="protocollo" name="protocollo" maxlength="50"></div>
                    <div><label class="form-label small mb-0" for="registrazioneVolume"><?= Html::e($campi['registrazioneVolume']) ?></label><input type="text" class="form-control form-control-sm" id="registrazioneVolume" name="registrazioneVolume" maxlength="5" inputmode="numeric"></div>
                    <div><label class="form-label small mb-0" for="registrazioneNumero"><?= Html::e($campi['registrazioneNumero']) ?></label><input type="text" class="form-control form-control-sm" id="registrazioneNumero" name="registrazioneNumero" maxlength="6" inputmode="numeric"></div>
                    <button type="submit" class="btn btn-sm btn-success" <?= count($errori) > 0 ? 'disabled' : '' ?>><i class="bi bi-check2-circle me-1"></i><?= Html::e($azioni['segnaPresentata']) ?></button>
                </form>
                <form method="post" action="/dichiarazione-successione/delete/dichiarazione/<?= Html::e($id) ?>" class="ms-auto" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i><?= Html::e($azioni['elimina']) ?></button></form>
            <?php else: ?>
                <form method="post" action="/dichiarazione-successione/sostitutiva/dichiarazione/<?= Html::e($id) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-files me-1"></i><?= Html::e($azioni['sostitutiva']) ?></button></form>
                <form method="post" action="/dichiarazione-successione/stato/dichiarazione/<?= Html::e($id) ?>/stato/<?= Html::e(StatoDichiarazioneSuccessione::bozza->value) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-secondary"><?= Html::e($azioni['riportaBozza']) ?></button></form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
