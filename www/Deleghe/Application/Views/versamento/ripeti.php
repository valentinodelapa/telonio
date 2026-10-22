<?php

use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;
use Deleghe\Application\HelperClasses\Importo;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$urlRipeti = '/versamento/ripeti/versamento/' . $versamento->id;
require __DIR__ . '/../commonParts/riferimentiVersamento.php';

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><i class="bi bi-arrow-repeat me-2"></i><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($versamento->soggetto->nominativo) ?></p>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><?= Html::e($versamentoDiPartenza) ?></div>
    <div class="card-body small">
        <span class="badge text-bg-light border"><?= Html::e($versamento->sezione->getLabel()) ?></span>
        <span class="font-monospace fw-semibold"><?= Html::e($versamento->getCodice()) ?></span>
        <?= Html::e($versamento->descrizione ?? $versamento->getDescrizione()) ?> ·
        <?= Html::data($versamento->scadenza) ?> · <?= Html::e($riferimenti($versamento)) ?> ·
        € <?= Html::e(Importo::euro($versamento->importoDebito > 0 ? $versamento->importoDebito : $versamento->importoCredito)) ?>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="get" action="<?= Html::e($urlRipeti) ?>" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="periodicita"><?= Html::e($etichettaCadenza) ?></label>
                <select class="form-select" id="periodicita" name="periodicita">
                    <?php foreach ($cadenze as $voce): ?>
                        <option value="<?= Html::e($voce->value) ?>" <?= $voce === $cadenza ? 'selected' : '' ?>><?= Html::e($voce->getLabel()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="volte"><?= Html::e($etichettaVolte) ?></label>
                <input type="number" class="form-control" id="volte" name="volte" value="<?= Html::e($volte) ?>" min="1" max="<?= Html::e($massimoRipetizioni) ?>">
            </div>
            <div class="col-md-3 d-grid">
                <button type="submit" class="btn btn-outline-primary"><?= Html::e($azioni['anteprima']) ?></button>
            </div>
        </form>
        <p class="small text-body-secondary mt-2 mb-0"><?= Html::e($aiutoRipeti) ?></p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold"><?= Html::e($copieDaCreare) ?> (<?= count($copie) ?>)</div>
    <?php if (count($copie) === 0): ?>
        <div class="card-body small text-body-secondary"><?= Html::e($nessunaCopia) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-anagrafica mb-0">
                <thead><tr><th><?= Html::e($campi['scadenza']) ?></th><th><?= Html::e($campi['codice']) ?></th><th></th><th class="text-end"><?= Html::e($campi['debito']) ?></th><th class="text-end"><?= Html::e($campi['credito']) ?></th></tr></thead>
                <tbody>
                    <?php foreach ($copie as $indice => $copia): ?>
                        <tr class="<?= isset($erroriCopie[$indice]) ? 'table-danger' : '' ?>">
                            <td class="text-nowrap"><?= Html::data($copia->scadenza) ?></td>
                            <td class="font-monospace fw-semibold"><?= Html::e($copia->getCodice()) ?></td>
                            <td class="small">
                                <?= Html::e($riferimenti($copia)) ?>
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

<form method="post" action="<?= Html::e($urlRipeti . '/periodicita/' . $cadenza->value . '/volte/' . $volte) ?>" class="d-flex align-items-center gap-2 mt-4">
    <?= $campoCsrf ?>
    <button type="submit" class="btn btn-primary" <?= (count($copie) === 0 || count($erroriCopie) > 0) ? 'disabled' : '' ?>><i class="bi bi-check-lg me-1"></i><?= Html::e($azioni['creaCopie']) ?></button>
    <a href="/contribuente/show/soggetto/<?= Html::e($versamento->soggetto->id) ?>" class="btn btn-outline-secondary"><?= Html::e($azioni['annulla']) ?></a>
</form>
<?php require Layout::percorso('footer'); ?>
