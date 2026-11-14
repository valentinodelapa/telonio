<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary mb-1"><?= Html::e($soggetto->nominativo) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiutoDestinazione) ?></p>

<?php if ($risultato == 0): ?>
    <div class="alert alert-light border small"><?= Html::e($nessunRisultato) ?></div>
<?php else: ?>
    <form method="post" novalidate>
        <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold"><?= Html::e($risultato > 0 ? $utile : $perdita) ?> € <?= Html::e(Importo::euro(abs($risultato))) ?></div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label class="form-label" for="data"><?= Html::e($dataDelibera) ?></label>
                        <input type="date" class="form-control" id="data" name="data" value="<?= Html::e($dataDestinazione) ?>" required>
                    </div>
                </div>
                <?php foreach ($righeDestinazione as $indice => $riga): ?>
                    <div class="row g-2 mb-2">
                        <div class="col-md-8">
                            <select class="form-select" name="righe[<?= Html::e($indice) ?>][conto]">
                                <option value=""><?= Html::e($selezionare) ?></option>
                                <?php foreach ($scelteConti as $id => $etichetta): ?><option value="<?= Html::e($id) ?>" <?= ($riga['conto']?->id ?? null) === $id ? 'selected' : '' ?>><?= Html::e($etichetta) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4"><input type="text" class="form-control text-end" name="righe[<?= Html::e($indice) ?>][importo]" value="<?= $riga['importo'] == 0 ? '' : Html::e(Importo::euro($riga['importo'])) ?>" placeholder="0,00" inputmode="decimal"></div>
                    </div>
                <?php endforeach; ?>
                <?php if ($successivo === null): ?><div class="small text-warning-emphasis"><?= Html::e($senzaSuccessivo) ?></div><?php endif; ?>
            </div>
        </div>
        <?php $urlAnnulla = '/chiusura/index/soggetto/' . $soggetto->id . '/anno/' . $esercizio->anno; require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
    </form>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
