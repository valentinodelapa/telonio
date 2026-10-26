<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;
use Deleghe\Application\Services\EsitoControllo;

require Layout::percorso('header');
?>
<h1 class="h3 mb-3"><i class="bi bi-send me-2"></i><?= Html::e($pageTitle) ?></h1>

<?php if (isset($erroreInvio)): ?>
    <div class="alert alert-danger"><?= Html::e($erroreInvio) ?></div>
<?php endif; ?>

<form method="get" action="/invio-telematico/create" class="card shadow-sm mb-3">
    <div class="card-body row g-3 align-items-end">
        <div class="col-md-6">
            <label for="intermediario" class="form-label"><?= Html::e($campi['intermediario']) ?></label>
            <select class="form-select" id="intermediario" name="intermediario" onchange="this.form.submit()">
                <?php foreach ($intermediari as $intermediario): ?>
                    <option value="<?= Html::e($intermediario->id) ?>" <?= $intermediario->id === $intermediarioScelto->id ? 'selected' : '' ?>><?= Html::e($intermediario->getEtichetta()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="addebito" name="addebito" value="1" <?= $addebitoIntermediario ? 'checked' : '' ?> onchange="this.form.submit()">
                <label class="form-check-label" for="addebito"><?= Html::e($campi['addebitoIntermediario']) ?></label>
            </div>
            <div class="form-text"><?= Html::e($aiutoAddebito) ?></div>
        </div>
    </div>
</form>

<?php if (count($problemiIntermediario) > 0): ?>
    <div class="alert alert-warning">
        <div class="fw-semibold"><?= Html::e($titoloProblemiIntermediario) ?></div>
        <ul class="mb-0 small"><?php foreach ($problemiIntermediario as $problema): ?><li><?= Html::e($problema) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" action="/invio-telematico/create">
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <input type="hidden" name="genera" value="1">
    <input type="hidden" name="intermediario" value="<?= Html::e($intermediarioScelto->id) ?>">
    <input type="hidden" name="addebito" value="<?= $addebitoIntermediario ? '1' : '0' ?>">
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold"><?= Html::e($deleghe) ?></div>
        <?php if (count($voci) === 0): ?>
            <div class="card-body text-body-secondary"><?= Html::e($nessuna) ?></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-anagrafica mb-0">
                    <thead class="table-light"><tr><th></th><th><?= Html::e($campi['scadenza']) ?></th><th><?= Html::e($campi['soggetto']) ?></th><th class="text-end"><?= Html::e($campi['saldoFinale']) ?></th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($voci as $id => ['delega' => $delega, 'esito' => $esito, 'problemi' => $problemi]): ?>
                            <tr class="<?= count($problemi) > 0 ? 'storico-non-vigente' : '' ?>">
                                <td><input class="form-check-input" type="checkbox" name="deleghe[]" value="<?= Html::e($id) ?>" <?= count($problemi) > 0 ? 'disabled' : 'checked' ?> aria-label="<?= Html::e($delega->soggetto->nominativo) ?>"></td>
                                <td class="text-nowrap"><?= Html::data($delega->scadenza) ?></td>
                                <td>
                                    <a href="/delega/show/delega/<?= Html::e($id) ?>"><?= Html::e($delega->soggetto->nominativo) ?></a>
                                    <span class="small text-body-secondary"><?= Html::e($delega->modello->getLabel()) ?>, <?= count($esito->modelli) ?> <?= Html::e($modelli) ?></span>
                                    <?php foreach ($problemi as $problema): ?><div class="small text-danger"><?= Html::e($problema) ?></div><?php endforeach; ?>
                                </td>
                                <td class="text-end text-nowrap"><?= EsitoControllo::euro($esito->getSaldo()) ?></td>
                                <td></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <div class="d-flex align-items-center gap-2 mt-4">
        <button type="submit" class="btn btn-primary" <?= (count($problemiIntermediario) > 0 || count($voci) === 0) ? 'disabled' : '' ?>><i class="bi bi-file-earmark-arrow-down me-1"></i><?= Html::e($azioni['genera']) ?></button>
        <a href="/invio-telematico/index" class="btn btn-outline-secondary"><?= Html::e($azioni['annulla']) ?></a>
    </div>
</form>
<?php require Layout::percorso('footer'); ?>
