<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navContabilita.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h3 mb-0 me-auto"><?= Html::e($navCodiciIva) ?></h1>
    <a href="/codice-iva/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['nuovo']) ?></a>
</div>
<p class="text-body-secondary"><?= Html::e($aiuto) ?></p>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr><th><?= Html::e($campi['codice']) ?></th><th><?= Html::e($campi['descrizione']) ?></th><th class="text-end"><?= Html::e($campi['aliquota']) ?></th><th><?= Html::e($campi['natura']) ?></th><th class="text-end"><?= Html::e($campi['detraibilita']) ?></th><th><?= Html::e($campi['norma']) ?></th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($codici as $codice): ?>
                    <tr class="<?= $codice->attivo ? '' : 'text-body-secondary' ?>">
                        <td class="font-monospace"><?= Html::e($codice->codice) ?></td>
                        <td>
                            <?= Html::e($codice->descrizione) ?>
                            <?php if ($codice->inversioneContabile): ?><span class="badge text-bg-info">RC</span><?php endif; ?>
                            <?php if ($codice->ventilazione): ?><span class="badge text-bg-warning">VEN</span><?php endif; ?>
                            <?php if ($codice->studio !== null): ?><span class="badge text-bg-primary"><?= Html::e($delloStudio) ?></span><?php endif; ?>
                        </td>
                        <td class="text-end"><?= Html::e(number_format($codice->aliquota, 2, ',', '')) ?></td>
                        <td class="small"><?= Html::e($codice->natura?->value ?? '') ?></td>
                        <td class="text-end"><?= Html::e(number_format($codice->detraibilita, 0, ',', '')) ?></td>
                        <td class="small text-body-secondary"><?= Html::e($codice->norma ?? '') ?></td>
                        <td class="text-end"><?php if ($codice->studio !== null): ?><a href="/codice-iva/update/codice/<?= Html::e($codice->id) ?>" class="btn btn-sm btn-link py-0"><i class="bi bi-pencil"></i></a><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require Layout::percorso('footer'); ?>
