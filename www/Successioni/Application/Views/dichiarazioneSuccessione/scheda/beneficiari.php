<?php

use Telonio\Application\HelperClasses\Html;

?>
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex align-items-center">
        <span class="fw-semibold me-auto"><?= Html::e($etichetteSchede['beneficiari']) ?></span>
        <?php if ($modificabile): ?><a href="/beneficiario-successione/create/dichiarazione/<?= Html::e($id) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['aggiungiBeneficiario']) ?></a><?php endif; ?>
    </div>
    <?php if (count($dati->beneficiari) === 0): ?>
        <div class="card-body text-body-secondary"><?= Html::e($nessunBeneficiario) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-anagrafica mb-0">
                <thead class="table-light"><tr><th></th><th><?= Html::e($campi['soggetto']) ?></th><th><?= Html::e($campi['tipoBeneficiario']) ?></th><th><?= Html::e($campi['gradoParentela']) ?></th><th></th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($dati->beneficiari as $beneficiario): ?>
                        <tr>
                            <td class="text-nowrap small text-body-secondary"><?= Html::e($beneficiario->getRiferimento()) ?></td>
                            <td><?= Html::e($beneficiario->soggetto->nominativo) ?><div class="small font-monospace text-body-secondary"><?= Html::e($beneficiario->soggetto->codiceFiscale) ?></div></td>
                            <td class="small"><?= Html::e($beneficiario->tipoBeneficiario->getLabel()) ?></td>
                            <td class="small"><?= Html::e($beneficiario->gradoParentela->getLabel()) ?>
                                <?php if ($beneficiario->rappresentato !== null): ?><div class="text-body-secondary">per rappresentazione di <?= Html::e($beneficiario->rappresentato->nominativo) ?></div><?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if ($beneficiario->disabile): ?><span class="badge text-bg-light border">L. 104/1992</span><?php endif; ?>
                                <?php if ($beneficiario->rinuncia): ?><span class="badge text-bg-warning">rinuncia</span><?php endif; ?>
                                <?php if ($beneficiario->soggetto->id === $dichiarazione->presentatore?->id): ?><span class="badge text-bg-primary-subtle text-primary-emphasis"><?= Html::e($ilDichiarante) ?></span><?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if ($modificabile): ?>
                                    <a href="/beneficiario-successione/update/beneficiario/<?= Html::e($beneficiario->id) ?>" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['modifica']) ?>"><i class="bi bi-pencil"></i></a>
                                    <form method="post" action="/beneficiario-successione/delete/beneficiario/<?= Html::e($beneficiario->id) ?>" class="d-inline" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <div class="card-footer bg-white small text-body-secondary"><?= Html::e($aiutoBeneficiari) ?></div>
</div>
