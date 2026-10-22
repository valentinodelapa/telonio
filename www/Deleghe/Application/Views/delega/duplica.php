<?php

use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;
use Deleghe\Application\HelperClasses\Importo;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$urlDuplica = '/delega/duplica/delega/' . $delega->id;
require __DIR__ . '/../commonParts/riferimentiVersamento.php';

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><i class="bi bi-copy me-2"></i><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary">F24 <?= Html::data($delega->scadenza) ?> · <?= Html::e($delega->soggetto->nominativo) ?></p>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="get" action="<?= Html::e($urlDuplica) ?>" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="scadenza"><?= Html::e($campi['scadenza']) ?></label>
                <input type="date" class="form-control" id="scadenza" name="scadenza" value="<?= Html::data($dataCopia, 'Y-m-d') ?>" required>
            </div>
            <div class="col-md-3 d-grid">
                <button type="submit" class="btn btn-outline-primary"><?= Html::e($azioni['anteprima']) ?></button>
            </div>
        </form>
        <p class="small text-body-secondary mt-2 mb-0"><?= Html::e($aiutoDuplica) ?></p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold"><?= Html::e($copieDaCreare) ?> (<?= count($copie) ?>)</div>
    <?php if (count($copie) === 0): ?>
        <div class="card-body small text-body-secondary"><?= Html::e($nessunaCopiaDelega) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-anagrafica mb-0">
                <tbody>
                    <?php foreach ($copie as $indice => $copia): ?>
                        <tr class="<?= isset($erroriCopie[$indice]) ? 'table-danger' : '' ?>">
                            <td class="text-nowrap"><span class="badge text-bg-light border"><?= Html::e($copia->sezione->getLabel()) ?></span></td>
                            <td class="font-monospace fw-semibold"><?= Html::e($copia->getCodice()) ?></td>
                            <td class="small">
                                <?= Html::e($copia->descrizione ?? $copia->getDescrizione()) ?>
                                <div class="text-body-secondary"><?= Html::e($riferimenti($copia)) ?></div>
                                <?php foreach ($erroriCopie[$indice] ?? [] as $errore): ?><div class="text-danger"><?= Html::e($errore) ?></div><?php endforeach; ?>
                            </td>
                            <td class="text-end text-nowrap"><?= Html::e(Importo::euro($copia->importoDebito, true)) ?></td>
                            <td class="text-end text-nowrap"><?= Html::e(Importo::euro($copia->importoCredito, true)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<form method="post" action="<?= Html::e($urlDuplica) ?>" class="d-flex flex-wrap align-items-center gap-3 mt-4">
    <?= $campoCsrf ?>
    <input type="hidden" name="scadenza" value="<?= Html::data($dataCopia, 'Y-m-d') ?>">
    <div class="form-check mb-0">
        <input class="form-check-input" type="checkbox" id="genera" name="genera" value="1" checked>
        <label class="form-check-label" for="genera"><?= Html::e($etichettaGenera) ?></label>
    </div>
    <div class="form-check mb-0">
        <input class="form-check-input" type="checkbox" id="compensa" name="compensa" value="1">
        <label class="form-check-label" for="compensa"><?= Html::e($azioni['compensaTutti']) ?></label>
    </div>
    <button type="submit" class="btn btn-primary" <?= (count($copie) === 0 || count($erroriCopie) > 0) ? 'disabled' : '' ?>><i class="bi bi-check-lg me-1"></i><?= Html::e($azioni['creaCopie']) ?></button>
    <a href="/delega/show/delega/<?= Html::e($delega->id) ?>" class="btn btn-outline-secondary"><?= Html::e($azioni['annulla']) ?></a>
</form>
<?php require Layout::percorso('footer'); ?>
